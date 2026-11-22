(()=>{'use strict';
const ui=window.JB_UI, graph=ui.graph, readonly=ui.readonly;
const ports={trigger:['next'],condition:['yes','no'],attribute:['yes','no'],split:['a','b'],slot:['next'],delay:['next'],wait_event:['event','timeout'],sms:['success','failure'],email:['success','failure'],webpush:['success','failure'],api:['success','failure'],onsite:['success','failure'],exit:[]};
const labels={trigger:'ورود کاربران',condition:'شرط رویداد',attribute:'شرط ویژگی',split:'تقسیم مسیر',slot:'بازه زمانی',delay:'تأخیر',wait_event:'انتظار رویداد',sms:'ارسال پیامک',email:'ارسال ایمیل',webpush:'وب‌پوش نوتیفیکیشن',api:'درخواست API',onsite:'افزودن آنسایت',exit:'خروج از جرنی'};
const icons={trigger:'▶',condition:'◇',attribute:'◈',split:'⑂',slot:'◷',delay:'◴',wait_event:'⌛',sms:'✉',email:'✉',webpush:'◉',api:'↗',onsite:'▣',exit:'■'};
const colors={trigger:'#3182ee',condition:'#e0a434',attribute:'#e7a238',split:'#9d6ddd',slot:'#ed9563',delay:'#eb9463',wait_event:'#cf6d75',sms:'#14a985',email:'#7b61e8',webpush:'#0e9fbd',api:'#3677d6',onsite:'#10a088',exit:'#8795a8'};
const portLabels={next:'بعدی',yes:'بله',no:'خیر',a:'مسیر A',b:'مسیر B',event:'رویداد',timeout:'زمان تمام شد',success:'موفق',failure:'ناموفق'};
const defaults={trigger:{section:'attributes',name:'',sign:'',key:'',operator:'exists',value:''},condition:{event:'',sign:'',scope:'ever',key:'',operator:'exists',value:''},attribute:{section:'attributes',name:'',key:'',sign:'',operator:'exists',value:''},split:{percent:50},slot:{days:[0],from:'10:00',to:'15:00'},delay:{hours:24},wait_event:{event:'',sign:'',hours:24},sms:{delivery_mode:'managed',provider_id:0,template:'',message:'',receptor_attribute:'phone'},email:{provider_id:0,recipient_attribute:'email',subject:'',body:'',format:'text'},webpush:{provider_id:0,title:'',body:'',url:'',ttl:24,button_title:'',button_url:''},api:{url:'',method:'POST',token_source:'none',token_header:'bearer',token_custom_header:'X-Access-Token',headers:{},body:'{"userId":"{{userId}}","flowId":"{{flowId}}"}'},onsite:{onsite_name:'',failure_policy:'hold'},exit:{}};
const days=['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
const opNames={exists:'مقدار داشته باشد',not_exists:'مقدار نداشته باشد (قدیمی)',is_empty:'خالی باشد (NULL / Empty)',is_not_empty:'خالی نباشد',equals:'برابر باشد',not_equals:'برابر نباشد',starts_with:'با مقدار شروع شود',not_starts_with:'با مقدار شروع نشود'};
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const uid=()=> 'n'+Math.random().toString(36).slice(2,11);
let selectedId=null,source=null,dirty=false;
// Editor viewport is independent from the stored graph coordinates.
// Large journeys should never be clipped by a fixed 2600 x 2200 board.
const NODE_WIDTH=238, NODE_HEIGHT=142, BOARD_MARGIN=620, MAX_COORD=10000;
const board=$('board'), boardShell=$('board-shell'), viewport=$('viewport');
// Visual padding is independent of persisted graph coordinates: even a node at x=0
// has a large area to its left for panning / placing other branches.
let originX=1700, originY=340;
let zoom=1, boardWidth=4800, boardHeight=4000;
const visualX=n=>Number(n.x)+originX, visualY=n=>Number(n.y)+originY;
const outputPercent=(index,count)=>count<=1?50:25+50*index/(count-1);
// Graph coordinates remain non-negative for server-side validators. When a node is
// moved to the virtual left/top of x=0, shift the saved coordinates (not its image)
// and compensate through the visual origin. Existing node positions do not jump.
function rebaseIfNeeded(x,y){
  const dx=x<0?Math.ceil(180-x):0,dy=y<0?Math.ceil(140-y):0;
  if(!dx&&!dy)return {x,y,dx:0,dy:0};
  if(graph.nodes.some(n=>Number(n.x)+dx>MAX_COORD||Number(n.y)+dy>MAX_COORD)){
    notify('محدوده مجاز گره‌ها پر شده؛ ابتدا گره‌های دورتر را نزدیک‌تر بیاورید.',true);
    return null;
  }
  for(const node of graph.nodes){node.x=Number(node.x)+dx;node.y=Number(node.y)+dy;}
  originX-=dx;originY-=dy;dirty=true;
  return {x:x+dx,y:y+dy,dx,dy};
}
function measureNodes(){
  const ns=graph.nodes.filter(n=>Number.isFinite(Number(n.x))&&Number.isFinite(Number(n.y)));
  return {
    minX:ns.length?Math.min(...ns.map(n=>visualX(n))):originX,
    minY:ns.length?Math.min(...ns.map(n=>visualY(n))):originY,
    maxX:ns.length?Math.max(...ns.map(n=>visualX(n))):originX,
    maxY:ns.length?Math.max(...ns.map(n=>visualY(n))):originY,
  };
}
let minimapScheduled=false;
function scheduleMinimap(){
  if(minimapScheduled)return;
  minimapScheduled=true;
  requestAnimationFrame(()=>{minimapScheduled=false;drawMinimap()});
}
function sizeBoard(){
  const bounds=measureNodes();
  // Reserve left and right margins without altering the stored node coordinates.
  boardWidth=Math.max(4800,Math.ceil((bounds.maxX+NODE_WIDTH+BOARD_MARGIN)/200)*200,
    Math.ceil((viewport.clientWidth/zoom+BOARD_MARGIN)/200)*200);
  boardHeight=Math.max(4000,Math.ceil((bounds.maxY+NODE_HEIGHT+BOARD_MARGIN)/200)*200,
    Math.ceil((viewport.clientHeight/zoom+BOARD_MARGIN)/200)*200);
  board.style.width=boardWidth+'px';
  board.style.height=boardHeight+'px';
  board.style.transform=`scale(${zoom})`;
  boardShell.style.width=(boardWidth*zoom)+'px';
  boardShell.style.height=(boardHeight*zoom)+'px';
  const svg=$('wires');
  svg.setAttribute('viewBox',`0 0 ${boardWidth} ${boardHeight}`);
  svg.setAttribute('width',String(boardWidth));
  svg.setAttribute('height',String(boardHeight));
  scheduleMinimap();
}
function setZoom(value,{focus=true,clientX=null,clientY=null}={}){
  const next=Math.min(2.5,Math.max(0.02,Math.round(value*1000)/1000));
  if(next===zoom)return;
  const r=viewport.getBoundingClientRect();
  const anchorX=clientX==null?viewport.clientWidth/2:Math.max(0,Math.min(viewport.clientWidth,clientX-r.left));
  const anchorY=clientY==null?viewport.clientHeight/2:Math.max(0,Math.min(viewport.clientHeight,clientY-r.top));
  const pointX=(viewport.scrollLeft+anchorX)/zoom,pointY=(viewport.scrollTop+anchorY)/zoom;
  zoom=next;
  sizeBoard();
  if(focus){viewport.scrollLeft=pointX*zoom-anchorX;viewport.scrollTop=pointY*zoom-anchorY;}
  const label=$('jb-zoom-label');if(label)label.textContent=Math.round(zoom*100)+'٪';
  scheduleMinimap();
}
function fitNodes(){
  if(!graph.nodes.length){setZoom(1,{focus:false});viewport.scrollLeft=originX*zoom-viewport.clientWidth/2;viewport.scrollTop=0;scheduleMinimap();return;}
  const {minX,minY,maxX,maxY}=measureNodes();
  const contentW=maxX-minX+NODE_WIDTH+160,contentH=maxY-minY+NODE_HEIGHT+160;
  const target=Math.min(1,Math.max(0.02,Math.min((viewport.clientWidth-32)/contentW,(viewport.clientHeight-32)/contentH)));
  setZoom(target,{focus:false});
  viewport.scrollLeft=(minX+maxX+NODE_WIDTH)*zoom/2-viewport.clientWidth/2;
  viewport.scrollTop=(minY+maxY+NODE_HEIGHT)*zoom/2-viewport.clientHeight/2;
  scheduleMinimap();
}
function focusNode(node){
  viewport.scrollLeft=(visualX(node)+NODE_WIDTH/2)*zoom-viewport.clientWidth/2;
  viewport.scrollTop=(visualY(node)+NODE_HEIGHT/2)*zoom-viewport.clientHeight/2;
  scheduleMinimap();
}
// Small interactive bird's-eye map. It only reads the rendered graph; it never
// modifies graph_json or makes any network requests.
let minimapTransform=null;
function drawMinimap(){
  const canvas=$('jb-minimap');if(!canvas)return;
  const rect=canvas.getBoundingClientRect();if(rect.width<1||rect.height<1)return;
  const pixelRatio=Math.min(window.devicePixelRatio||1,3);
  const targetW=Math.max(1,Math.round(rect.width*pixelRatio)),targetH=Math.max(1,Math.round(rect.height*pixelRatio));
  if(canvas.width!==targetW||canvas.height!==targetH){canvas.width=targetW;canvas.height=targetH;}
  const ctx=canvas.getContext('2d');if(!ctx)return;
  ctx.setTransform(pixelRatio,0,0,pixelRatio,0,0);
  const w=rect.width,h=rect.height;
  ctx.clearRect(0,0,w,h);
  ctx.fillStyle='#f5f8fd';ctx.fillRect(0,0,w,h);
  const pad=8,scale=Math.min((w-2*pad)/boardWidth,(h-2*pad)/boardHeight);
  const ox=(w-boardWidth*scale)/2,oy=(h-boardHeight*scale)/2;
  minimapTransform={ox,oy,scale};
  const projectX=x=>ox+x*scale,projectY=y=>oy+y*scale;
  const nodesById=new Map(graph.nodes.map(n=>[n.id,n]));
  ctx.lineWidth=1;ctx.strokeStyle='#bac7d9';
  for(const edge of graph.edges){
    const a=nodesById.get(edge.source),b=nodesById.get(edge.target);if(!a||!b)continue;
    ctx.beginPath();ctx.moveTo(projectX(visualX(a)+NODE_WIDTH/2),projectY(visualY(a)+NODE_HEIGHT));
    ctx.lineTo(projectX(visualX(b)+NODE_WIDTH/2),projectY(visualY(b)));ctx.stroke();
  }
  for(const n of graph.nodes){
    const x=projectX(visualX(n)),y=projectY(visualY(n));
    ctx.fillStyle=colors[n.type]||'#64748b';
    ctx.fillRect(x,y,Math.max(3,NODE_WIDTH*scale),Math.max(2,NODE_HEIGHT*scale));
    if(n.id===selectedId){ctx.strokeStyle='#1e3a8a';ctx.lineWidth=1.5;ctx.strokeRect(x-2,y-2,Math.max(3,NODE_WIDTH*scale)+4,Math.max(2,NODE_HEIGHT*scale)+4);}
  }
  const visibleX=viewport.scrollLeft/zoom,visibleY=viewport.scrollTop/zoom;
  const visibleW=viewport.clientWidth/zoom,visibleH=viewport.clientHeight/zoom;
  ctx.fillStyle='rgba(65,107,226,.13)';ctx.strokeStyle='#4f46e5';ctx.lineWidth=1.5;
  ctx.fillRect(projectX(visibleX),projectY(visibleY),visibleW*scale,visibleH*scale);
  ctx.strokeRect(projectX(visibleX),projectY(visibleY),visibleW*scale,visibleH*scale);
}
const minimapCanvas=$('jb-minimap');
let minimapDragging=false;
function panFromMinimap(e){
  const t=minimapTransform,c=minimapCanvas;if(!t||!c)return;
  const r=c.getBoundingClientRect();
  const x=(e.clientX-r.left-t.ox)/t.scale,y=(e.clientY-r.top-t.oy)/t.scale;
  viewport.scrollLeft=x*zoom-viewport.clientWidth/2;
  viewport.scrollTop=y*zoom-viewport.clientHeight/2;
  scheduleMinimap();
}
minimapCanvas?.addEventListener('pointerdown',e=>{
  if(e.button!==0)return;
  minimapDragging=true;minimapCanvas.setPointerCapture(e.pointerId);
  panFromMinimap(e);e.preventDefault();
});
minimapCanvas?.addEventListener('pointermove',e=>{if(minimapDragging)panFromMinimap(e)});
const stopMinimapPan=()=>{minimapDragging=false};
minimapCanvas?.addEventListener('pointerup',stopMinimapPan);
minimapCanvas?.addEventListener('pointercancel',stopMinimapPan);
minimapCanvas?.addEventListener('lostpointercapture',stopMinimapPan);
minimapCanvas?.addEventListener('keydown',e=>{
  const amount=100*zoom;
  if(e.key==='ArrowRight')viewport.scrollLeft+=amount;
  else if(e.key==='ArrowLeft')viewport.scrollLeft-=amount;
  else if(e.key==='ArrowDown')viewport.scrollTop+=amount;
  else if(e.key==='ArrowUp')viewport.scrollTop-=amount;
  else return;
  e.preventDefault();scheduleMinimap();
});
viewport.addEventListener('scroll',scheduleMinimap,{passive:true});

const configuredSecrets=new Set(ui.secretNodeIds||[]), pendingSecrets=new Map(), clearedSecrets=new Set();
function notify(text,error=false){const t=$('toast');t.textContent=text;t.style.background=error?'#ae3049':'#233d65';t.style.display='block';clearTimeout(notify.timer);notify.timer=setTimeout(()=>t.style.display='none',4800)}
function describe(n){const c=n.config||{};switch(n.type){case'trigger':return `${c.section||'attributes'} / ${c.name||'*'} / ${c.key||'کلید تعیین نشده'} · ${opNames[c.operator]||''}`;case'condition':return `${c.event||'رویداد تعیین نشده'}${c.key?' / '+c.key+' · '+(opNames[c.operator]||''):''} · ${c.scope==='after_enroll'?'بعد از ورود':'کل سوابق'}`;case'attribute':return `${c.section||'attributes'} / ${c.name||'*'} / ${c.key||'تعیین نشده'} · ${opNames[c.operator]||''}`;case'split':return `${c.percent??50}٪ مسیر A / ${100-(Number(c.percent)||0)}٪ مسیر B`;case'slot':return `${(c.days||[]).map(d=>days[d]).join('، ')} · ${c.from||''} تا ${c.to||''}`;case'delay':return `${c.hours??24} ساعت`;case'wait_event':return `${c.event||'رویداد تعیین نشده'}${c.key?' / '+c.key+' · '+(opNames[c.operator]||''):''} · ${c.hours??24} ساعت`;case'sms':return (c.delivery_mode||'legacy')==='managed'?`پیامک متنی · ${c.message?String(c.message).slice(0,38):'متن تعیین نشده'}`:`قالب قدیمی: ${c.template||'تعیین نشده'}`;case'email':return `ایمیل · ${c.subject||'موضوع تعیین نشده'}`;case'webpush':return `وب‌پوش نوتیفیکیشن · ${c.title||'عنوان تعیین نشده'}`;case'api':return `${c.method||'POST'} · ${c.url||'آدرس تعیین نشده'}`;case'onsite':return `آنسایت · ${c.onsite_name||'انتخاب نشده'}`;case'exit':return 'پایان این مسیر'}}
function redraw(){
  const root=$('nodes');root.innerHTML='';
  for(const n of graph.nodes){
    n.x=Number(n.x)||0;n.y=Number(n.y)||0;
    const el=document.createElement('div');el.id='node-'+n.id;
    el.className='node'+(n.id===selectedId?' selected':'');
    el.style.left=visualX(n)+'px';el.style.top=visualY(n)+'px';
    el.style.setProperty('--accent',colors[n.type]||'#678');
    const available=ports[n.type]||[];
    const outs=available.map((port,i)=>`<button class="socket output ${source?.id===n.id&&source.port===port?'highlight':''}" type="button" data-port="${port}" title="${esc(portLabels[port])}" style="left:${outputPercent(i,available.length)}%"><span class="socket-label">${esc(portLabels[port])}</span></button>`).join('');
    el.innerHTML=`<div class="node-head"><span class="iconbox">${icons[n.type]||'■'}</span><strong>${labels[n.type]||n.type}</strong></div><div class="node-body">${esc(describe(n))}</div>${n.type!=='trigger'?'<button type="button" class="socket input" title="ورودی گره از بالا"></button>':''}${outs}`;
    el.addEventListener('click',()=>select(n.id));
    el.querySelector('.node-head').addEventListener('pointerdown',e=>moveNode(e,n,el));
    el.querySelector('.input')?.addEventListener('click',e=>{e.stopPropagation();connect(n.id)});
    el.querySelectorAll('.output').forEach(btn=>btn.addEventListener('click',e=>{
      e.stopPropagation();if(readonly)return;
      source={id:n.id,port:btn.dataset.port};redraw();notify('حالا اتصال ورودی بالای گره مقصد را بزنید.');
    }));root.appendChild(el);
  }
  drawWires();
}
function drawWires(){
  sizeBoard();const svg=$('wires');svg.innerHTML='';
  const ns='http://www.w3.org/2000/svg';const defs=document.createElementNS(ns,'defs'),m=document.createElementNS(ns,'marker');
  m.setAttribute('id','jb-arrow');[['markerWidth','9'],['markerHeight','9'],['refX','8'],['refY','3'],['orient','auto']].forEach(([k,v])=>m.setAttribute(k,v));
  const tri=document.createElementNS(ns,'path');tri.setAttribute('d','M0 0 L8 3 L0 6 z');tri.setAttribute('fill','#94a4bd');m.appendChild(tri);defs.appendChild(m);svg.appendChild(defs);
  for(const edge of graph.edges){
    const a=graph.nodes.find(n=>n.id===edge.source),b=graph.nodes.find(n=>n.id===edge.target);
    if(!a||!b)continue;
    const available=ports[a.type]||[],index=available.indexOf(edge.port);if(index<0)continue;
    const aElement=$('node-'+a.id),bElement=$('node-'+b.id);
    const sourceWidth=aElement?.offsetWidth||NODE_WIDTH,sourceHeight=aElement?.offsetHeight||NODE_HEIGHT;
    const targetWidth=bElement?.offsetWidth||NODE_WIDTH;
    const sx=visualX(a)+sourceWidth*outputPercent(index,available.length)/100;
    const sy=visualY(a)+sourceHeight;
    const tx=visualX(b)+targetWidth/2,ty=visualY(b);
    let d;
    if(ty>=sy+12){
      const dy=Math.max(58,Math.abs(ty-sy)*0.48);
      d=`M${sx} ${sy} C${sx} ${sy+dy},${tx} ${ty-dy},${tx} ${ty}`;
    }else{
      // A backwards edge goes around the cards before entering target from above.
      const side=tx>=sx?1:-1;
      const bypass=side>0?Math.max(sx,tx)+145:Math.min(sx,tx)-145;
      d=`M${sx} ${sy} C${sx} ${sy+60},${bypass} ${sy+40},${bypass} ${sy+76} L${bypass} ${ty-70} C${bypass} ${ty-32},${tx} ${ty-45},${tx} ${ty}`;
    }
    const path=document.createElementNS(ns,'path');path.setAttribute('class','wire');path.setAttribute('d',d);
    path.setAttribute('marker-end','url(#jb-arrow)');
    path.addEventListener('click',()=>{if(!readonly&&confirm('این اتصال حذف شود؟')){graph.edges=graph.edges.filter(z=>z!==edge);dirty=true;redraw()}});
    svg.appendChild(path);
  }
  scheduleMinimap();
}
function moveNode(e,n,el){
  if(readonly||e.button!==0)return;
  e.preventDefault();const sx=e.clientX,sy=e.clientY;
  let ox=n.x,oy=n.y;el.setPointerCapture(e.pointerId);
  const onmove=ev=>{
    let x=ox+(ev.clientX-sx)/zoom,y=oy+(ev.clientY-sy)/zoom;
    const rebased=rebaseIfNeeded(x,y);
    if(rebased){ox+=rebased.dx;oy+=rebased.dy;x=rebased.x;y=rebased.y;}
    n.x=Math.max(0,Math.min(MAX_COORD,Math.round(x)));
    n.y=Math.max(0,Math.min(MAX_COORD,Math.round(y)));
    el.style.left=visualX(n)+'px';el.style.top=visualY(n)+'px';drawWires();
  };
  const onup=()=>{el.removeEventListener('pointermove',onmove);el.removeEventListener('pointerup',onup);dirty=true;select(n.id)};
  el.addEventListener('pointermove',onmove);el.addEventListener('pointerup',onup);
}
function connect(id){if(readonly||!source)return;if(source.id===id)return notify('گره به خودش متصل نمی‌شود.',true);graph.edges=graph.edges.filter(e=>!(e.source===source.id&&e.port===source.port));graph.edges.push({id:uid(),source:source.id,port:source.port,target:id});source=null;dirty=true;redraw()}
function addNode(type,x,y){
  if(readonly)return notify('طرح در وضعیت فعلی قابل ویرایش نیست.',true);
  if(type==='trigger'&&graph.nodes.some(n=>n.type==='trigger'))return notify('هر جرنی فقط یک نقطه شروع دارد.',true);
  const point=rebaseIfNeeded(x,y);if(!point)return;
  const n={id:uid(),type,x:Math.max(0,Math.min(MAX_COORD,Math.round(point.x))),y:Math.max(0,Math.min(MAX_COORD,Math.round(point.y))),config:structuredClone(defaults[type])};
  graph.nodes.push(n);dirty=true;select(n.id);focusNode(n);
}
function field(label,id,value,type='text',extra=''){return `<div class="field"><span>${label}</span><input id="${id}" type="${type}" value="${esc(value)}" ${extra}></div>`}
function selectField(label,id,value,choices){return `<div class="field"><span>${label}</span><select id="${id}">${choices.map(([k,v])=>`<option value="${esc(k)}" ${k===value?'selected':''}>${esc(v)}</option>`).join('')}</select></div>`}
function textField(label,id,value,rows=4){return `<div class="field"><span>${label}</span><textarea id="${id}" rows="${rows}">${esc(value)}</textarea></div>`}
const ops=Object.entries(opNames);const eventScope=[['ever','تمام سوابق موجود'],['after_enroll','از زمان ورود به جرنی']];
function select(id){selectedId=id;redraw();const n=graph.nodes.find(n=>n.id===id),box=$('inspector');if(!n){box.innerHTML='';return}const c=n.config||{};let html=`<b>${labels[n.type]}</b>`;
if(n.type==='trigger')html+=selectField('بخش دیتابیس (section)','cfg-section',c.section||'attributes',[['attributes','ویژگی کاربران (attributes)'],['events','رویدادها (events)']])+field('نام رویداد / مجموعه (name، اختیاری)','cfg-name',c.name||'','text','maxlength="50"')+field('منبع sign (اختیاری)','cfg-sign',c.sign||'')+field('کلید (key، برای رویداد اختیاری)','cfg-key',c.key||'')+selectField('فیلتر ورود','cfg-operator',c.operator||'exists',ops)+field('مقدار (در صورت نیاز)','cfg-value',c.value||'')+'<div class="help">مثال: events / Reg_Started / NationalCode / خالی باشد. مقدار خالی یعنی ردیفی با value برابر NULL یا رشته خالی وجود دارد.</div>';
if(n.type==='condition')html+=field('نام رویداد (name)','cfg-event',c.event||'')+field('sign اختیاری','cfg-sign',c.sign||'')+field('کلید داخل رویداد (key، اختیاری)','cfg-key',c.key||'')+selectField('عملگر مقدار رویداد','cfg-operator',c.operator||'exists',ops)+field('مقدار (در صورت نیاز)','cfg-value',c.value||'')+selectField('محدوده زمانی','cfg-scope',c.scope||'ever',eventScope);
if(n.type==='attribute')html+=selectField('بخش دیتابیس (section)','cfg-section',c.section||'attributes',[['attributes','ویژگی کاربران (attributes)'],['events','رویدادها (events)']])+field('نام رویداد / مجموعه (name، اختیاری)','cfg-name',c.name||'','text','maxlength="50"')+field('کلید (key، برای رویداد اختیاری)','cfg-key',c.key||'')+field('sign اختیاری','cfg-sign',c.sign||'')+selectField('عملگر','cfg-operator',c.operator||'exists',ops)+field('مقدار','cfg-value',c.value||'');
if(n.type==='split')html+=field('درصد تخصیص به مسیر A','cfg-percent',c.percent??50,'number','min="0" max="100"');
if(n.type==='slot')html+=field('از ساعت','cfg-from',c.from||'10:00','time')+field('تا ساعت','cfg-to',c.to||'15:00','time')+`<div class="help">روزهای مجاز به وقت جرنی</div><div class="days">${days.map((day,i)=>`<label><input type="checkbox" data-day="${i}" ${(c.days||[]).includes(i)?'checked':''}>${day}</label>`).join('')}</div>`;
if(n.type==='delay'||n.type==='wait_event')html+=field('مدت انتظار (ساعت)','cfg-hours',c.hours??24,'number','min="1" max="2160"');
if(n.type==='wait_event')html+=field('نام رویداد (name)','cfg-event',c.event||'')+field('sign اختیاری','cfg-sign',c.sign||'')+field('کلید داخل رویداد (key، اختیاری)','cfg-key',c.key||'')+selectField('عملگر مقدار رویداد','cfg-operator',c.operator||'exists',ops)+field('مقدار (در صورت نیاز)','cfg-value',c.value||'')+'<div class="help">وقتی شرط رویداد برقرار شود از خروجی «رویداد» عبور می‌کند؛ وگرنه تا پایان مهلت منتظر می‌ماند.</div>';
if(n.type==='sms'){
 const mode=c.delivery_mode||'legacy';
 html+=selectField('روش ارسال','cfg-delivery_mode',mode,[['managed','پنل پیامکی مدیریت‌شده (کاوه‌نگار)'],['legacy','اتصال پیامک قدیمی']]);
 html+='<div id="sms-managed-settings">'
   +selectField('پنل پیامک','cfg-provider_id',String(c.provider_id||0),[['0','پنل پیش‌فرض سیستم'],...(ui.smsProviders||[]).map(p=>[String(p.id),p.name])])
   +field('Attribute شماره گیرنده (receptor)','cfg-receptor_attribute',c.receptor_attribute||'phone','text','dir="ltr" maxlength="50"')
   +textField('متن پیامک (message)','cfg-message',c.message||'',6)
   +selectField('معیار موفقیت','cfg-success_mode',c.success_mode||'accepted', [['accepted','پذیرش توسط پنل پیامک'],['delivered','تحویل واقعی به گیرنده (استعلام وضعیت)']])
   +'<div class="help">متغیرها: {{firstName}}، {{lastName}}، {{phone}}، {{userId}} یا نام هر Attribute انگلیسی ذخیره‌شده. در زمان ارسال از دیتابیس خوانده می‌شوند. شماره گیرنده از Attribute مشخص‌شده در همین گره خوانده می‌شود؛ تنظیمات پنل فقط sender و API-KEY را نگه می‌دارد.</div>'
   +'<div class="help"><a href="'+esc(ui.smsProvidersUrl||'#')+'" target="_blank" rel="noopener noreferrer">مدیریت پنل‌ها و معرفی پارامترهای message / sender / receptor / API-KEY</a></div>'
   +'</div><div id="sms-legacy-settings">'
   +field('کد Template قدیمی','cfg-template',c.template||'')
   +'<div class="help">فقط برای جرنی‌های قدیمی که از API پیامک JSON استفاده می‌کنند.</div></div>';
}
if(n.type==='email'){
 html+=selectField('حساب SMTP','cfg-provider_id',String(c.provider_id||0),[['0','سرویس پیش‌فرض'],...(ui.emailProviders||[]).map(p=>[String(p.id),p.name])])
 +field('Attribute ایمیل گیرنده','cfg-recipient_attribute',c.recipient_attribute||'email','text','dir="ltr"')
 +field('موضوع ایمیل','cfg-subject',c.subject||'')
 +selectField('قالب ایمیل','cfg-format',c.format||'text',[['text','متن ساده'],['html','HTML']])
 +textField('متن ایمیل','cfg-body',c.body||'',9)
 +'<div class="help">شخصی‌سازی: {{firstName}}، {{email}} و سایر Attributeها. <a href="'+esc(ui.emailProvidersUrl||'#')+'" target="_blank">مدیریت حساب SMTP</a>. POP3 فقط برای دریافت است.</div>';
}
if(n.type==='webpush'){
 html+=selectField('سرویس‌دهنده وب‌پوش','cfg-provider_id',String(c.provider_id||0),[['0','سرویس پیش‌فرض'],...(ui.pushProviders||[]).map(p=>[String(p.id),p.name])])
 +field('عنوان وب‌پوش','cfg-title',c.title||'')
 +textField('متن وب‌پوش','cfg-body',c.body||'',5)
 +field('لینک کلیک (HTTPS)','cfg-url',c.url||'','url','dir="ltr"')
 +field('متن دکمه اختیاری','cfg-button_title',c.button_title||'')
 +field('لینک دکمه (HTTPS)','cfg-button_url',c.button_url||'','url','dir="ltr"')
 +field('TTL به ساعت','cfg-ttl',c.ttl??24,'number','min="1" max="168"')
 +'<div class="help">فقط برای توکن‌های عضوِ همین کاربر. <a href="'+esc(ui.pushProvidersUrl||'#')+'" target="_blank">مدیریت وب‌پوش نوتیفیکیشن</a>.</div>';
}
if(n.type==='api'){
 const authSource=c.token_source==='secret'?'secret':'none';
 html+=field('آدرس HTTPS API','cfg-url',c.url||'','url','dir="ltr"')
  +selectField('متد HTTP','cfg-method',c.method||'POST',[['POST','POST'],['PUT','PUT'],['PATCH','PATCH']])
  +selectField('احراز هویت درخواست','cfg-token_source',authSource,[['none','بدون توکن'],['secret','توکن اختصاصی برای همین درخواست']])

  +'<div id="api-secret-settings"><div class="field"><span>توکن اختصاصی (فقط هنگام ثبت/تعویض)</span><input id="cfg-secret" type="password" autocomplete="new-password" maxlength="4096" dir="ltr" placeholder="توکن جدید را اینجا وارد کنید"></div><div class="secret-state" id="secret-state"></div><div class="secret-tools"><button type="button" class="btn danger" id="clear-secret">حذف توکن ثبت‌شده</button></div><div class="help">توکن در جدول جدا و با APP_KEY رمزگذاری می‌شود. مقدار قبلی دوباره نمایش داده نمی‌شود؛ برای تغییر، توکن جدید وارد کنید و «ذخیره تغییرات» را بزنید.</div></div>'
  +'<div id="api-header-settings">'
  +selectField('محل قرارگیری توکن در درخواست','cfg-token_header',c.token_header||'bearer',[['bearer','Authorization: Bearer'],['basic','Authorization: Basic'],['x-api-key','X-API-Key'],['custom','هدر سفارشی X-...']])
  +'<div id="api-custom-header-settings">'+field('نام هدر سفارشی','cfg-token_custom_header',c.token_custom_header||'X-Access-Token','text','dir="ltr"')+'</div><div id="api-basic-help" class="help">در بخش توکن بالا مقدار Base64 مربوط به نام‌کاربری و رمز عبور را وارد کنید. پیشوند Basic لازم نیست و مقدار در گراف ذخیره نمی‌شود.</div></div>'
  +'<div class="jb-request-headers"><b>هدرهای اضافی درخواست</b><p class="help">Content-Type: application/json به‌صورت خودکار تنظیم می‌شود. Authorization را در بخش احراز هویت بالا قرار بدهید.</p><div id="api-extra-headers"></div><button class="btn" type="button" id="api-add-header">+ افزودن هدر</button></div>'
  +textField('بدنه JSON (متغیرها قابل استفاده‌اند)','cfg-body',c.body||'{}',8)
  +'<div class="help">متغیرها: {{userId}}, {{flowId}}, {{runId}}, {{nodeId}} و Attributeهای کاربر مثل {{phone}}. فرمت WebEngage مثل user["system"]["phone"] پشتیبانی نمی‌شود. API فقط به دامنه عمومی HTTPS روی پورت 443 متصل می‌شود؛ HTTP با پورت سفارشی یا IP خصوصی مسدود است.</div>';
}
if(n.type==='onsite') {
  const choices=[['','انتخاب آنسایت'],...(ui.onsites||[]).map(name=>[String(name),String(name)])];
  if(c.onsite_name && !choices.some(([v])=>v===c.onsite_name))choices.push([c.onsite_name,`${c.onsite_name} (نام ذخیره‌شده)`]);
  html+=selectField('انتخاب Onsite از MongoDB (onsite_name)','cfg-onsite_name',c.onsite_name||'',choices);
  if(ui.onsitesError)html+='<div class="jb-onsite-warning">'+esc(ui.onsitesError)+'</div>';
}
if(n.type==='exit')html+='';
if(['sms','api','email','webpush','onsite'].includes(n.type)) {
 html+=selectField('رفتار در شکست قطعی','cfg-failure_policy',c.failure_policy||'hold', [['hold','توقف برای بررسی و Retry'],['branch','ادامه از خروجی Failure']]);
}
if(['sms','email','webpush'].includes(n.type))html+='<button type="button" class="btn jb-preview-launch" id="preview-node">◉ پیش‌نمایش '+(n.type==='sms'?'پیامک':n.type==='email'?'ایمیل':'وب‌پوش نوتیفیکیشن')+'</button>';
if(!readonly)html+='<button class="btn danger" id="delete-node" style="width:100%;margin-top:15px">حذف گره</button>';
box.innerHTML=html;
box.querySelectorAll('.help,.muted-box').forEach(element=>element.remove());
const map={'cfg-section':'section','cfg-name':'name','cfg-sign':'sign','cfg-key':'key','cfg-operator':'operator','cfg-value':'value','cfg-event':'event','cfg-scope':'scope','cfg-percent':'percent','cfg-from':'from','cfg-to':'to','cfg-hours':'hours','cfg-template':'template','cfg-message':'message','cfg-receptor_attribute':'receptor_attribute','cfg-delivery_mode':'delivery_mode','cfg-success_mode':'success_mode','cfg-failure_policy':'failure_policy','cfg-provider_id':'provider_id','cfg-url':'url','cfg-method':'method','cfg-token_source':'token_source','cfg-token_header':'token_header','cfg-token_custom_header':'token_custom_header','cfg-body':'body','cfg-recipient_attribute':'recipient_attribute','cfg-subject':'subject','cfg-format':'format','cfg-title':'title','cfg-ttl':'ttl','cfg-button_title':'button_title','cfg-button_url':'button_url','cfg-onsite_name':'onsite_name'};
for(const [input,key] of Object.entries(map)){const el=$(input);if(!el)continue;el.disabled=readonly;el.addEventListener('input',()=>{c[key]=['hours','percent','provider_id','ttl'].includes(key)?Number(el.value):el.value;dirty=true;const nbody=$('node-'+n.id)?.querySelector('.node-body');if(nbody)nbody.textContent=describe(n)})}
if(n.type==='sms'){
 const modeInput=$('cfg-delivery_mode');
 const sync=()=>{
   $('sms-managed-settings')?.classList.toggle('api-hidden', modeInput?.value!=='managed');
   $('sms-legacy-settings')?.classList.toggle('api-hidden', modeInput?.value==='managed');
 };
 modeInput?.addEventListener('change',sync);sync();
}
if(n.type==='api'){
 // HTTP headers are node-specific, non-secret key/value pairs; credentials stay encrypted separately.
 const headerBox=$('api-extra-headers');
 const headerEntries=Object.entries(c.headers && typeof c.headers==='object' && !Array.isArray(c.headers) ? c.headers : {});
 const cleanHeaderEntries=headerEntries.map(([name,value])=>({name,value:String(value??'')}));
 const updateHeaders=()=>{c.headers={};for(const row of cleanHeaderEntries){if(row.name.trim())c.headers[row.name.trim()]=row.value;}dirty=true;};
 const drawHeaderRows=()=>{
   headerBox.innerHTML='';
   cleanHeaderEntries.forEach((row,index)=>{
     const div=document.createElement('div');div.className='jb-header-row';
     div.innerHTML='<input class="jb-header-name" aria-label="نام هدر" placeholder="X-Campaign" dir="ltr" maxlength="80"><input class="jb-header-value" aria-label="مقدار هدر" placeholder="مقدار" dir="ltr" maxlength="1000"><button class="btn danger jb-header-remove" type="button" aria-label="حذف هدر">×</button>';
     const nameInput=div.querySelector('.jb-header-name'),valueInput=div.querySelector('.jb-header-value');
     nameInput.value=row.name;valueInput.value=row.value;
     nameInput.disabled=readonly;valueInput.disabled=readonly;
     div.querySelector('.jb-header-remove').disabled=readonly;
     nameInput.addEventListener('input',()=>{row.name=nameInput.value;updateHeaders()});
     valueInput.addEventListener('input',()=>{row.value=valueInput.value;updateHeaders()});
     div.querySelector('.jb-header-remove').addEventListener('click',()=>{cleanHeaderEntries.splice(index,1);updateHeaders();drawHeaderRows()});
     headerBox.appendChild(div);
   });
 };
 $('api-add-header').disabled=readonly;
 $('api-add-header').addEventListener('click',()=>{if(cleanHeaderEntries.length>=12)return notify('حداکثر ۱۲ هدر اضافی مجاز است.',true);cleanHeaderEntries.push({name:'',value:''});drawHeaderRows();dirty=true;});
 drawHeaderRows();
 const authSource=$('cfg-token_source'), authHeader=$('cfg-token_header'), secretInput=$('cfg-secret');
 const authFields=()=>{
   const mode=authSource?.value||'none';
   $('api-secret-settings')?.classList.toggle('api-hidden',mode!=='secret');
   $('api-header-settings')?.classList.toggle('api-hidden',mode==='none');
   $('api-custom-header-settings')?.classList.toggle('api-hidden',mode==='none'||authHeader?.value!=='custom');
   $('api-basic-help')?.classList.toggle('api-hidden',mode==='none'||authHeader?.value!=='basic');
 };
 const refreshSecret=()=>{
   const state=$('secret-state');if(!state)return;
   const pending=pendingSecrets.has(n.id);
   const configured=configuredSecrets.has(n.id)&&!clearedSecrets.has(n.id);
   state.textContent=pending?'توکن جدید در انتظار ذخیره است.':configured?'توکن قبلاً ثبت شده و محفوظ است.':'توکنی برای این گره ثبت نشده است.';
   state.classList.toggle('ok',pending||configured);
   $('clear-secret').disabled=readonly||(!pending&&!configured);
 };
 authSource?.addEventListener('change',authFields);
 authHeader?.addEventListener('change',authFields);
 if(secretInput){
   secretInput.disabled=readonly;
   secretInput.addEventListener('input',()=>{
     if(secretInput.value){pendingSecrets.set(n.id,secretInput.value);clearedSecrets.delete(n.id)}
     else pendingSecrets.delete(n.id);
     dirty=true;refreshSecret();
   });
 }
 $('clear-secret')?.addEventListener('click',()=>{
   if(readonly)return;
   if(!confirm('توکن ذخیره‌شده این گره حذف شود؟ بعد از ذخیره تا ثبت توکن جدید امکان فعال‌سازی جرنی وجود ندارد.'))return;
   pendingSecrets.delete(n.id);clearedSecrets.add(n.id);
   if(secretInput)secretInput.value='';dirty=true;refreshSecret();
 });
 authFields();refreshSecret();
}
$('preview-node')?.addEventListener('click',()=>showMessagePreview(n));
box.querySelectorAll('[data-day]').forEach(el=>{el.disabled=readonly;el.addEventListener('change',()=>{c.days=[...box.querySelectorAll('[data-day]:checked')].map(x=>Number(x.dataset.day));dirty=true;redraw()})});
$('delete-node')?.addEventListener('click',()=>{if(!confirm('گره و اتصالات آن حذف شود؟'))return;graph.nodes=graph.nodes.filter(x=>x.id!==id);pendingSecrets.delete(id);clearedSecrets.delete(id);configuredSecrets.delete(id);graph.edges=graph.edges.filter(x=>x.source!==id&&x.target!==id);dirty=true;selectedId=null;redraw();box.innerHTML=''});}
// Previews are read-only, render unsaved inspector values and NEVER call any delivery endpoint.
// Placeholder values are fictional. Real attributes are substituted only by backend dispatchers.
const sampleAttributes={firstName:'کاربر',lastName:'نمونه',phone:'09120000000',email:'sample@example.com',userId:'sample-user-id'};
const sampleText=value=>String(value??'').replace(/\{\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}\}/g,(_,key)=>
 Object.hasOwn(sampleAttributes,key)?sampleAttributes[key]:`[${key}]`);
let previewReturnFocus=null;
function previewText(tag,className,value){
 const el=document.createElement(tag);if(className)el.className=className;el.textContent=value==null?'':String(value);return el;
}
function previewDetail(label,value){
 const wrap=document.createElement('div');wrap.className='jb-preview-detail';
 wrap.append(previewText('span','jb-preview-detail-label',label),previewText('span','jb-preview-detail-value',value));return wrap;
}
function showMessagePreview(node){
 const overlay=$('jb-preview-backdrop'),area=$('jb-preview-content');if(!overlay||!area)return;
 const c=node.config||{};area.replaceChildren();
 const mode=node.type;
 const name=mode==='sms'?'پیش‌نمایش پیامک':mode==='email'?'پیش‌نمایش ایمیل':'پیش‌نمایش وب‌پوش نوتیفیکیشن';
 $('jb-preview-heading').textContent=name;
 $('jb-preview-description').textContent='فقط نمایش نمونه · اطلاعات واقعی کاربران خوانده نمی‌شود و هیچ پیامی ارسال نخواهد شد.';
 if(mode==='sms'){
   const message=(c.delivery_mode||'legacy')==='managed'?sampleText(c.message||''): 'ارسال با قالب قدیمی: '+(c.template||'تنظیم نشده');
   const phone=previewText('div','jb-preview-phone');
   phone.append(previewText('div','jb-preview-phone-header','پیامک · فرستنده نمونه'),previewText('div','jb-preview-chat-bubble',message||'متن پیامک را وارد کنید.'));
   area.append(phone,previewDetail('تعداد نویسه‌های متن',new Intl.NumberFormat('fa-IR').format(message.length)),previewDetail('گیرنده',`مقدار نمونه از Attribute: ${c.receptor_attribute||'phone'}`));
 } else if(mode==='email'){
   const shell=document.createElement('div');shell.className='jb-preview-mail';
   shell.append(previewDetail('از', (ui.emailProviders||[]).find(p=>String(p.id)===String(c.provider_id))?.name||'حساب SMTP پیش‌فرض'),
     previewDetail('به', `${c.recipient_attribute||'email'} (مقدار نمونه)`),
     previewDetail('موضوع',sampleText(c.subject||'بدون موضوع')));
   if((c.format||'text')==='html'){
     const frame=document.createElement('iframe');frame.className='jb-preview-mail-frame';
     frame.setAttribute('title','پیش‌نمایش ایمن HTML ایمیل');frame.setAttribute('sandbox','');
     frame.setAttribute('referrerpolicy','no-referrer');
     // No scripts, remote images, external CSS, forms or network calls from user HTML.
     // Browser sandbox also prevents navigating the parent window.
     const csp=`default-src 'none'; script-src 'none'; style-src 'unsafe-inline'; img-src data: blob:; font-src 'none'; connect-src 'none'; form-action 'none'; base-uri 'none'`;
     const css='body{margin:0;padding:20px;overflow-wrap:anywhere;font:15px/1.9 Arial,Tahoma,sans-serif;color:#26354b;background:#fff}';
     frame.srcdoc=`<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${csp}"><style>${css}</style></head><body>${sampleText(c.body||'<p>متن ایمیل را وارد کنید.</p>')}</body></html>`;
     shell.append(frame,previewText('p','jb-preview-note','برای امنیت، تصاویر خارجی و اسکریپت‌ها در پیش‌نمایش HTML بارگذاری نمی‌شوند.'));
   } else {
     shell.append(previewText('div','jb-preview-mail-text',sampleText(c.body||'متن ایمیل را وارد کنید.')));
   }
   area.append(shell);
 } else if(mode==='webpush'){
   const push=document.createElement('article');push.className='jb-preview-push';
   const logo=previewText('div','jb-preview-push-icon','🔔');
   const body=document.createElement('div');body.className='jb-preview-push-main';
   body.append(previewText('div','jb-preview-push-app','اعلان وب‌سایت · پیش‌نمایش'),
     previewText('h3','jb-preview-push-title',sampleText(c.title||'عنوان نوتیفیکیشن')),
     previewText('p','jb-preview-push-text',sampleText(c.body||'متن نوتیفیکیشن')));
   push.append(logo,body);area.append(push);
   if(c.button_title){const btn=previewText('div','jb-preview-push-action',sampleText(c.button_title));area.append(btn)}
   area.append(previewDetail('مقصد کلیک',c.url||'مشخص نشده'),previewDetail('زمان اعتبار',`${c.ttl??24} ساعت`));
 }
 previewReturnFocus=document.activeElement;
 overlay.hidden=false;document.body.classList.add('jb-preview-open');$('jb-preview-close').focus();
}
function hideMessagePreview(){
 const overlay=$('jb-preview-backdrop');if(!overlay||overlay.hidden)return;
 overlay.hidden=true;document.body.classList.remove('jb-preview-open');
 $('jb-preview-content').replaceChildren();
 if(previewReturnFocus?.isConnected)previewReturnFocus.focus();previewReturnFocus=null;
}
$('jb-preview-close')?.addEventListener('click',hideMessagePreview);
$('jb-preview-done')?.addEventListener('click',hideMessagePreview);
$('jb-preview-backdrop')?.addEventListener('click',e=>{if(e.target===$('jb-preview-backdrop'))hideMessagePreview()});
document.addEventListener('keydown',e=>{
 const overlay=$('jb-preview-backdrop');if(!overlay||overlay.hidden)return;
 if(e.key==='Escape'){e.preventDefault();hideMessagePreview();return}
 if(e.key==='Tab'){
  const focusables=[...overlay.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),[tabindex="0"]')].filter(x=>x.getClientRects().length);
  if(!focusables.length)return;
  const first=focusables[0],last=focusables.at(-1);
  if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}
  else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}
 }
});
async function call(method,url,body){const response=await fetch(url,{method,credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(body)});const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(Object.values(data.errors||{})[0]?.[0]||data.message||'HTTP '+response.status);return data}
$('save')?.addEventListener('click',async()=>{
 if(readonly)return;
 const btn=$('save');btn.disabled=true;
 try{
   const title=$('edit-title').value.trim();if(!title)throw new Error('نام جرنی را وارد کنید.');
   // Save nodes first so the server can verify the ownership/type of each node.
   await call('PUT',ui.saveUrl,{title,graph});
   const apiIds=new Set(graph.nodes.filter(n=>n.type==='api'&&n.config?.token_source==='secret').map(n=>n.id));
   for(const [nodeId,token] of [...pendingSecrets]){
     if(!apiIds.has(nodeId)){pendingSecrets.delete(nodeId);continue}
     await call('PUT',ui.tokenUrlPattern.replace('__NODE__',encodeURIComponent(nodeId)),{token});
     pendingSecrets.delete(nodeId);clearedSecrets.delete(nodeId);configuredSecrets.add(nodeId);
   }
   for(const nodeId of [...clearedSecrets]){
     if(apiIds.has(nodeId)) await call('PUT',ui.tokenUrlPattern.replace('__NODE__',encodeURIComponent(nodeId)),{clear:true});
     clearedSecrets.delete(nodeId);configuredSecrets.delete(nodeId);
   }
   for(const nodeId of [...configuredSecrets])if(!apiIds.has(nodeId))configuredSecrets.delete(nodeId);
   dirty=false;
   $('flow-title').textContent=title;
   const secretInput=$('cfg-secret');if(secretInput)secretInput.value='';
   if(selectedId){select(selectedId)}
   notify('طرح و تنظیمات توکن با موفقیت ذخیره شد.');
 }catch(e){dirty=true;notify('ذخیره کامل نشد: '+e.message,true)}
 finally{btn.disabled=false}
});
$('edit-title')?.addEventListener('input',()=>dirty=true);
$('refresh')?.addEventListener('click',()=>location.reload());
$('jb-zoom-in')?.addEventListener('click',()=>setZoom(zoom*1.25));
$('jb-zoom-out')?.addEventListener('click',()=>setZoom(zoom/1.25));
$('jb-fit-all')?.addEventListener('click',fitNodes);
const editorLayout=document.querySelector('.editor-layout');
const fullscreenBtn=$('jb-expand-editor');
function toggleEditor(expand){
  editorLayout?.classList.toggle('jb-editor-expanded',expand);
  document.body.classList.toggle('jb-editor-fullscreen',expand);
  if(fullscreenBtn){fullscreenBtn.setAttribute('aria-pressed',expand?'true':'false');
    fullscreenBtn.textContent=expand?'بازگشت به حالت عادی':'بوم تمام‌صفحه';}
  requestAnimationFrame(()=>{sizeBoard();});
}
fullscreenBtn?.addEventListener('click',()=>toggleEditor(!editorLayout.classList.contains('jb-editor-expanded')));
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'&&editorLayout?.classList.contains('jb-editor-expanded')&&!document.body.classList.contains('jb-preview-open'))toggleEditor(false);
});
window.addEventListener('resize',()=>{sizeBoard();scheduleMinimap()});

