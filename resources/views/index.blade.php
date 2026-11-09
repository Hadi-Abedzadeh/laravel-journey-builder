<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>مدیریت جرنی‌ها</title><link rel="stylesheet" href="{{ asset('vendor/journey-builder/style.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/journey-builder-pro.css') }}?v=3.4.4">
</head><body>
@include('journey-builder::partials.nav', ['active' => 'journeys'])
<main class="wrap"><div class="heading"><div><h1>مدیریت جرنی‌ها</h1><div class="subtle">جرنی‌ها مستقل هستند؛ فعال/غیرفعال کردن یک جرنی، اجرای جرنی‌های دیگر را تغییر نمی‌دهد.</div></div><div class="pill">تعداد جرنی‌ها: {{ number_format($flows->total()) }}</div></div>
<div class="card"><h2 class="section-title">ساخت جرنی جدید</h2><div class="row"><input id="new-title" style="flex:1;min-width:250px" maxlength="200" placeholder="عنوان فارسی یا دلخواه جرنی"><button id="new-btn" class="btn primary">+ ایجاد و ورود به ویرایش</button></div><p class="subtle">فقط نام نمایشی جرنی را وارد کنید. شناسه‌های سیستمی در سرور ساخته و نگهداری می‌شوند.</p></div>
<div class="card"><div class="heading"><h2 class="section-title">{{ $trash ? 'سطل جرنی‌های حذف‌شده' : 'لیست جرنی‌ها و کنترل اجرا' }}</h2><a class="btn" href="{{ $trash ? route('jb.index') : route('jb.index', ['trash'=>1]) }}">{{ $trash ? 'بازگشت به جرنی‌ها' : 'جرنی‌های حذف‌شده' }}</a></div><div class="table-scroll"><table class="table"><thead><tr><th>نام جرنی</th><th>وضعیت</th><th>کاربران</th><th>در حال انتظار</th><th>خروج</th><th>مدیریت</th></tr></thead><tbody>
@if(count($flows) > 0)
@foreach($flows as $flow)

@php($s=$stats[$flow->id]??[])
@php($rc=$s['runs']??[])
@php($sm=$s['sms']??[])
@php($ac=$s['api']??[])
<tr><td><strong>{{ $flow->title }}</strong><div class="subtle">نسخه {{ $flow->revision }}</div></td><td><span class="badge {{ $flow->status }}">{{ ['active'=>'فعال','paused'=>'غیرفعال','draft'=>'پیش‌نویس'][$flow->status]??$flow->status }}</span></td><td>{{ number_format(array_sum($rc)) }}</td><td>{{ number_format($rc['waiting']??0) }}</td><td>{{ number_format($rc['exited']??0) }}</td><td><div class="actions"><a class="btn" href="{{ route('jb.show',$flow->id) }}">جزئیات و گزارش</a>@if($trash)<button type="button" class="btn primary restore-flow" data-id="{{ $flow->id }}">بازیابی جرنی</button>@else
<a class="btn" href="{{ route('jb.builder',$flow->id) }}">ویرایش</a><button class="btn {{ $flow->status==='active'?'danger':'primary' }} toggle" data-id="{{ $flow->id }}" data-status="{{ $flow->status==='active'?'paused':'active' }}">{{ $flow->status==='active'?'غیرفعال‌کردن':'فعال‌کردن' }}</button>@if($flow->status!=='active')<button type="button" class="btn danger delete-flow" data-id="{{ $flow->id }}" data-title="{{ $flow->title }}">حذف</button>@endif
@endif
</div></td></tr>
@endforeach
@else
<tr><td colspan="6" style="text-align:center;padding:35px">هنوز جرنی ساخته نشده است؛ از فرم بالا شروع کنید.</td></tr>
@endif
</tbody></table></div><div class="pager"><span>صفحه {{ $flows->currentPage() }} از {{ $flows->lastPage() }}</span><span>@if($flows->previousPageUrl())<a href="{{ $flows->previousPageUrl() }}">قبلی</a>@endif　@if($flows->nextPageUrl())<a href="{{ $flows->nextPageUrl() }}">بعدی</a>@endif</span></div></div>
<div class="muted-box">حذف جرنی در این نسخه «حذف منطقی» است: پردازش‌های جدید متوقف می‌شوند، اطلاعات برای گزارش و امکان بازیابی حفظ می‌شود. برای حذف جرنی فعال ابتدا آن را غیرفعال کنید. فعالسازی جرنی ممکن است اجرای پیامک یا درخواست API را آغاز کند. تا زمانی که آدرس و قرارداد API را بررسی نکرده‌اید، جرنی را در پیش‌نویس نگه دارید. غیرفعال‌کردن از شروع پردازش‌های جدید جلوگیری می‌کند؛ درخواست HTTP آغازشده را نمی‌تواند بازگرداند.</div></main><div id="toast" class="toast" role="status"></div>
<script>
const csrf=document.querySelector('meta[name="csrf-token"]').content;
function note(t,err=false){const el=document.getElementById('toast');el.textContent=t;el.style.background=err?'#b42d45':'#263b61';el.style.display='block';setTimeout(()=>el.style.display='none',5000)}
async function send(url,body,method='POST'){const response=await fetch(url,{method,credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body)});const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(Object.values(data.errors||{})[0]?.[0]||data.message||('HTTP '+response.status));return data}
document.getElementById('new-btn').onclick=async()=>{const title=document.getElementById('new-title').value.trim();if(!title)return note('نام نمایشی جرنی را وارد کنید.',true);try{const r=await send(@json(route('jb.create')),{title});location.href=r.url}catch(e){note(e.message,true)}};
const deleteUrlPattern=@json(route('jb.delete',['id'=>'__FLOW__']));
const restoreUrlPattern=@json(route('jb.restore',['id'=>'__FLOW__']));
document.querySelectorAll('.delete-flow').forEach(btn=>btn.onclick=async()=>{
  if(prompt('برای حذف منطقی جرنی «'+btn.dataset.title+'» عبارت DELETE را وارد کنید.\nتاریخچه و پیام‌ها حفظ می‌شوند و جرنی از فهرست فعال خارج می‌شود.') !== 'DELETE') return;
  btn.disabled=true;
  try { await send(deleteUrlPattern.replace('__FLOW__',encodeURIComponent(btn.dataset.id)),{confirmation:'DELETE'},'DELETE'); location.reload(); }
  catch(e){btn.disabled=false; note(e.message,true);}
});
document.querySelectorAll('.restore-flow').forEach(btn=>btn.onclick=async()=>{
 if(!confirm('جرنی بازیابی شود؟ پس از بازیابی غیرفعال خواهد ماند.'))return;
 btn.disabled=true;
 try{await send(restoreUrlPattern.replace('__FLOW__',encodeURIComponent(btn.dataset.id)),{});location.reload()}
 catch(e){btn.disabled=false;note(e.message,true)}
});
document.querySelectorAll('.toggle').forEach(btn=>btn.onclick=async()=>{const next=btn.dataset.status;if(next==='active'&&!confirm('جرنی فعال شود؟ در زمان رسیدن به مراحل، پیامک و API واقعی ممکن است ارسال شود.'))return;if(next==='paused'&&!confirm('این جرنی غیرفعال شود؟ سایر جرنی‌ها فعال می‌مانند.'))return;btn.disabled=true;try{await send(@json(url('/journeys/flows')).replace(/\/$/,'')+'/'+btn.dataset.id+'/status',{status:next});location.reload()}catch(e){btn.disabled=false;note(e.message,true)}});
</script>@include('journey-builder::partials.footer')
</body></html>
