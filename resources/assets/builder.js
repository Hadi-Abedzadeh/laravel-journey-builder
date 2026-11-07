(()=>{'use strict';
const ui=window.JB_UI, graph=ui.graph, readonly=ui.readonly;
const ports={trigger:['next'],condition:['yes','no'],attribute:['yes','no'],split:['a','b'],slot:['next'],delay:['next'],wait_event:['event','timeout'],sms:['success','failure'],api:['success','failure'],exit:[]};
const labels={trigger:'ورود کاربران',condition:'شرط رویداد',attribute:'شرط ویژگی',split:'تقسیم مسیر',slot:'بازه زمانی',delay:'تأخیر',wait_event:'انتظار رویداد',sms:'ارسال پیامک',api:'درخواست API',exit:'خروج از جرنی'};
const icons={trigger:'▶',condition:'◇',attribute:'◈',split:'⑂',slot:'◷',delay:'◴',wait_event:'⌛',sms:'✉',api:'↗',exit:'■'};
const colors={trigger:'#3182ee',condition:'#e0a434',attribute:'#e7a238',split:'#9d6ddd',slot:'#ed9563',delay:'#eb9463',wait_event:'#cf6d75',sms:'#14a985',api:'#3677d6',exit:'#8795a8'};
const portLabels={next:'بعدی',yes:'بله',no:'خیر',a:'مسیر A',b:'مسیر B',event:'رویداد',timeout:'زمان تمام شد',success:'موفق',failure:'ناموفق'};
const defaults={trigger:{section:'attributes',sign:'',key:'',operator:'exists',value:''},condition:{event:'',sign:'',scope:'ever'},attribute:{key:'',sign:'',operator:'exists',value:''},split:{percent:50},slot:{days:[0],from:'10:00',to:'15:00'},delay:{hours:24},wait_event:{event:'',sign:'',hours:24},sms:{template:'',message:''},api:{url:'',method:'POST',token_source:'none',token_alias:'',token_header:'bearer',token_custom_header:'X-Access-Token',body:'{"userId":"{{userId}}","journey":"{{flowCode}}"}'},exit:{}};
const days=['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
const opNames={exists:'وجود داشته باشد',not_exists:'وجود نداشته باشد',equals:'برابر باشد',not_equals:'برابر نباشد',starts_with:'با مقدار شروع شود',not_starts_with:'با مقدار شروع نشود'};
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const uid=()=> 'n'+Math.random().toString(36).slice(2,11);
let selectedId=null,source=null,dirty=false;
const configuredSecrets=new Set(ui.secretNodeIds||[]), pendingSecrets=new Map(), clearedSecrets=new Set();
function notify(text,error=false){const t=$('toast');t.textContent=text;t.style.background=error?'#ae3049':'#233d65';t.style.display='block';clearTimeout(notify.timer);notify.timer=setTimeout(()=>t.style.display='none',4800)}
function describe(n){const c=n.config||{};switch(n.type){case'trigger':return `${c.section||'attributes'} / ${c.sign||'*'} / ${c.key||'کلید تعیین نشده'} · ${opNames[c.operator]||''}`;case'condition':return `${c.event||'رویداد تعیین نشده'} · ${c.scope==='after_enroll'?'بعد از ورود':'کل سوابق'}`;case'attribute':return `key: ${c.key||'تعیین نشده'} · ${opNames[c.operator]||''}`;case'split':return `${c.percent??50}٪ مسیر A / ${100-(Number(c.percent)||0)}٪ مسیر B`;case'slot':return `${(c.days||[]).map(d=>days[d]).join('، ')} · ${c.from||''} تا ${c.to||''}`;case'delay':return `${c.hours??24} ساعت`;case'wait_event':return `${c.event||'رویداد تعیین نشده'} · ${c.hours??24} ساعت`;case'sms':return `قالب: ${c.template||'تعیین نشده'}`;case'api':return `${c.method||'POST'} · ${c.url||'آدرس تعیین نشده'}`;case'exit':return 'پایان این مسیر'}}
function redraw(){const root=$('nodes');root.innerHTML='';for(const n of graph.nodes){n.x=Number(n.x)||0;n.y=Number(n.y)||0;const el=document.createElement('div');el.id='node-'+n.id;el.className='node'+(n.id===selectedId?' selected':'');el.style.left=n.x+'px';el.style.top=n.y+'px';el.style.setProperty('--accent',colors[n.type]||'#678');const outs=(ports[n.type]||[]).map((port,i)=>`<button class="socket output ${source?.id===n.id&&source.port===port?'highlight':''}" type="button" data-port="${port}" title="${esc(portLabels[port])}" style="top:${45+i*27}px"><span class="socket-label">${esc(portLabels[port])}</span></button>`).join('');el.innerHTML=`<div class="node-head"><span class="iconbox">${icons[n.type]||'■'}</span><strong>${labels[n.type]||n.type}</strong></div><div class="node-body">${esc(describe(n))}</div>${n.type!=='trigger'?'<button type="button" class="socket input" title="ورودی گره"></button>':''}${outs}`;el.addEventListener('click',()=>select(n.id));el.querySelector('.node-head').addEventListener('pointerdown',e=>moveNode(e,n,el));el.querySelector('.input')?.addEventListener('click',e=>{e.stopPropagation();connect(n.id)});el.querySelectorAll('.output').forEach(btn=>btn.addEventListener('click',e=>{e.stopPropagation();if(readonly)return;source={id:n.id,port:btn.dataset.port};redraw();notify('حالا ورودی گره مقصد را بزنید.')}));root.appendChild(el)}drawWires()}
function drawWires(){const svg=$('wires');svg.innerHTML='';const ns='http://www.w3.org/2000/svg';const defs=document.createElementNS(ns,'defs'),m=document.createElementNS(ns,'marker');m.setAttribute('id','jb-arrow');[['markerWidth','9'],['markerHeight','9'],['refX','8'],['refY','3'],['orient','auto']].forEach(([k,v])=>m.setAttribute(k,v));const tri=document.createElementNS(ns,'path');tri.setAttribute('d','M0 0 L8 3 L0 6 z');tri.setAttribute('fill','#94a4bd');m.appendChild(tri);defs.appendChild(m);svg.appendChild(defs);for(const e of graph.edges){const a=graph.nodes.find(n=>n.id===e.source),b=graph.nodes.find(n=>n.id===e.target);if(!a||!b)continue;const i=(ports[a.type]||[]).indexOf(e.port);if(i<0)continue;const sx=a.x+238,sy=a.y+54+i*27,tx=b.x,ty=b.y+64,dx=Math.max(75,Math.abs(tx-sx)*.48);const p=document.createElementNS(ns,'path');p.setAttribute('class','wire');p.setAttribute('d',`M${sx} ${sy} C${sx+dx} ${sy},${tx-dx} ${ty},${tx} ${ty}`);p.setAttribute('marker-end','url(#jb-arrow)');p.addEventListener('click',()=>{if(!readonly&&confirm('این اتصال حذف شود؟')){graph.edges=graph.edges.filter(z=>z!==e);dirty=true;redraw()}});svg.appendChild(p)}}
function moveNode(e,n,el){if(readonly||e.button!==0)return;e.preventDefault();const sx=e.clientX,sy=e.clientY,ox=n.x,oy=n.y;el.setPointerCapture(e.pointerId);const onmove=ev=>{n.x=Math.max(0,Math.min(2360,Math.round(ox+ev.clientX-sx)));n.y=Math.max(0,Math.min(2080,Math.round(oy+ev.clientY-sy)));el.style.left=n.x+'px';el.style.top=n.y+'px';drawWires()};const onup=()=>{el.removeEventListener('pointermove',onmove);el.removeEventListener('pointerup',onup);dirty=true;select(n.id)};el.addEventListener('pointermove',onmove);el.addEventListener('pointerup',onup)}
function connect(id){if(readonly||!source)return;if(source.id===id)return notify('گره به خودش متصل نمی‌شود.',true);graph.edges=graph.edges.filter(e=>!(e.source===source.id&&e.port===source.port));graph.edges.push({id:uid(),source:source.id,port:source.port,target:id});source=null;dirty=true;redraw()}
function addNode(type,x,y){if(readonly)return notify('طرح در وضعیت فعلی قابل ویرایش نیست.',true);if(type==='trigger'&&graph.nodes.some(n=>n.type==='trigger'))return notify('هر جرنی فقط یک نقطه شروع دارد.',true);const n={id:uid(),type,x:Math.max(0,Math.round(x)),y:Math.max(0,Math.round(y)),config:structuredClone(defaults[type])};graph.nodes.push(n);dirty=true;select(n.id)}
function field(label,id,value,type='text',extra=''){return `<div class="field"><span>${label}</span><input id="${id}" type="${type}" value="${esc(value)}" ${extra}></div>`}
function selectField(label,id,value,choices){return `<div class="field"><span>${label}</span><select id="${id}">${choices.map(([k,v])=>`<option value="${esc(k)}" ${k===value?'selected':''}>${esc(v)}</option>`).join('')}</select></div>`}
function textField(label,id,value,rows=4){return `<div class="field"><span>${label}</span><textarea id="${id}" rows="${rows}">${esc(value)}</textarea></div>`}
const ops=Object.entries(opNames);const eventScope=[['ever','تمام سوابق موجود'],['after_enroll','از زمان ورود به جرنی']];
function select(id){selectedId=id;redraw();const n=graph.nodes.find(n=>n.id===id),box=$('inspector');if(!n){box.innerHTML='<p class="help">گرهی انتخاب نشده است.</p>';return}const c=n.config||{};let html=`<b>${labels[n.type]}</b>${field('شناسه فنی گره','node-id',n.id,'text','readonly')}`;
if(n.type==='trigger')html+=selectField('بخش دیتابیس','cfg-section',c.section||'attributes',[['attributes','ویژگی کاربران (attributes)'],['events','رویدادها (events)']])+field('منبع sign (اختیاری)','cfg-sign',c.sign||'')+field('کلید key','cfg-key',c.key||'')+selectField('فیلتر ورود','cfg-operator',c.operator||'exists',ops)+field('مقدار (در صورت نیاز)','cfg-value',c.value||'')+'<div class="help">هر جرنی مستقل ردیف‌های این منبع را اسکن می‌کند. کلید را پیش از فعالسازی مشخص کنید.</div>';
if(n.type==='condition')html+=field('نام رویداد (name)','cfg-event',c.event||'')+field('sign اختیاری','cfg-sign',c.sign||'')+selectField('محدوده زمانی','cfg-scope',c.scope||'ever',eventScope);
if(n.type==='attribute')html+=field('key','cfg-key',c.key||'')+field('sign اختیاری','cfg-sign',c.sign||'')+selectField('عملگر','cfg-operator',c.operator||'exists',ops)+field('مقدار','cfg-value',c.value||'');
if(n.type==='split')html+=field('درصد تخصیص به مسیر A','cfg-percent',c.percent??50,'number','min="0" max="100"');
if(n.type==='slot')html+=field('از ساعت','cfg-from',c.from||'10:00','time')+field('تا ساعت','cfg-to',c.to||'15:00','time')+`<div class="help">روزهای مجاز به وقت جرنی</div><div class="days">${days.map((day,i)=>`<label><input type="checkbox" data-day="${i}" ${(c.days||[]).includes(i)?'checked':''}>${day}</label>`).join('')}</div>`;
if(n.type==='delay'||n.type==='wait_event')html+=field('مدت انتظار (ساعت)','cfg-hours',c.hours??24,'number','min="1" max="2160"');
if(n.type==='wait_event')html+=field('نام رویداد','cfg-event',c.event||'')+field('sign اختیاری','cfg-sign',c.sign||'')+'<div class="help">بعد از مشاهده رویداد، از خروجی «رویداد» عبور می‌کند. در پایان زمان، مسیر «اتمام زمان».</div>';
if(n.type==='sms')html+=field('کد Template','cfg-template',c.template||'')+textField('متن اختیاری','cfg-message',c.message||'')+'<div class="help">قالب پیامک باید در سرویس ارسال شناخته شده باشد.</div>';
if(n.type==='api'){
 const authSource=c.token_source||(c.token_alias?'alias':'none');
 html+=field('آدرس HTTPS API','cfg-url',c.url||'','url','dir="ltr"')
  +selectField('متد HTTP','cfg-method',c.method||'POST',[['POST','POST'],['PUT','PUT'],['PATCH','PATCH']])
  +selectField('احراز هویت درخواست','cfg-token_source',authSource,[['none','بدون توکن'],['alias','توکن از تنظیمات سرور (ENV)'],['secret','توکن اختصاصی برای همین گره']])
  +'<div id="api-alias-settings">'+selectField('نام توکن ذخیره‌شده در سرور','cfg-token_alias',c.token_alias||'',[['','انتخاب کنید'],...(ui.aliases||[]).map(x=>[x,x])])+'</div>'
  +'<div id="api-secret-settings"><div class="field"><span>توکن اختصاصی (فقط هنگام ثبت/تعویض)</span><input id="cfg-secret" type="password" autocomplete="new-password" maxlength="4096" dir="ltr" placeholder="توکن جدید را اینجا وارد کنید"></div><div class="secret-state" id="secret-state"></div><div class="secret-tools"><button type="button" class="btn danger" id="clear-secret">حذف توکن ثبت‌شده</button></div><div class="help">توکن در جدول جدا و با APP_KEY رمزگذاری می‌شود. مقدار قبلی دوباره نمایش داده نمی‌شود؛ برای تغییر، توکن جدید وارد کنید و «ذخیره تغییرات» را بزنید.</div></div>'
  +'<div id="api-header-settings">'
  +selectField('محل قرارگیری توکن در درخواست','cfg-token_header',c.token_header||'bearer',[['bearer','Authorization: Bearer'],['x-api-key','X-API-Key'],['custom','هدر سفارشی X-...']])
  +'<div id="api-custom-header-settings">'+field('نام هدر سفارشی','cfg-token_custom_header',c.token_custom_header||'X-Access-Token','text','dir="ltr"')+'</div></div>'
  +textField('بدنه JSON (متغیرها قابل استفاده‌اند)','cfg-body',c.body||'{}',8)
  +'<div class="help">متغیرها: {{userId}}, {{flowId}}, {{flowCode}}, {{runId}}, {{nodeId}}. دامنه مقصد باید در JOURNEY_BUILDER_API_ALLOWED_HOSTS مجاز شده باشد. پاسخ 2xx موفق است، نه تایید انجام فرآیند مقصد.</div>';
}
if(n.type==='exit')html+='<div class="muted-box">هنگام رسیدن کاربر به این مرحله، اجرای او پایان می‌یابد.</div>';
if(!readonly)html+='<button class="btn danger" id="delete-node" style="width:100%;margin-top:15px">حذف گره</button>';
box.innerHTML=html;
const map={'cfg-section':'section','cfg-sign':'sign','cfg-key':'key','cfg-operator':'operator','cfg-value':'value','cfg-event':'event','cfg-scope':'scope','cfg-percent':'percent','cfg-from':'from','cfg-to':'to','cfg-hours':'hours','cfg-template':'template','cfg-message':'message','cfg-url':'url','cfg-method':'method','cfg-token_alias':'token_alias','cfg-token_source':'token_source','cfg-token_header':'token_header','cfg-token_custom_header':'token_custom_header','cfg-body':'body'};
for(const [input,key] of Object.entries(map)){const el=$(input);if(!el)continue;el.disabled=readonly;el.addEventListener('input',()=>{c[key]=['hours','percent'].includes(key)?Number(el.value):el.value;dirty=true;const nbody=$('node-'+n.id)?.querySelector('.node-body');if(nbody)nbody.textContent=describe(n)})}
if(n.type==='api'){
 const authSource=$('cfg-token_source'), authHeader=$('cfg-token_header'), secretInput=$('cfg-secret');
 const authFields=()=>{
   const mode=authSource?.value||'none';
   $('api-alias-settings')?.classList.toggle('api-hidden',mode!=='alias');
   $('api-secret-settings')?.classList.toggle('api-hidden',mode!=='secret');
   $('api-header-settings')?.classList.toggle('api-hidden',mode==='none');
   $('api-custom-header-settings')?.classList.toggle('api-hidden',mode==='none'||authHeader?.value!=='custom');
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
box.querySelectorAll('[data-day]').forEach(el=>{el.disabled=readonly;el.addEventListener('change',()=>{c.days=[...box.querySelectorAll('[data-day]:checked')].map(x=>Number(x.dataset.day));dirty=true;redraw()})});
$('delete-node')?.addEventListener('click',()=>{if(!confirm('گره و اتصالات آن حذف شود؟'))return;graph.nodes=graph.nodes.filter(x=>x.id!==id);pendingSecrets.delete(id);clearedSecrets.delete(id);configuredSecrets.delete(id);graph.edges=graph.edges.filter(x=>x.source!==id&&x.target!==id);dirty=true;selectedId=null;redraw();box.innerHTML='<p class="help">گرهی انتخاب نشده است.</p>'});}
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
document.querySelectorAll('.palette-entry').forEach(item=>{item.addEventListener('dragstart',e=>{e.dataTransfer.setData('text/x-jb-node',item.dataset.type);e.dataTransfer.effectAllowed='copy'});item.addEventListener('click',()=>{const v=$('viewport');addNode(item.dataset.type,v.scrollLeft+300,v.scrollTop+220)})});
$('viewport').addEventListener('dragover',e=>e.preventDefault());$('viewport').addEventListener('drop',e=>{e.preventDefault();const type=e.dataTransfer.getData('text/x-jb-node');if(!Object.hasOwn(ports,type))return;const b=$('board').getBoundingClientRect();addNode(type,e.clientX-b.left,e.clientY-b.top)});
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue=''}});
redraw();$('viewport').scrollLeft=100;$('viewport').scrollTop=0;
})();
