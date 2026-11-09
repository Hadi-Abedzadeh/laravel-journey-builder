<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>لاگ و نظارت | Journey Builder</title><link rel="stylesheet" href="{{ asset('vendor/journey-builder/style.css') }}"><link rel="stylesheet" href="{{ asset('vendor/journey-builder/journey-builder-pro.css') }}?v=3.4.4"></head><body>
@include('journey-builder::partials.nav', ['active' => 'monitoring'])
<main class="wrap jb-monitor">
<div class="heading"><div><h1>لاگ و نظارت</h1><div class="subtle">آخرین اجرای واقعی سه فرمان و وضعیت صف‌ها؛ نمایش در schedule:list به‌تنهایی اجرای خودکار را ثابت نمی‌کند.</div></div><a class="btn" href="{{ route('jb.monitoring') }}">به‌روزرسانی گزارش</a></div>
@if($needsMigration)
<div class="card"><h2>راه‌اندازی گزارش نظارت</h2><p>برای فعال شدن گزارش‌ها ابتدا Migration نسخه مانیتورینگ را در SQL Server اجرا کنید. دستورات جرنی تا آن زمان همچنان اجرا می‌شوند.</p><code>2026_11_09_150000_add_journey_monitoring_and_revision_tables.php</code></div>
@else
<div class="jb-monitor-grid">
<div class="card"><div class="subtle">جرنی فعال</div><h2>{{ number_format($activeFlows) }}</h2></div>
<div class="card"><div class="subtle">Runهای موعدرسیده</div><h2>{{ number_format($due) }}</h2></div>
<div class="card"><div class="subtle">Runهای خطادار</div><h2>{{ number_format($failedRuns) }}</h2></div>
<div class="card"><div class="subtle">SMS آماده ارسال</div><h2>{{ number_format($pendingSms) }}</h2></div>
<div class="card"><div class="subtle">SMS نامشخص</div><h2>{{ number_format($unknownSms) }}</h2></div>
</div>
<section class="card"><h2 class="section-title">سلامت فرمان‌های زمان‌بندی‌شده</h2><div class="table-scroll"><table class="table"><thead><tr><th>فرمان</th><th>آخرین شروع</th><th>آخرین پایان</th><th>وضعیت</th><th>پردازش‌شده</th><th>ناموفق</th><th>زمان اجرا</th></tr></thead><tbody>
@foreach(['enroll'=>'ورود کاربران','tick'=>'اجرای مراحل','sms-dispatch'=>'ارسال پیامک'] as $key=>$title)
@php($row=$last[$key]??null)
@php($stale=$row && \Carbon\Carbon::parse($row->started_at,'UTC')->lt(now('UTC')->subMinutes(3)))
<tr><td><strong>{{ $title }}</strong><div class="subtle">{{ $key }}</div></td><td>{{ $row?->started_at ?? 'هرگز' }}</td><td>{{ $row?->finished_at ?? '—' }}</td><td><span class="badge {{ $row?->status==='failed'?'failed':($stale?'waiting':'ready') }}">{{ !$row?'هنوز اجرا نشده':($stale?'آخرین اجرا قدیمی است':'') }} {{ $row?->status ?? '' }}</span></td><td>{{ number_format($row?->processed ?? 0) }}</td><td>{{ number_format($row?->failed ?? 0) }}</td><td>{{ $row?->duration_ms === null ? '—' : $row->duration_ms.' ms' }}</td></tr>
@endforeach
</tbody></table></div><p class="subtle">اگر یکی از فرمان‌ها بیش از ۳ دقیقه اجرا نشده باشد، وضعیت آن قدیمی نمایش داده می‌شود. این هشدار تشخیص قطعی توقف Scheduler نیست؛ زمان‌بندی و قفل‌های احتمالی را نیز بررسی کنید.</p></section>
<section class="card"><div class="heading"><h2 class="section-title">تاریخچه اجراهای Artisan</h2></div>
<form class="row" method="get" style="gap:10px;flex-wrap:wrap;margin:12px 0"><select name="command"><option value="">همه فرمان‌ها</option>@foreach(['enroll'=>'Enroll','tick'=>'Tick','sms-dispatch'=>'SMS Dispatch'] as $v=>$n)<option value="{{ $v }}" @selected(request('command')===$v)>{{ $n }}</option>@endforeach</select><select name="status"><option value="">همه وضعیت‌ها</option>@foreach(['success'=>'موفق','partial'=>'بخشی ناموفق','failed'=>'ناموفق','running'=>'در حال اجرا'] as $v=>$n)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $n }}</option>@endforeach</select><button class="btn primary">فیلتر</button></form>
<div class="table-scroll"><table class="table"><thead><tr><th>شروع</th><th>فرمان</th><th>وضعیت</th><th>پردازش</th><th>خطا</th><th>خلاصه</th></tr></thead><tbody>@forelse($commandRows as $r)<tr><td>{{ $r->started_at }}</td><td>{{ $r->command_name }}</td><td>{{ $r->status }}</td><td>{{ number_format($r->processed) }}</td><td>{{ number_format($r->failed) }}</td><td style="max-width:330px;overflow-wrap:anywhere"><span title="{{ $r->error_message }}">{{ \Illuminate\Support\Str::limit($r->error_message??$r->details,130) }}</span></td></tr>@empty<tr><td colspan="6">هنوز اجرای ثبت‌شده‌ای وجود ندارد.</td></tr>@endforelse</tbody></table></div><div class="pager">صفحه {{ $commandRows->currentPage() }} از {{ $commandRows->lastPage() }} <span>@if($commandRows->previousPageUrl())<a href="{{ $commandRows->previousPageUrl() }}">قبلی</a>@endif @if($commandRows->nextPageUrl())<a href="{{ $commandRows->nextPageUrl() }}">بعدی</a>@endif</span></div></section>
<section class="card"><div class="heading"><h2 class="section-title">لاگ فعالیت مدیران</h2></div><form class="row" method="get" style="gap:8px;margin-bottom:14px"><input type="number" min="1" name="flow" placeholder="شناسه جرنی (اختیاری)" value="{{ request('flow') }}"><button class="btn">فیلتر</button></form><div class="table-scroll"><table class="table"><thead><tr><th>زمان</th><th>رویداد</th><th>جرنی</th><th>مدیر</th><th>جزئیات</th></tr></thead><tbody>@forelse($audit as $a)<tr><td>{{ $a->created_at }}</td><td>{{ $a->event_type }}</td><td>{{ $a->flow_id ?? '—' }}</td><td>{{ $a->actor ?? 'system/local' }}</td><td>{{ $a->details ?? '—' }}</td></tr>@empty<tr><td colspan="5">فعلاً رویدادی ثبت نشده.</td></tr>@endforelse</tbody></table></div></section>
<div class="muted-box">برای مشاهده جزئیات کاربران، لاگ SMS و خطاهای API هر جرنی، صفحه «جزئیات و گزارش» همان جرنی را باز کنید. تاریخچه اجرای Commandها فقط از زمان نصب این نسخه ثبت می‌شود.</div>
@endif
</main>
@include('journey-builder::partials.footer')
</body></html>
