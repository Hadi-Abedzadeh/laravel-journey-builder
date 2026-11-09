<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>جزئیات جرنی | {{ $flow->title }}</title><link rel="stylesheet" href="{{ asset('vendor/journey-builder/style.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/journey-builder-pro.css') }}?v=3.4.4">
</head><body>
@include('journey-builder::partials.nav', ['active' => 'journeys'])
<main class="wrap"><div class="heading"><div><a href="{{ route('jb.index') }}">بازگشت به فهرست</a><h1 style="margin-top:12px">{{ $flow->title }}</h1><div class="row"><span class="badge {{ $flow->status }}">{{ ['active'=>'فعال','paused'=>'غیرفعال','draft'=>'پیش‌نویس'][$flow->status]??$flow->status }}</span><span class="subtle">آخرین بروزرسانی: {{ $flow->updated_at }}</span></div></div><a class="btn primary" href="{{ route('jb.builder',$flow->id) }}">ویرایش مراحل · Drag & Drop</a></div>
@php($total=array_sum($runCounts))
<div class="statgrid"><div class="stat"><small>کل کاربران این جرنی</small><strong>{{ number_format($total) }}</strong></div><div class="stat"><small>در صف یا انتظار</small><strong>{{ number_format(($runCounts['ready']??0)+($runCounts['waiting']??0)) }}</strong></div><div class="stat"><small>پایان‌یافته</small><strong>{{ number_format($runCounts['exited']??0) }}</strong></div><div class="stat"><small>خطاها</small><strong>{{ number_format($runCounts['failed']??0) }}</strong></div></div>
<div class="cols2" style="margin-top:18px"><section class="card"><h2 class="section-title">گزارش پیامک</h2>@foreach(['sent'=>'پذیرفته‌شده توسط API','failed'=>'ناموفق','unknown'=>'نتیجه نامشخص','sending'=>'در حال ارسال'] as $key=>$name)<div class="row" style="justify-content:space-between;padding:9px 0;border-bottom:1px solid #edf0f6"><span>{{ $name }}</span><strong>{{ number_format($smsCounts[$key]??0) }}</strong></div>@endforeach</section><section class="card"><h2 class="section-title">گزارش درخواست‌های API</h2>@foreach(['sent'=>'پذیرفته‌شده توسط API','failed'=>'ناموفق','unknown'=>'نامشخص','sending'=>'در حال ارسال'] as $key=>$name)<div class="row" style="justify-content:space-between;padding:9px 0;border-bottom:1px solid #edf0f6"><span>{{ $name }}</span><strong>{{ number_format($apiCounts[$key]??0) }}</strong></div>@endforeach</section></div>
<section class="card">
<h2 class="section-title">ورود کاربران به جرنی</h2>
<p class="subtle">برای تست روی یک کاربر، حالت «ورود فقط دستی» را انتخاب کن. این تغییر فقط از اسکن خودکار کاربران جدید جلوگیری می‌کند؛ کاربران قبلی همچنان توسط Tick پردازش می‌شوند.</p>
<div class="row" style="gap:12px;align-items:center;flex-wrap:wrap;margin:12px 0">
<label for="enrollment-mode">روش ورود:</label>
<select id="enrollment-mode"><option value="automatic" {{ ($flow->enrollment_mode??'automatic')==='automatic'?'selected':'' }}>ورود خودکار از منبع داده</option><option value="manual" {{ ($flow->enrollment_mode??'automatic')==='manual'?'selected':'' }}>فقط کاربران واردشده دستی (تست)</option></select>
<button type="button" id="save-enrollment-mode" class="btn">ثبت روش ورود</button>
</div>
<div class="row" style="gap:10px;flex-wrap:wrap;align-items:center">
<label for="manual-user-id">شناسه دقیق userId:</label>
<input id="manual-user-id" type="text" dir="ltr" maxlength="100" style="min-width:290px;flex:1" placeholder="userId از member_data">
<button type="button" class="btn primary" id="add-test-user">افزودن همین کاربر</button>
</div>
<p class="subtle">فقط برای جرنی فعال در حالت ورود دستی؛ کاربر باید در member_data وجود داشته و با شرط گره شروع مطابقت داشته باشد. ثبت کاربر به‌تنهایی پیامک نمی‌فرستد، اما Scheduler ممکن است بلافاصله مراحل جرنی را اجرا کند.</p>
</section>
<section class="card"><div class="heading"><h2 class="section-title">آمار هر مرحله</h2><span class="subtle">«ورود به مرحله» تعداد انتقال‌هاست، نه تعداد کاربران یکتا.</span></div><div class="table-scroll"><table class="table"><thead><tr><th>شناسه گره</th><th>نوع ابزار</th><th>کاربران در حال حاضر</th><th>دفعات ورود ثبت‌شده</th></tr></thead><tbody>@foreach($nodes as $node)<tr><td><code>{{ $node['id'] }}</code></td><td>{{ ['trigger'=>'ورود','slot'=>'بازه زمانی','delay'=>'تأخیر','condition'=>'شرط رویداد','attribute'=>'شرط ویژگی','wait_event'=>'انتظار رویداد','split'=>'تقسیم مسیر','sms'=>'پیامک','api'=>'درخواست API','exit'=>'خروج'][$node['type']]??$node['type'] }}</td><td>{{ number_format($currentNodes[$node['id']]??0) }}</td><td>{{ number_format($arrivals[$node['id']]??0) }}</td></tr>@endforeach</tbody></table></div></section>
<section class="card"><div class="heading"><h2 class="section-title">کاربران و جزئیات اجرا</h2><div class="actions">@foreach(['runs'=>'خروجی CSV کاربران','sms'=>'CSV پیامک','api'=>'CSV API','steps'=>'CSV مراحل'] as $kind=>$label)<a class="btn" href="{{ route('jb.export',['id'=>$flow->id,'kind'=>$kind]) }}">{{ $label }}</a>@endforeach</div></div><form method="get" class="row" style="margin-bottom:14px"><select name="state"><option value="">همه وضعیت‌ها</option>@foreach(['ready'=>'در صف','waiting'=>'انتظار','exited'=>'خروج','failed'=>'خطا'] as $state=>$label)<option value="{{ $state }}" {{ request('state')===$state?'selected':'' }}>{{ $label }}</option>@endforeach</select><button class="btn primary">فیلتر</button></form><div class="table-scroll"><table class="table"><thead><tr><th>Run ID</th><th>شناسه کاربر</th><th>مرحله فعلی</th><th>وضعیت</th><th>اقدام بعدی</th><th>زمان شروع</th><th>خطا</th><th>اقدامات</th></tr></thead><tbody>@if(count($runs) > 0)
@foreach($runs as $r)
<tr><td>{{ $r->id }}</td><td><code title="{{ $r->user_id }}">{{ \Illuminate\Support\Str::limit($r->user_id,20) }}</code></td><td>{{ $r->node_id }}</td><td><span class="badge {{ $r->state }}">{{ $r->state }}</span></td><td>{{ $r->next_run_at??'-' }}</td><td>{{ $r->created_at }}</td><td title="{{ $r->last_error }}">{{ \Illuminate\Support\Str::limit($r->last_error,65) }}</td><td>@if($r->state==='failed')<button type="button" class="btn danger retry-run" data-run="{{ $r->id }}" data-type="{{ $runNodeTypes[$r->revision][$r->node_id]??($nodes[$r->node_id]['type']??'') }}">تلاش مجدد</button>@else — @endif</td></tr>@endforeach
@else
<tr><td colspan="8">هنوز کاربری وارد این جرنی نشده است.</td></tr>
@endif
</tbody></table></div><div class="pager"><span>صفحه {{ $runs->currentPage() }} از {{ $runs->lastPage() }}</span><span>@if($runs->previousPageUrl())<a href="{{ $runs->previousPageUrl() }}">قبلی</a>@endif　@if($runs->nextPageUrl())<a href="{{ $runs->nextPageUrl() }}">بعدی</a>@endif</span></div></section>
<div class="cols2"><section class="card"><h2 class="section-title">آخرین ارسال‌های پیامک</h2><div class="table-scroll"><table class="table"><tr><th>Run</th><th>گره</th><th>قالب</th><th>وضعیت</th><th>زمان</th></tr>@if(count($sms) > 0)
@foreach($sms as $l)
<tr><td>{{ $l->run_id }}</td><td>{{ $l->node_id }}</td><td>{{ $l->template }}</td><td>{{ $l->status }}</td><td>{{ $l->created_at }}</td></tr>@endforeach
@else
<tr><td colspan="5">موردی ثبت نشده است.</td></tr>
@endif
</table></div></section><section class="card"><h2 class="section-title">آخرین درخواست‌های API</h2><div class="table-scroll"><table class="table"><tr><th>Run</th><th>گره</th><th>دامنه</th><th>وضعیت</th><th>HTTP</th></tr>@if(count($api) > 0)
@foreach($api as $l)
<tr><td>{{ $l->run_id }}</td><td>{{ $l->node_id }}</td><td>{{ $l->target_host }}</td><td>{{ $l->status }}</td><td>{{ $l->http_status??'-' }}</td></tr>@endforeach
@else
<tr><td colspan="5">موردی ثبت نشده است.</td></tr>
@endif
</table></div></section></div>
<section class="card"><h2 class="section-title">آخرین انتقال‌های ثبت‌شده</h2><div class="table-scroll"><table class="table"><tr><th>زمان</th><th>Run</th><th>گره قبلی</th><th>خروجی</th><th>گره جدید</th></tr>@if(count($logs) > 0)
@foreach($logs as $log)
<tr><td>{{ $log->created_at }}</td><td>{{ $log->run_id }}</td><td>{{ $log->from_node_id }}</td><td>{{ $log->port }}</td><td>{{ $log->to_node_id }}</td></tr>@endforeach
@else
<tr><td colspan="5">هنوز انتقالی ثبت نشده است.</td></tr>
@endif
</table></div></section>
<div class="muted-box">برای حفظ ایمنی، پاسخ‌های نامشخص SMS/API خودکار یا دستی بدون تعیین تکلیف از سمت سرویس مقصد تکرار نمی‌شوند. دکمه تلاش مجدد فقط Run ناموفق را در همان گره مجاز می‌کند؛ ارسال واقعی توسط Tick بعدی انجام خواهد شد. پس از فعال‌شدن پاک‌سازی، رکوردهای خروج‌یافته حذف ولی آمار تجمیعی آن‌ها حفظ می‌شود. فایل‌های CSV حداکثر ۱۰٬۰۰۰ ردیف از داده‌های همین جرنی را خروجی می‌دهند. تاریخچه انتقال مراحل از زمان نصب نسخه جدید ثبت می‌شود.</div></main><div class="toast" id="toast" role="status"></div>
<script>
const csrf=document.querySelector('meta[name="csrf-token"]').content;
const retryPattern=@json(route('jb.retry.run', ['id'=>$flow->id,'runId'=>'__RUN__']));
const modeUrl=@json(route('jb.enrollment.mode',$flow->id));
const enrollUrl=@json(route('jb.enroll.user',$flow->id));
function notification(t,bad=false){const el=document.getElementById('toast');el.textContent=t;el.style.background=bad?'#b42d45':'#263b61';el.style.display='block';setTimeout(()=>el.style.display='none',6500)}
async function submitAdmin(url,body){
 const res=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify(body)});
 const data=await res.json().catch(()=>({}));
 if(!res.ok)throw new Error(Object.values(data.errors||{})[0]?.[0]||data.message||('HTTP '+res.status));
 return data;
}
document.getElementById('save-enrollment-mode')?.addEventListener('click',async()=>{
 const value=document.getElementById('enrollment-mode').value;
 if(value==='automatic'&&!confirm('با این تغییر Enroll می‌تواند همه کاربران واجد شرط را وارد جرنی کند. ادامه می‌دهید؟'))return;
 const button=document.getElementById('save-enrollment-mode');button.disabled=true;
 try{await submitAdmin(modeUrl,{mode:value});notification('روش ورود جرنی ذخیره شد.');}
 catch(e){notification(e.message,true)}finally{button.disabled=false}
});
document.getElementById('add-test-user')?.addEventListener('click',async()=>{
 const userId=document.getElementById('manual-user-id').value.trim();
 if(!userId){notification('شناسه userId را وارد کنید.',true);return}
 if(!confirm('این کاربر وارد جرنی فعال می‌شود و Scheduler ممکن است SMS/API واقعی بفرستد. تأیید می‌کنید؟'))return;
 const button=document.getElementById('add-test-user');button.disabled=true;
 try{const result=await submitAdmin(enrollUrl,{user_id:userId});notification('کاربر به جرنی اضافه شد. Run: '+result.run_id);setTimeout(()=>location.reload(),1100)}
 catch(e){notification(e.message,true)}finally{button.disabled=false}
});
document.querySelectorAll('.retry-run').forEach(button=>button.addEventListener('click',async()=>{
  const external=['sms','api'].includes(button.dataset.type);
  if (!confirm(external ? 'اجرای دوباره این مرحله ممکن است باعث ارسال تکراری به سرویس مقصد شود. بعد از بررسی وضعیت ارسال قبلی، خطر ارسال تکراری را می‌پذیرید؟' : 'این اجرای ناموفق از همان مرحله دوباره به صف پردازش برگردد؟')) return;
  button.disabled=true;
  try {
    const res=await fetch(retryPattern.replace('__RUN__',encodeURIComponent(button.dataset.run)),{
      method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify({retry_external:external,ack_duplicate_risk:external})
    });
    const data=await res.json().catch(()=>({}));
    if(!res.ok) throw new Error(data.reason||data.message||`HTTP ${res.status}`);
    notification('مرحله ناموفق مجدداً آماده اجرا شد. Tick از همان گره ادامه می‌دهد.');
    setTimeout(()=>location.reload(),700);
  } catch(e) { notification(e.message,true); button.disabled=false; }
}));
</script>@include('journey-builder::partials.footer')
</body></html>
