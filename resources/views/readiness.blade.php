<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>آمادگی اتصال‌ها | Journey Builder</title>
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/style.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/journey-builder-pro.css') }}?v=3.4.14">
<style>.jb-ready-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.jb-ready-item{padding:21px;border-radius:16px;border:1px solid #e4e9f4;background:#fff}.jb-ready-item h3{margin:0 0 10px}.jb-ready-item p{overflow-wrap:anywhere;line-height:1.9}.jb-ready-item.ok{border-right:4px solid #169b72}.jb-ready-item.warning,.jb-ready-item.not_configured{border-right:4px solid #e9a536}.jb-ready-item.error{border-right:4px solid #dc4b64}@media(max-width:760px){.jb-ready-grid{grid-template-columns:1fr}}</style></head><body>
@include('journey-builder::partials.nav', ['active'=>'readiness'])
<main class="wrap"><div class="heading"><div><h1>وضعیت اتصال‌ها و آمادگی اجرا</h1><p class="subtle">اتصال SQL Server و MongoDB بررسی می‌شود؛ هیچ داده‌ای نوشته نمی‌شود.</p></div><div class="actions"><a class="btn" href="{{ route('jb.readiness',['json'=>1]) }}">نتیجه JSON</a><a class="btn primary" href="{{ route('jb.readiness') }}">بررسی مجدد</a></div></div>
<div class="jb-ready-grid">
@foreach($checks as $check)
<section class="jb-ready-item {{ $check['status'] }}"><h3>{{ $check['label'] }}</h3><span class="badge {{ $check['status'] === 'ok' ? 'active' : 'failed' }}">{{ ['ok'=>'متصل','error'=>'خطا','warning'=>'هشدار','not_configured'=>'تنظیم نشده'][$check['status']]??$check['status'] }}</span><p>{{ $check['detail'] }}</p><small>{{ $check['elapsed_ms'] }} ms</small></section>
@endforeach
</div>
<div class="card" style="margin-top:20px"><strong>وضعیت سرویس Onsite:</strong> {{ config('journey_builder.onsite.enabled',false) ? 'فعال' : 'غیرفعال (ایمن)' }} <p class="subtle">MongoDB Readiness فقط اتصال و وجود Collectionها را بررسی می‌کند؛ ساختار فیلدهای سند کاربران باید پیش از فعال‌کردن ارسال Onsite تأیید شود.</p></div></main>
@include('journey-builder::partials.footer')</body></html>
