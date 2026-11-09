<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $kind==='email'?'پنل‌های ایمیل':'پنل‌های وب‌پوش' }} | Journey Builder</title>
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/style.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/journey-builder-pro.css') }}">
<style>
.channel-wrap{max-width:1250px;margin:28px auto;padding:0 20px}.channel-card{background:#fff;border:1px solid #e2e9f3;border-radius:15px;margin:16px 0;padding:23px;box-shadow:0 5px 19px #17264b08}.channel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;margin:16px 0}.channel-grid label{display:flex;flex-direction:column;gap:7px;font-size:13px}.channel-grid input,.channel-grid select{padding:11px;border:1px solid #c8d4e5;border-radius:9px;min-width:0;font:inherit}.channel-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.channel-note{color:#61728a;font-size:13px;line-height:2}.channel-warning{background:#fff5e0;padding:13px 18px;border-radius:10px;color:#875500;line-height:2}.channel-pill{background:#e9f7f0;border-radius:30px;padding:4px 9px;font-size:12px}
</style>
</head>
<body>
@include('journey-builder::partials.nav',['active'=>$kind])
<div class="channel-wrap">
<h1>{{ $kind==='email'?'مدیریت سرویس‌های ارسال ایمیل':'مدیریت وب‌پوش نجوا' }}</h1>
<p class="channel-note">{{ $kind==='email'?'SMTP برای ارسال استفاده می‌شود؛ تنظیمات POP3 اختیاری هستند و فقط برای اتصال دریافت ایمیل در آینده ذخیره می‌شوند.':'وب‌پوش برای کاربرانی اجرا می‌شود که توکن عضویت همان سرویس را به بک‌اند داده‌اند. ارسال عمومی به همهٔ اعضا فعال نیست.' }}</p>

@if(session('message'))<div class="channel-card">{{ session('message') }}</div>
@endif

@if($errors->any())
<div class="channel-warning">

@foreach($errors->all() as $error)
<p>{{ $error }}</p>

@endforeach
</div>

@endif
@php($kindEmail=$kind==='email')
<div class="channel-card">
<h2>افزودن {{ $kindEmail?'حساب SMTP':'سرویس نجوا' }} جدید</h2>
<form method="post" action="{{ route('jb.channels.store',$kind) }}">

@csrf
<div class="channel-grid"><label>نام نمایشی<input name="name" required maxlength="120" placeholder="مثلاً سرویس اصلی"></label>

@if($kindEmail)
<label>SMTP Host<input name="smtp_host" dir="ltr" required placeholder="smtp.example.com"></label>
<label>SMTP Port<select name="smtp_port"><option value="587">587</option><option value="465">465</option><option value="2525">2525</option><option value="25">25</option></select></label>
<label>امنیت SMTP<select name="smtp_security"><option value="starttls">STARTTLS</option><option value="smtps">SMTPS (465)</option></select></label>
<label>SMTP Username<input name="smtp_username" dir="ltr" required></label>
<label>SMTP Password<input name="smtp_password" type="password" autocomplete="new-password" required></label>
<label>From Email<input name="from_email" type="email" dir="ltr" required></label>
<label>From Name<input name="from_name" required></label>
<label>حداکثر ایمیل در دقیقه<input name="max_per_minute" type="number" min="1" max="10000" value="60" required></label>
<label>POP3 Host (اختیاری)<input name="pop3_host" dir="ltr"></label>
<label>POP3 Port<select name="pop3_port"><option value="">—</option><option value="995">995 (TLS)</option><option value="110">110</option></select></label>
<label>POP3 Username<input name="pop3_username" dir="ltr"></label>
<label>POP3 Password<input name="pop3_password" type="password" autocomplete="new-password"></label>
<label>POP3 Security<select name="pop3_security"><option value="">—</option><option value="tls">TLS</option><option value="none">None</option></select></label>

@else
<label>Najva Website ID<input name="website_id" dir="ltr" maxlength="64" required></label>
<label>API Key<input name="api_key" type="password" dir="ltr" autocomplete="new-password" required></label>
<label>شیوه هدر API<select name="auth_mode"><option value="bearer">Authorization: Bearer</option><option value="x-api-key">X-API-Key</option><option value="api-key">Api-Key</option></select></label>

@endif
</div><div class="channel-actions"><label><input type="checkbox" name="is_enabled" value="1"> فعال باشد</label><button class="btn primary" type="submit">افزودن سرویس</button></div>
</form></div>

@if($providers->isEmpty())
<div class="channel-card">هنوز سرویسی ثبت نشده است.</div>

@else

@foreach($providers as $p)
<div class="channel-card">
<h2>{{ $p->name }} 
@if($p->is_default)<span class="channel-pill">پیش‌فرض</span>
@endif</h2>
<form method="post" action="{{ route('jb.channels.update',[$kind,$p->id]) }}">

@csrf

@method('PUT')
<div class="channel-grid"><label>نام نمایشی<input name="name" value="{{ $p->name }}" maxlength="120" required></label>

@if($kindEmail)
<label>SMTP Host<input name="smtp_host" dir="ltr" value="{{ $p->smtp_host }}" required></label>
<label>SMTP Port<select name="smtp_port">
@foreach([587,465,2525,25] as $port)
<option value="{{ $port }}" {{ (int)$p->smtp_port===$port?'selected':'' }}>{{ $port }}</option>
@endforeach
</select></label>
<label>امنیت SMTP<select name="smtp_security"><option value="starttls" {{ $p->smtp_security==='starttls'?'selected':'' }}>STARTTLS</option><option value="smtps" {{ $p->smtp_security==='smtps'?'selected':'' }}>SMTPS</option></select></label>
<label>SMTP Username<input name="smtp_username" value="{{ $p->smtp_username }}" dir="ltr" required></label>
<label>تعویض رمز SMTP<input name="smtp_password" type="password" autocomplete="new-password" placeholder="خالی = حفظ رمز"></label>
<label>From Email<input name="from_email" value="{{ $p->from_email }}" type="email" dir="ltr" required></label>
<label>From Name<input name="from_name" value="{{ $p->from_name }}" required></label>
<label>حداکثر در دقیقه<input name="max_per_minute" type="number" min="1" max="10000" value="{{ $p->max_per_minute }}" required></label>
<label>POP3 Host<input name="pop3_host" value="{{ $p->pop3_host }}" dir="ltr"></label>
<label>POP3 Port<select name="pop3_port"><option value="">—</option><option value="995" {{ $p->pop3_port==995?'selected':'' }}>995</option><option value="110" {{ $p->pop3_port==110?'selected':'' }}>110</option></select></label>
<label>POP3 Username<input name="pop3_username" value="{{ $p->pop3_username }}" dir="ltr"></label>
<label>تعویض رمز POP3<input name="pop3_password" type="password" autocomplete="new-password" placeholder="خالی = حفظ رمز"></label>
<label>POP3 Security<select name="pop3_security"><option value="">—</option><option value="tls" {{ $p->pop3_security==='tls'?'selected':'' }}>TLS</option><option value="none" {{ $p->pop3_security==='none'?'selected':'' }}>None</option></select></label>

@else
<label>Website ID<input name="website_id" value="{{ $p->website_id }}" required dir="ltr"></label>
<label>تعویض API Key<input name="api_key" type="password" autocomplete="new-password" placeholder="خالی = حفظ کلید"></label>
<label>شیوه احراز هویت<select name="auth_mode">
@foreach(['bearer'=>'Bearer','x-api-key'=>'X-API-Key','api-key'=>'Api-Key'] as $value=>$label)
<option value="{{ $value }}" {{ $p->auth_mode===$value?'selected':'' }}>{{ $label }}</option>
@endforeach
</select></label>

@endif
</div><div class="channel-actions"><label><input type="checkbox" name="is_enabled" value="1" {{ $p->is_enabled?'checked':'' }}> فعال</label><button class="btn primary" type="submit">ذخیره</button></div></form>
<div class="channel-actions" style="margin-top:12px">

@if(!$p->is_default && $p->is_enabled)<form method="post" action="{{ route('jb.channels.default',[$kind,$p->id]) }}">
@csrf<button class="btn" type="submit">انتخاب پیش‌فرض</button></form>
@endif
<form method="post" action="{{ route('jb.channels.destroy',[$kind,$p->id]) }}" onsubmit="return confirm('این سرویس حذف شود؟');">
@csrf 
@method('DELETE')<button class="btn danger" type="submit">حذف</button></form>
</div></div>

@endforeach

@endif
<div class="channel-warning">

@if($kindEmail)
SMTP سرور باید TLS معتبر داشته باشد. برای کارایی بهتر ارسال انبوه، اتصال SMTP در هر Batch دوباره استفاده می‌شود؛ نرخ ارسال را بر اساس ظرفیت واقعی پنل تعیین کن. POP3 برای ارسال به کار نمی‌رود. ارسال واقعی نیازمند فعال‌شدن تنظیم سرور است.

@else
ابتدا اسکریپت نجوا را روی سایت نصب کن و توکن کاربران عضو را در بک‌اند ثبت کن. IP خروجی سرور باید در پنل نجوا Whitelist شود. شیوه دقیق هدر API را با حساب نجوا تست و تأیید کن. HTTP 200 به‌تنهایی تأیید تحویل همه توکن‌ها نیست.

@endif
</div>
</div>
@include('journey-builder::partials.footer')
</body></html>
