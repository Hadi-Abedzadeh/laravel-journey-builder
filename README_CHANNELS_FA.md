# Journey Builder — Email + Najva Web Push (v0.3.4-beta.6)

این افزونه **افزایشی** است: جدول‌های موجود Journey Builder، Runها و SMS را حذف یا بازنویسی نمی‌کند. این نسخه برای آزمون در Staging طراحی شده است؛ اتصال SMTP واقعی، SQL Server واقعی و حساب نجوا در محیط ساخت پچ تست نشده‌اند.

## ۱. انتشار نسخه در مخزن اصلی

محتویات این پوشه را داخل ریشه مخزن `Hadi-Abedzadeh/laravel-journey-builder` کپی کنید (نه `vendor` پروژهٔ مصرف‌کننده). بعد از Commit و بررسی کد:

```powershell
git add .
git commit -m "Add SMTP email and Najva web push journey channels"
git push origin main
git tag -a v0.3.4-beta.6 -m "Email and Najva webpush channels"
git push origin v0.3.4-beta.6
```

اگر تگ `v0.3.4-beta.6` از قبل وجود دارد، از شماره نسخه جدید **استفاده کنید**؛ تگ منتشرشده را جابه‌جا نکنید. در Packagist نسخه را Update کنید و بعد در میزبان:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.6"
php artisan migrate --database=sqlsrv --path=vendor/hadiabedzadeh/laravel-journey-builder/database/migrations/2026_11_09_170000_add_journey_email_and_push.php
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
```

پیش از Migration از دیتابیس Backup بگیرید. migration فقط جدول‌های `jb_email_providers`، `jb_push_providers`، `jb_push_subscriptions` و `jb_channel_dispatches` را اضافه می‌کند؛ همچنین ستون‌های آمار ایمیل/پوش را به `jb_flow_totals` اضافه می‌کند (اگر جدول موجود باشد).

## ۲. فعال‌سازی صریح

در `.env`:

```dotenv
JOURNEY_BUILDER_EMAIL_ENABLED=false
JOURNEY_BUILDER_WEBPUSH_ENABLED=false
```

پس از نصب Migration، تنظیم پنل‌ها و **تست روی کاربر آزمایشی**، کانال مربوط را `true` کنید. اگر `config/journey_builder.php` قدیمی Publish شده، آن را با `channels` جدید تطبیق دهید؛ Provider پکیج تنظیمات پیش‌فرض را merge می‌کند. `php artisan optimize:clear` را اجرا کنید.

## ۳. صفحات مدیریت

- `/journeys/channels/email/providers` — نام حساب، آدرس/پورت/امنیت SMTP، username/password، ایمیل و نام فرستنده، محدودیت سرعت ارسال در دقیقه، تنظیمات اختیاری POP3
- `/journeys/channels/webpush/providers` — نام سرویس، Najva `website_id`، API Key و شیوهٔ هدر احراز هویت
- `/journeys/channels/logs` — وضعیت صف، پذیرش ارسال، خطا و نتیجهٔ نامشخص

پسوردها با Laravel `Crypt` رمزگذاری می‌شوند و در صفحات ویرایش بازخوانی نمی‌شوند. اگر `APP_KEY` عوض شود، لازم است Credentialها مجدداً ثبت شوند.

**POP3 برای دریافت ایمیل است نه ارسال.** این نسخه مشخصات POP3 را فقط برای توسعهٔ دریافت ایمیل در آینده ذخیره می‌کند؛ هیچ Inbox/POP3 Worker ندارد.

## ۴. گره‌های جدید Drag & Drop

- **ارسال ایمیل**: انتخاب حساب خاص یا پیش‌فرض، Attribute آدرس ایمیل (پیش‌فرض `email`)، `sign` اختیاری، موضوع، متن ساده یا HTML، متغیرهایی مانند `{{firstName}}`.
- **وب‌پوش نجوا**: انتخاب سرویس خاص یا پیش‌فرض، عنوان حداکثر ۵۰ کاراکتر، متن حداکثر ۱۵۰، لینک HTTPS، TTL ساعت. این گره **هرگز برای تمام مشترکین نجوا ارسال نمی‌کند**؛ فقط توکن‌های ثبت‌شدهٔ همان `userId` را هدف می‌گیرد.

در هر دو گره، خروجی `success` و `failure` باید متصل باشند. برخلاف عبارت «تحویل»، `success` نشان می‌دهد SMTP یا نجوا درخواست را پذیرفته، نه اینکه ایمیل در Inbox قرار گرفته یا پوش توسط کاربر دیده شده است.

## ۵. ثبت امن توکن نجوا برای کاربر

پیش‌نیاز: اسکریپت نجوا روی سایت، تأیید سرویس و عضوگیری مرورگر. طبق مستندات نجوا:

```javascript
const token = await window.NAJVA.getUserToken();
```

برای ارتباط حساب داخلی و توکن، در اپلیکیشن میزبان یک Endpoint **احراز هویت‌شده** ایجاد کنید که پس از اعتبارسنجی کاربر و CSRF/توکن احراز هویت، کلاس زیر را فراخوانی کند:

```php
app(\OpenJourney\LaravelJourneyBuilder\Services\PushSubscriptionRegistrar::class)
    ->save($najvaProviderId, $trustedUserId, $browserToken);