document.querySelectorAll('.palette-entry').forEach(item=>{item.addEventListener('dragstart',e=>{e.dataTransfer.setData('text/x-jb-node',item.dataset.type);e.dataTransfer.effectAllowed='copy'});item.addEventListener('click',()=>{const v=$('viewport');addNode(item.dataset.type,(v.scrollLeft+v.clientWidth/2)/zoom-originX,(v.scrollTop+v.clientHeight/2)/zoom-originY)})});
$('viewport').addEventListener('dragover',e=>e.preventDefault());$('viewport').addEventListener('drop',e=>{e.preventDefault();const type=e.dataTransfer.getData('text/x-jb-node');if(!Object.hasOwn(ports,type))return;const b=$('board').getBoundingClientRect();addNode(type,(e.clientX-b.left)/zoom-originX,(e.clientY-b.top)/zoom-originY)});
// Mouse wheel zooms around the cursor. Shift+wheel pans horizontally;
// Alt+wheel pans vertically; trackpad two-axis gestures continue to pan.
const canvas=$('viewport');
canvas.classList.add('jb-pan-ready');
canvas.addEventListener('wheel',e=>{
  e.preventDefault();
  const unit=e.deltaMode===1?18:(e.deltaMode===2?canvas.clientHeight:1);
  if(e.shiftKey){canvas.scrollLeft+=(e.deltaX+e.deltaY)*unit;return;}
  if(e.altKey){canvas.scrollTop+=e.deltaY*unit;canvas.scrollLeft+=e.deltaX*unit;return;}
  if(!e.ctrlKey&&!e.metaKey&&Math.abs(e.deltaX)>2){
    canvas.scrollLeft+=e.deltaX*unit;canvas.scrollTop+=e.deltaY*unit;return;
  }
  const factor=Math.exp(-e.deltaY*unit*.0018);
  setZoom(zoom*factor,{clientX:e.clientX,clientY:e.clientY});
},{passive:false});
let pan=null;
canvas.addEventListener('pointerdown', e => {
  if(e.button!==0 && e.button!==1) return;
  if(e.button===0 && e.target.closest('.node,.socket,.wire')) return;
  if(e.pointerType==='touch') return; // keep native touch scrolling
  pan={id:e.pointerId,x:e.clientX,y:e.clientY,left:canvas.scrollLeft,top:canvas.scrollTop};
  canvas.setPointerCapture(e.pointerId);
  canvas.classList.add('jb-panning');
  e.preventDefault();
});
canvas.addEventListener('pointermove', e => {
  if(!pan || e.pointerId!==pan.id) return;
  canvas.scrollLeft=pan.left+pan.x-e.clientX;
  canvas.scrollTop=pan.top+pan.y-e.clientY;
});
const finishPan=e=>{if(pan && e.pointerId===pan.id){pan=null;canvas.classList.remove('jb-panning')}};
canvas.addEventListener('pointerup',finishPan);
canvas.addEventListener('pointercancel',finishPan);
canvas.addEventListener('lostpointercapture',finishPan);
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue=''}});
redraw();fitNodes();
})();
