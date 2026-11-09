(()=>{'use strict';
const ui=window.JB_UI, graph=ui.graph, readonly=ui.readonly;
const ports={trigger:['next'],condition:['yes','no'],attribute:['yes','no'],split:['a','b'],slot:['next'],delay:['next'],wait_event:['event','timeout'],sms:['success','failure'],email:['success','failure'],webpush:['success','failure'],api:['success','failure'],exit:[]};
const labels={trigger:'ورود کاربران',condition:'شرط رویداد',attribute:'شرط ویژگی',split:'تقسیم مسیر',slot:'بازه زمانی',delay:'تأخیر',wait_event:'انتظار رویداد',sms:'ارسال پیامک',email:'ارسال ایمیل',webpush:'وب‌پوش نجوا',api:'درخواست API',exit:'خروج از جرنی'};
const icons={trigger:'▶',condition:'◇',attribute:'◈',split:'⑂',slot:'◷',delay:'◴',wait_event:'⌛',sms:'✉',email:'✉',webpush:'◉',api:'↗',exit:'■'};
const colors={trigger:'#3182ee',condition:'#e0a434',attribute:'#e7a238',split:'#9d6ddd',slot:'#ed9563',delay:'#eb9463',wait_event:'#cf6d75',sms:'#14a985',email:'#7b61e8',webpush:'#0e9fbd',api:'#3677d6',exit:'#8795a8'};
const portLabels={next:'بعدی',yes:'بله',no:'خیر',a:'مسیر A',b:'مسیر B',event:'رویداد',timeout:'زمان تمام شد',success:'موفق',failure:'ناموفق'};
const defaults={trigger:{section:'attributes',sign:'',key:'',operator:'exists',value:''},condition:{event:'',sign:'',scope:'ever'},attribute:{key:'',sign:'',operator:'exists',value:''},split:{percent:50},slot:{days:[0],from:'10:00',to:'15:00'},delay:{hours:24},wait_event:{event:'',sign:'',hours:24},sms:{delivery_mode:'managed',provider_id:0,template:'',message:'',receptor_attribute:'phone',receptor_sign:''},email:{provider_id:0,recipient_attribute:'email',recipient_sign:'',subject:'',body:'',format:'text'},webpush:{provider_id:0,title:'',body:'',url:'',ttl:24,button_title:'',button_url:''},api:{url:'',method:'POST',token_source:'none',token_header:'bearer',token_custom_header:'X-Access-Token',body:'{"userId":"{{userId}}","flowId":"{{flowId}}"}'},exit:{}};
const days=['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
const opNames={exists:'وجود داشته باشد',not_exists:'وجود نداشته باشد',equals:'برابر باشد',not_equals:'برابر نباشد',starts_with:'با مقدار شروع شود',not_starts_with:'با مقدار شروع نشود'};
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const uid=()=> 'n'+Math.random().toString(36).slice(2,11);
let selectedId=null,source=null,dirty=false;
const configuredSecrets=new Set(ui.secretNodeIds||[]), pendingSecrets=new Map(), clearedSecrets=new Set();
function notify(text,error=false){const t=$('toast');t.textContent=text;t.style.background=error?'#ae3049':'#233d65';t.style.display='block';clearTimeout(notify.timer);notify.timer=setTimeout(()=>t.style.display='none',4800)}
function describe(n){const c=n.config||{};switch(n.type){case'trigger':return `${c.section||'attributes'} / ${c.sign||'*'} / ${c.key||'کلید تعیین نشده'} · ${opNames[c.operator]||''}`;case'condition':return `${c.event||'رویداد تعیین نشده'} · ${c.scope==='after_enroll'?'بعد از ورود':'کل سوابق'}`;case'attribute':return `key: ${c.key||'تعیین نشده'} · ${opNames[c.operator]||''}`;case'split':return `${c.percent??50}٪ مسیر A / ${100-(Number(c.percent)||0)}٪ مسیر B`;case'slot':return `${(c.days||[]).map(d=>days[d]).join('، ')} · ${c.from||''} تا ${c.to||''}`;case'delay':return `${c.hours??24} ساعت`;case'wait_event':return `${c.event||'رویداد تعیین نشده'} · ${c.hours??24} ساعت`;case'sms':return (c.delivery_mode||'legacy')==='managed'?`پیامک متنی · ${c.message?String(c.message).slice(0,38):'متن تعیین نشده'}`:`قالب قدیمی: ${c.template||'تعیین نشده'}`;case'email':return `ایمیل · ${c.subject||'موضوع تعیین نشده'}`;case'webpush':return `نجوا · ${c.title||'عنوان تعیین نشده'}`;case'api':return `${c.method||'POST'} · ${c.url||'آدرس تعیین نشده'}`;case'exit':return 'پایان این مسیر'}}
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
if(n.type==='sms'){
 const mode=c.delivery_mode||'legacy';
 html+=selectField('روش ارسال','cfg-delivery_mode',mode,[['managed','پنل پیامکی مدیریت‌شده (کاوه‌نگار)'],['legacy','اتصال پیامک قدیمی']]);
 html+='<div id="sms-managed-settings">'
   +selectField('پنل پیامک','cfg-provider_id',String(c.provider_id||0),[['0','پنل پیش‌فرض سیستم'],...(ui.smsProviders||[]).map(p=>[String(p.id),p.name])])
   +field('Attribute شماره گیرنده (receptor)','cfg-receptor_attribute',c.receptor_attribute||'phone','text','dir="ltr" maxlength="50"')
   +field('فیلتر sign شماره گیرنده (اختیاری)','cfg-receptor_sign',c.receptor_sign||'','text','dir="ltr" maxlength="30"')
   +textField('متن پیامک (message)','cfg-message',c.message||'',6)
   +'<div class="help">متغیرها: {{firstName}}، {{lastName}}، {{phone}}، {{userId}} یا نام هر Attribute انگلیسی ذخیره‌شده. در زمان ارسال از دیتابیس خوانده می‌شوند. شماره گیرنده از Attribute مشخص‌شده در همین گره خوانده می‌شود؛ تنظیمات پنل فقط sender و API-KEY را نگه می‌دارد.</div>'
   +'<div class="help"><a href="'+esc(ui.smsProvidersUrl||'#')+'" target="_blank" rel="noopener noreferrer">مدیریت پنل‌ها و معرفی پارامترهای message / sender / receptor / API-KEY</a></div>'
   +'</div><div id="sms-legacy-settings">'
   +field('کد Template قدیمی','cfg-template',c.template||'')
   +'<div class="help">فقط برای جرنی‌های قدیمی که از API پیامک JSON استفاده می‌کنند.</div></div>';
}
if(n.type==='email'){
 html+=selectField('حساب SMTP','cfg-provider_id',String(c.provider_id||0),[['0','سرویس پیش‌فرض'],...(ui.emailProviders||[]).map(p=>[String(p.id),p.name])])
 +field('Attribute ایمیل گیرنده','cfg-recipient_attribute',c.recipient_attribute||'email','text','dir="ltr"')
 +field('sign گیرنده (اختیاری)','cfg-recipient_sign',c.recipient_sign||'','text','dir="ltr"')
 +field('موضوع ایمیل','cfg-subject',c.subject||'')
 +selectField('قالب ایمیل','cfg-format',c.format||'text',[['text','متن ساده'],['html','HTML']])
 +textField('متن ایمیل','cfg-body',c.body||'',9)
 +'<div class="help">شخصی‌سازی: {{firstName}}، {{email}} و سایر Attributeها. <a href="'+esc(ui.emailProvidersUrl||'#')+'" target="_blank">مدیریت حساب SMTP</a>. POP3 فقط برای دریافت است.</div>';
}
if(n.type==='webpush'){
 html+=selectField('سرویس نجوا','cfg-provider_id',String(c.provider_id||0),[['0','سرویس پیش‌فرض'],...(ui.pushProviders||[]).map(p=>[String(p.id),p.name])])
 +field('عنوان وب‌پوش','cfg-title',c.title||'')
 +textField('متن وب‌پوش','cfg-body',c.body||'',5)
 +field('لینک کلیک (HTTPS)','cfg-url',c.url||'','url','dir="ltr"')
 +field('متن دکمه اختیاری','cfg-button_title',c.button_title||'')
 +field('لینک دکمه (HTTPS)','cfg-button_url',c.button_url||'','url','dir="ltr"')
 +field('TTL به ساعت','cfg-ttl',c.ttl??24,'number','min="1" max="168"')
 +'<div class="help">فقط برای توکن‌های عضوِ همین کاربر. <a href="'+esc(ui.pushProvidersUrl||'#')+'" target="_blank">مدیریت سرویس نجوا</a>.</div>';
}
if(n.type==='api'){
 const authSource=c.token_source==='secret'?'secret':'none';
 html+=field('آدرس HTTPS API','cfg-url',c.url||'','url','dir="ltr"')
  +selectField('متد HTTP','cfg-method',c.method||'POST',[['POST','POST'],['PUT','PUT'],['PATCH','PATCH']])
  +selectField('احراز هویت درخواست','cfg-token_source',authSource,[['none','بدون توکن'],['secret','توکن اختصاصی برای همین درخواست']])

  +'<div id="api-secret-settings"><div class="field"><span>توکن اختصاصی (فقط هنگام ثبت/تعویض)</span><input id="cfg-secret" type="password" autocomplete="new-password" maxlength="4096" dir="ltr" placeholder="توکن جدید را اینجا وارد کنید"></div><div class="secret-state" id="secret-state"></div><div class="secret-tools"><button type="button" class="btn danger" id="clear-secret">حذف توکن ثبت‌شده</button></div><div class="help">توکن در جدول جدا و با APP_KEY رمزگذاری می‌شود. مقدار قبلی دوباره نمایش داده نمی‌شود؛ برای تغییر، توکن جدید وارد کنید و «ذخیره تغییرات» را بزنید.</div></div>'
  +'<div id="api-header-settings">'
  +selectField('محل قرارگیری توکن در درخواست','cfg-token_header',c.token_header||'bearer',[['bearer','Authorization: Bearer'],['x-api-key','X-API-Key'],['custom','هدر سفارشی X-...']])
  +'<div id="api-custom-header-settings">'+field('نام هدر سفارشی','cfg-token_custom_header',c.token_custom_header||'X-Access-Token','text','dir="ltr"')+'</div></div>'
  +textField('بدنه JSON (متغیرها قابل استفاده‌اند)','cfg-body',c.body||'{}',8)
  +'<div class="help">متغیرها: {{userId}}, {{flowId}}, {{runId}}, {{nodeId}}. هر دامنه عمومی HTTPS بدون فهرست مجاز قابل استفاده است؛ آدرس‌های داخلی و IP خصوصی برای امنیت سرور مسدودند. پاسخ 2xx موفق است، نه تایید انجام فرآیند مقصد.</div>';
}
if(n.type==='exit')html+='<div class="muted-box">هنگام رسیدن کاربر به این مرحله، اجرای او پایان می‌یابد.</div>';
if(!readonly)html+='<button class="btn danger" id="delete-node" style="width:100%;margin-top:15px">حذف گره</button>';
box.innerHTML=html;
const map={'cfg-section':'section','cfg-sign':'sign','cfg-key':'key','cfg-operator':'operator','cfg-value':'value','cfg-event':'event','cfg-scope':'scope','cfg-percent':'percent','cfg-from':'from','cfg-to':'to','cfg-hours':'hours','cfg-template':'template','cfg-message':'message','cfg-receptor_attribute':'receptor_attribute','cfg-receptor_sign':'receptor_sign','cfg-delivery_mode':'delivery_mode','cfg-provider_id':'provider_id','cfg-url':'url','cfg-method':'method','cfg-token_source':'token_source','cfg-token_header':'token_header','cfg-token_custom_header':'token_custom_header','cfg-body':'body','cfg-recipient_attribute':'recipient_attribute','cfg-recipient_sign':'recipient_sign','cfg-subject':'subject','cfg-format':'format','cfg-title':'title','cfg-ttl':'ttl','cfg-button_title':'button_title','cfg-button_url':'button_url'};
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
 const authSource=$('cfg-token_source'), authHeader=$('cfg-token_header'), secretInput=$('cfg-secret');
 const authFields=()=>{
   const mode=authSource?.value||'none';
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
// Four-way canvas movement: normal wheel = vertical, trackpad = both axes,
// Shift+wheel = horizontal, drag an empty area = pan. Never interfere with node drag/ports.
const canvas=$('viewport');
canvas.classList.add('jb-pan-ready');
canvas.addEventListener('wheel', e => {
  if(e.ctrlKey || e.metaKey) return; // leave browser zoom available
  e.preventDefault();
  const unit=e.deltaMode===1?18:(e.deltaMode===2?canvas.clientHeight:1);
  if(e.shiftKey){canvas.scrollLeft+=(e.deltaX+e.deltaY)*unit;}
  else {canvas.scrollLeft+=e.deltaX*unit;canvas.scrollTop+=e.deltaY*unit;}
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
redraw();$('viewport').scrollLeft=100;$('viewport').scrollTop=0;
})();