```

`$trustedUserId` باید از نشست معتبر یا مکانیزم احراز هویت سرور بدست بیاید، **نه** از پارامتر دلخواه درخواست کاربر. توکن مرورگر به‌صورت رمزگذاری‌شده ذخیره می‌شود. این پکیج یک endpoint عمومی و بدون احراز هویت برای ثبت توکن ارائه نمی‌کند.

در پنل نجوا، API Key و IP عمومی خروجی سرور را در بخش Developer/Whitelist تنظیم کنید. آدرس ارسال هدفمند طبق مستندات:

`POST https://push.najva.com/v1/send/token/` به روش `multipart/form-data` با `website_id`، تکرار فیلدهای `tokens[]`، `message.title`، `message.body` و سایر پارامترها.

در API عمومی فعلی نجوا **محل دقیق ارسال کلید احراز هویت در صفحه ارسال هدفمند صریح نیست**؛ در مدیریت پنل گزینه‌های `Authorization: Bearer`، `X-API-Key` و `Api-Key` برای سازگاری قابل انتخاب است. **پیش از استفادهٔ واقعی از نجوا، روش مورد تایید حساب خود را در درخواست تستی مشخص کنید.** پاسخ HTTP 200 را به‌تنهایی موفقیت همه توکن‌ها در نظر نگیرید: کد نتایج `Entries.tokens[]` را بررسی می‌کند و پاسخ ترکیبی را `unknown` ثبت می‌کند.

## ۶. ارسال Bulk و Scheduler

در صف مستقل `jb_channel_dispatches` هر گره برای هر Run فقط یک رکورد می‌سازد. Worker از اجرای جرنی جداست؛ بنابراین حتی هنگام صدها هزار Run، `tick` منتظر SMTP نمی‌ماند.

دستورات مستقل:

```powershell
php artisan journey-builder:channel-dispatch email --batch=200
php artisan journey-builder:channel-dispatch webpush --batch=200
```

ایمیل‌ها **به‌صورت چند پیام مستقل با استفادهٔ مجدد از یک اتصال SMTP در Batch** فرستاده می‌شوند؛ گیرندگان یکدیگر را نمی‌بینند. سقف `max_per_minute` برای هر حساب در دیتابیس اعمال می‌شود. سرعت واقعی تابع محدودیت SMTP، شبکه و تعداد Workerهای امن شماست؛ SMTP یک API ارسال جمعی واقعی نیست. در صورت نیاز به میلیون‌ها ایمیل، استفاده از API سرویس ایمیل انبوه مناسب‌تر خواهد بود.

وقتی `JOURNEY_BUILDER_AUTO_SCHEDULE=true` باشد، دو دستور بالا به Scheduler افزوده می‌شوند. اگر Scheduler دستی است در `routes/console.php` اضافه کنید:

```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('journey-builder:channel-dispatch email --batch=200')->everyMinute()->withoutOverlapping();
Schedule::command('journey-builder:channel-dispatch webpush --batch=200')->everyMinute()->withoutOverlapping();
```

Scheduler نباید هم‌زمان به‌صورت دستی و خودکار برای همان دستور ثبت شود. در PowerShell روی سیستم توسعه، `php artisan schedule:work` را در ترمینالی جداگانه اجرا کنید.

## ۷. خطا، Retry و خروج

- `failed`: نتیجهٔ رد شدن مشخص است و فقط با Retry صریح مدیر، از همان گره تکرار می‌شود.
- `unknown`: بعد از Timeout/قطع Worker یا پاسخ ترکیبی نجوا، به‌طور خودکار Retry نمی‌شود (خطر ارسال تکراری).
- `sent`: پذیرش توسط SMTP/Najva، نه تأیید رسیدن/نمایش.
- `paused`: ارسال‌های صف‌شدهٔ جرنی تا فعال‌سازی دوباره ارسال نمی‌شوند.

برای Retry از فرمان موجود `journey-builder:retry --run=ID --retry-external --ack-duplicate-risk --commit` (گزینه‌ها را با `php artisan journey-builder:retry --help` تطبیق دهید) یا دکمه تلاش مجدد استفاده کنید. داده‌های کاربر پس از Exit تنها وقتی پاک می‌شوند که retention فعال باشد و خروجی ارسال نامشخصی وجود نداشته باشد.

## ۸. آزمون و محدودیت‌ها

بررسی Syntax فایل‌های PHP، کد JavaScript و ساختار ZIP در محیط تهیه بسته انجام شده است. **تست یکپارچه واقعی روی SQL Server + SMTP + Najva و اجرای کامل PHPUnit انجام نشده است.** پیش از فعال‌کردن روی کاربران واقعی باید موارد زیر را در Staging با حساب تست بررسی کنید: Migration، SMTP/TLS، اعتبارسنجی SPF/DKIM/DMARC، Whitelist نجوا، ثبت توکن مرورگر، ارسال تکی، صف گروهی، گزارش، توقف/شروع جرنی، Retry و جلوگیری از ارسال تکراری.

مستندات: https://www.najva.com/help/push/ و https://developers.najva.com/reference/push-send-to-token و https://laravel.com/docs/12.x/mail
