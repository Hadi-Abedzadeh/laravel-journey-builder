<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>گزارش ارسال ایمیل و وب‌پوش | Journey Builder</title>
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/style.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/journey-builder-pro.css') }}">
<style>.log-wrap{max-width:1280px;padding:25px;margin:auto}.log-card{background:#fff;border-radius:16px;padding:20px;margin:16px 0;border:1px solid #dfe6ef;overflow:auto}.log-table{border-collapse:collapse;width:100%;font-size:13px}.log-table th,.log-table td{padding:12px;text-align:right;border-bottom:1px solid #e9eef5}.log-filter{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.log-filter input,.log-filter select{padding:10px;border:1px solid #ccd7e5;border-radius:8px}.muted{color:#687b90;font-size:12px}</style></head><body>
@include('journey-builder::partials.nav',['active'=>'channel-logs'])
<div class="log-wrap"><h1>گزارش ایمیل و وب‌پوش</h1>
<div class="log-card"><form class="log-filter" method="get">
<select name="channel"><option value="">همه کانال‌ها</option><option value="email" {{ ($filters['channel']??'')==='email'?'selected':'' }}>ایمیل</option><option value="webpush" {{ ($filters['channel']??'')==='webpush'?'selected':'' }}>وب‌پوش</option></select>
<select name="status"><option value="">همه وضعیت‌ها</option>
@foreach(['pending','sending','sent','failed','unknown','retry_authorized'] as $state)
<option value="{{ $state }}" {{ ($filters['status']??'')===$state?'selected':'' }}>{{ $state }}</option>
@endforeach
</select><input name="flow" type="number" min="1" placeholder="شناسه جرنی" value="{{ $filters['flow']??'' }}"><button class="btn primary" type="submit">فیلتر</button></form></div>
<div class="log-card"><h2>آمار وضعیت</h2><p>
@foreach($counts as $stat)
<span style="display:inline-block;margin:5px 4px;background:#f0f5fb;border-radius:9px;padding:9px">{{ $stat->channel }} / {{ $stat->status }}: <strong>{{ $stat->total }}</strong></span>
@endforeach
</p></div>
<div class="log-card"><table class="log-table"><thead><tr><th>ID</th><th>جرنی</th><th>Run</th><th>کانال</th><th>گره</th><th>پنل</th><th>وضعیت</th><th>تلاش</th><th>زمان ثبت</th><th>زمان پذیرش</th><th>خطا</th></tr></thead><tbody>
@if($rows->count()===0)
<tr><td colspan="11">ارسالی ثبت نشده است.</td></tr>
@else
@foreach($rows as $item)
<tr><td>{{ $item->id }}</td><td>{{ $item->flow_id }}</td><td>{{ $item->run_id }}</td><td>{{ $item->channel }}</td><td>{{ $item->node_id }}</td><td>{{ $item->provider_id }}</td><td>{{ $item->status }}</td><td>{{ $item->attempts }}</td><td>{{ $item->created_at }}</td><td>{{ $item->sent_at }}</td><td class="muted">{{ $item->last_error }}</td></tr>
@endforeach
@endif
</tbody></table><div style="padding:15px">{{ $rows->links() }}</div></div>
<p class="muted">وضعیت sent یعنی پذیرش توسط SMTP یا سرویس وب‌پوش، نه الزاماً تحویل به Inbox یا نمایش اعلان روی دستگاه. وضعیت unknown نیاز به بررسی دارد.</p>
</div>@include('journey-builder::partials.footer')</body></html>
