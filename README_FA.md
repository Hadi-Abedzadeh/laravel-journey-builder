# افزودنی نسخه ۳.۲ — مدیریت پنل‌های پیامکی

تغییرات جدید، Migration و فرمان ارسال دسته‌ای در [`README_SMS_V3_2_FA.md`](README_SMS_V3_2_FA.md) تشریح شده‌اند. برای نصب نسخه قبلی روی پروژه، این راهنما را در اولویت قرار دهید.

# راهنمای نصب، ارتقا و انتشار Journey Builder در Packagist

**وضعیت:** بسته آماده آزمایش است، اما نصب واقعی روی SQL Server پروژه و ارسال پیامک/API انتها‌به‌انتها انجام نشده؛ قبل از Production باید روی Staging تست شود. بسته فعلاً در Packagist منتشر نشده است. `your-vendor` نام نمونه است و باید با نام Vendor خودتان جایگزین شود.

## ۱) تغییرات نسبت به v2.1

- پکیج واقعی Composer با فضای نام `OpenJourney\LaravelJourneyBuilder`، Service Provider قابل Auto-discovery و کنترلر/Route/View/Commandهای داخل پکیج.
- فرمان‌های `journey-builder:status` و `journey-builder:retry` (پیش‌فرض **پیش‌نمایش بدون تغییر داده**).
- فرم لاگین، رمز اختصاصی و مسیرهای `/journeys/login` و `/journeys/logout` حذف شدند. در حالت پیش‌فرض، داشبورد فقط به کاربر لاگین‌شده‌ای اجازه دسترسی می‌دهد که Gate به نام `manage-journeys` را نیز پاس کند. هیچ صفحه لاگین جدیدی ساخته نمی‌شود؛ اگر Laravel Login ندارید، پاسخ HTTP 403 می‌گیرید، نه redirect به صفحه ناموجود. صرفاً در محیط محلی می‌توان `JOURNEY_BUILDER_DASHBOARD_ACCESS=local` را قرار داد؛ فقط درخواست‌های loopback مجازند. برای Production باید مکانیزم Auth/Gate یا لایه امنیتی میزبان فعال شود؛ داشبورد دارای ارسال پیامک و HTTP است و نباید عمومی باز شود.
- اسکیما در نصب تازه SQL Server با نوع‌های صریح ساخته می‌شود؛ اسکیماهای قدیمی **خودکار تغییر نمی‌کنند**. خواندن ستون‌های `varchar` در شرط‌های ایندکس‌شونده با `CAST(? AS varchar(n))` روی **پارامتر** انجام می‌شود، نه ستون. ستون `member_data.key` و `name` در SQL Server از نوع NVARCHAR هستند و مقایسه آنها Unicode باقی می‌ماند.
- در نسخه جدید داده‌های جداول قدیمی حذف یا بازنویسی نمی‌شوند؛ برای Retry جدول افزایشی `jb_retry_audit` اضافه شده است.

## ۲) نصب در یک پروژه تازه Laravel 12

۱. پیش از انتشار در Packagist، می‌توانید از مسیر محلی Composer استفاده کنید (مسیر زیر نمونه است):

```json
{
  "repositories": [{ "type": "path", "url": "../laravel-journey-builder-package", "options": { "symlink": true, "versions": { "your-vendor/laravel-journey-builder": "dev-main" } } }]
}
```

۲. در پروژه میزبان اجرا کنید:

```bash
composer require your-vendor/laravel-journey-builder:dev-main
php artisan vendor:publish --tag=journey-builder-config
php artisan vendor:publish --tag=journey-builder-assets
php artisan migrate
php artisan route:list --path=journeys
```

اگر قبلاً جدول Laravel `jobs` را ساخته‌اید ولی Migration پیش‌فرض Laravel هنوز ثبت نشده است، `php artisan migrate` ممکن است با خطای ایجاد مجدد `jobs` متوقف شود. **در این حالت `migrate:fresh` یا حذف `jobs` نزنید**؛ Migration پکیج را به صورت محدود اجرا کنید:

```powershell
php artisan migrate --database=sqlsrv --path=vendor/your-vendor/laravel-journey-builder/database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php
```

اگر پکیج هنوز در یک مخزن محلی خارج از vendor است، مسیر بالا را متناسب با محل فایل تغییر دهید یا پس از نصب Composer اجرا کنید.

۳. در `.env` پروژه میزبان:

```dotenv
JOURNEY_BUILDER_DB_CONNECTION=sqlsrv
JOURNEY_BUILDER_SOURCE_TABLE=member_data
JOURNEY_BUILDER_DASHBOARD_ACCESS=gate
JOURNEY_BUILDER_AUTO_SCHEDULE=true
JOURNEY_BUILDER_SMS_ENABLED=false
JOURNEY_BUILDER_SMS_URL=
JOURNEY_BUILDER_SMS_TOKEN=
JOURNEY_BUILDER_API_ENABLED=false
JOURNEY_BUILDER_API_ALLOWED_HOSTS=api.example.org
```

`JOURNEY_BUILDER_AUTO_SCHEDULE=true` فقط در پروژه‌های تازه‌ای فعال شود که این دستورات قبلاً در `routes/console.php` زمان‌بندی نشده‌اند. Scheduler میزبان باید واقعاً اجرا شود (مثل `php artisan schedule:work` در توسعه، یا cron سرور در Production).

۴. فونت Vazirmatn در سورس پکیج همراه نشده است. فایل فونت پیوست خودتان را جداگانه در `public/vendor/journey-builder/vazirmatn-fanum.woff2` قرار دهید. Assetهای CSS و JS با `vendor:publish` منتشر می‌شوند.

۵. برای Production بهتر است حالت پیش‌فرض `gate` را نگه دارید و در `AppServiceProvider::boot()` میزبان، گیت مناسب را تعریف کنید، برای مثال `Gate::define('manage-journeys', fn ($user) => (bool) $user->is_admin);` (ستون/قاعده `is_admin` صرفاً مثال است؛ مطابق سیستم مجوزدهی پروژه جایگزین کنید). اگر اپلیکیشن صرفاً مخصوص مدیر است، حالت `auth` هم امکان‌پذیر است، اما اجازه دسترسی به *هر کاربر احرازشده* می‌دهد و برای سیستم چندنقشی توصیه نمی‌شود. اگر Login وجود ندارد، برای توسعه روی کامپیوتر شخصی با `APP_ENV=local` و درخواست مستقیم `127.0.0.1` می‌توانید حالت `local` را فعال کنید. **حالت local را پشت Reverse Proxy که درخواست بیرونی را به loopback تبدیل می‌کند فعال نکنید.**

## ۳) ارتقا از سورس v2.1 فعلی (حتماً با پشتیبان)

۱. ابتدا SMS/API و Scheduler جرنی را موقتاً متوقف کنید. از دیتابیس و `app/`, `routes/`, `config/`, `public/` نسخه پشتیبان بگیرید.
۲. قبل از فعال‌سازی پکیج، `require __DIR__.'/journey_builder.php';` مربوط به نسخه قبلی را از `routes/web.php` حذف کنید. فایل‌های کلاس‌های قبلی `app/Console/Commands/JourneyBuilder*`, `app/Services/JourneyBuilder/`, `app/Http/Controllers/JourneyBuilderController.php` و Middleware قبلی را از Autoload پروژه خارج کنید تا نسخه‌های قدیمی موازی اجرا نشوند؛ **اول انتقال را روی Staging انجام دهید**. `routes/console.php` را بررسی کنید: یا زمان‌بندی دو دستور قدیمی را نگه دارید و `AUTO_SCHEDULE=false` بگذارید، یا آن خطوط را حذف کرده و `AUTO_SCHEDULE=true` تنظیم کنید؛ هرگز هر دو را هم‌زمان فعال نکنید.
۳. فایل قدیمی `config/journey_builder.php` در پروژه میزبان ممکن است تنظیمات پکیج را override کند؛ تنظیمات را طبق نسخه منتشرشده بازبینی کنید و گزینه قدیمی `admin_password` را پاک کنید.
۴. پکیج را با Composer نصب کنید، Assetها را publish کنید و **فقط Migration جدید پکیج** را اجرا کنید؛ جداول `jb_flows`, `jb_runs`, `jb_sms_logs`, `jb_api_logs`, `jb_step_logs`, `jb_api_credentials` اگر وجود داشته باشند **دست‌نخورده باقی می‌مانند**، جدول `jb_retry_audit` ایجاد می‌شود.
۵. SQL Server: فایل `sql/sqlsrv_audit.sql` را در SSMS اجرا و نتیجه را برای تیم دیتابیس بفرستید. **این اسکریپت فقط SELECT دارد** و داده‌ها را تغییر نمی‌دهد. بسیاری از جداول v2.1 با `Schema::string` از نوع NVARCHAR ساخته شده‌اند؛ نصب پکیج به صورت خودکار آن‌ها را به VARCHAR تبدیل نمی‌کند، چون تغییر نوع ستون‌های دارای ایندکس/Constraint و داده فعال نیازمند طرح مهاجرت DBA است.
۶. کش Laravel را پاک کنید:

```powershell
php artisan optimize:clear
php artisan route:list --path=journeys
php artisan journey-builder:status --json
php artisan journey-builder:retry --flow=3
```

۷. ابتدا با API آزمایشی و Flow تستی، زمان‌بندی و ارسال را بررسی کنید. بعد Scheduler را روشن کنید.

## ۴) فرمان‌های جدید

```bash
# گزارش تمام جرنی‌ها
php artisan journey-builder:status

# گزارش یک جرنی به صورت JSON
php artisan journey-builder:status --flow=3 --json

# پیش‌نمایش قابل تلاش مجدد (بدون تغییر دیتابیس)
php artisan journey-builder:retry --flow=3 --limit=100

# تلاش مجدد خطای فنی Run بدون ارسال خارجی قبلی
php artisan journey-builder:retry --run=123 --commit

# اجازه ارسال مجدد فقط برای نتیجه API/SMS ناموفق ثبت‌شده
php artisan journey-builder:retry --run=123 --retry-external --ack-duplicate-risk --commit
```

**قواعد ایمنی Retry:**

- فقط Runهایی با `state=failed` و Flow فعال و Revision یکسان انتخاب می‌شوند. Flow خاموش/متوقف دست‌نخورده می‌ماند.
- اگر در همان گره SMS/API سابقه `sent`, `unknown` یا `sending` باشد **ارسال مجدد انجام نمی‌شود**؛ حتی با گزینه‌های تأیید. برای `failed` نیاز به هر دو گزینه `--retry-external` و `--ack-duplicate-risk` دارید. HTTP 4xx/5xx هم گاهی لزوماً به معنای عدم پردازش در سرویس مقصد نیست؛ تأیید ریسک ضروری است.
- برای Attempt خارجی حداکثر پیش‌فرض ۳ تلاش است؛ در `JOURNEY_BUILDER_MAX_DELIVERY_ATTEMPTS` قابل تغییر است. قبل از تکرار، رخداد در `jb_retry_audit` ثبت می‌شود.
- اگر یک SMS در مسیر طبیعی جرنی شکست خورد و Flow مسیر Failure را طی کرد، ممکن است Run به Exit رسیده باشد؛ این دستور **Run پایان‌یافته را عقب نمی‌برد**. برای ارسال دوباره چنین موردی باید طراحی مستقل و کنترل‌شده انجام شود؛ نباید اجراهای پایان‌یافته را با UPDATE دستی ریست کنید.
- نتیجه `sent` یعنی HTTP 2xx، **نه** تأیید تحویل SMS.

## ۵) نوع داده‌های SQL Server

| جدول / ستون | نوع نصب تازه | دلیل |
|---|---|---|
| `jb_* .id`, `flow_id`, `run_id`, `enroll_cursor_id` | `BIGINT` | شناسه/ارجاع و ظرفیت بالا |
| `jb_runs.user_id`, `jb_sms_logs.user_id` و مشابه | `VARCHAR(100)` | سازگاری با `member_data.userId` |
| `code` | `VARCHAR(100)` | شناسه انگلیسی تصادفی |
| `node_id` | `VARCHAR(80)` | شناسه ASCII گره |
| `status`, `state` | `VARCHAR(20)` | نام وضعیت‌های ASCII |
| `method` | `VARCHAR(8)` | POST/PUT/PATCH |
| `template` | `VARCHAR(150)` | قالب ASCII |
| `target_host` | `VARCHAR(253)` | DNS hostname |
| `title` | `NVARCHAR(200)` | عنوان فارسی/Unicode |
| `graph_json` | `NVARCHAR(MAX)` | متن JSON شامل حروف فارسی |
| `error_message`, `last_error`, `previous_error` | `NVARCHAR(2000)` | متن خطای فارسی/Unicode |
| `encrypted_token` | `VARCHAR(MAX)` | خروجی Base64 رمزگذاری لاراول |
| `revision`, `attempts` | `INT` | شمارنده محدود |
| `http_status` | `SMALLINT` | کد HTTP بین ۱۰۰ تا ۵۹۹ |
| زمان‌ها | `DATETIME2(3)` | UTC با دقت میلی‌ثانیه |

`member_data` و `jobs` توسط این پکیج **تغییر نمی‌کنند**. مطابق اسکیمای ارسالی: `member_data.userId VARCHAR(100)`, `section VARCHAR(20)`, `sign VARCHAR(30)`, `key NVARCHAR(50)`, `name NVARCHAR(50)` و `jobs.queue VARCHAR(30)` هستند. اختلاف نوع در جداول **قبلاً ایجادشده** باید توسط DBA و با بررسی Constraint/Index/Data اصلاح شود؛ پکیج خودسرانه نوع ستون موجود را عوض نمی‌کند. `CAST` پارامتر در SQL Server به قصد جلوگیری از تبدیل ستون هنگام فیلترهای ایندکس‌شونده است، اما استفاده واقعی از Index را فقط Execution Plan تأیید می‌کند.

## ۶) گام‌به‌گام انتشار روی Packagist

۱. یک حساب در GitHub و یک حساب در **https://packagist.org** بسازید.
۲. در `composer.json` همین پکیج، مقدار `"name": "your-vendor/laravel-journey-builder"` را به نام Vendor خود تغییر دهید (مثلاً `yourgithubuser/laravel-journey-builder`). نام Packagist/Composer باید lowercase و مطابق الگوی `vendor/package` باشد. اگر Namespace کلاس‌ها را تغییر نمی‌دهید، `autoload.psr-4` و `extra.laravel.providers` را **دست‌نخورده** بگذارید؛ نام Vendor Composer لزوماً همان Namespace PHP نیست.
۳. مخزن عمومی GitHub به نام `laravel-journey-builder` ایجاد کنید. **فقط محتویات همین پوشه پکیج** را داخل مخزن بگذارید، نه کل پروژه `datasyncer` یا فایل `.env` و نه توکن API، لاگ کاربر، دیتابیس یا فونت اختصاصی.
۴. در پوشه پکیج:

```bash
git init
git add .
git commit -m "Initial Laravel Journey Builder package preview"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/laravel-journey-builder.git
git push -u origin main
```

۵. اگر Composer نصب است، قبل از انتشار اجرا کنید:

```bash
composer validate --strict
composer install
composer test
```

۶. در `packagist.org` وارد شوید، روی **Submit** بزنید، URL مخزن GitHub را وارد کنید و ارسال کنید. Packagist فایل `composer.json` مخزن را بررسی می‌کند. پیشنهاد می‌شود ارتباط GitHub/Packagist برای به‌روزرسانی خودکار فعال باشد.
۷. چون روی SQL Server واقعی تست نهایی انجام نشده، فعلاً برچسب **پیش‌انتشار** بزنید:

```bash
git tag v0.1.0-beta.1
git push origin v0.1.0-beta.1
```

۸. پس از شناسایی نسخه توسط Packagist، در پروژه مقصد:

```bash
composer require your-vendor/laravel-journey-builder:0.1.0-beta.1
php artisan vendor:publish --tag=journey-builder-assets
php artisan vendor:publish --tag=journey-builder-config
```

۹. برای نسخه‌های بعدی `git tag` جدید بزنید، مثلاً `v0.1.0-beta.2` و سپس بعد از آزمون‌ها `v1.0.0`. از ویرایش نسخه منتشرشده در همان Git tag خودداری کنید. **در `composer.json` مقدار `version` لازم نیست**؛ Composer نسخه را از Git tag دریافت می‌کند.

## ۷) ملاحظات Production و کارهای باقی‌مانده

- اجرای واقعی چند Worker هم‌زمان و مدیریت Lease پس از قطع ارتباط باید در SQL Server تست شود؛ وجود قفل `locked_until` تضمین پردازش دقیقاً یک‌بار در برابر تمام قطع‌ارتباط‌ها نیست.
- Enrollment فعلی یک Cursor برای هر جرنی دارد و فقط `updated_at`/`id` ردیف‌ها را اسکن می‌کند؛ برای پوشش تغییرات دیررس یا تاریخچه و تشخیص «آخرین Attribute معتبر» لازم است منطق منبع داده برای هر پروژه صحت‌سنجی شود.
- برای API از Host allowlist استفاده می‌شود؛ در سطح شبکه هم دسترسی به IPهای خصوصی و Metadata Endpointها باید محدود شود (DNS rebinding/SSRF mitigation).
- چند جرنی می‌توانند به یک کاربر پیامک مستقل ارسال کنند؛ Rate limit سراسری و جلوگیری از تداخل بازاریابی هنوز افزوده نشده است.
- Asset و Route به طور خودکار از پکیج لود می‌شوند اما انتشار Composer/عملکرد در محیط واقعی توسط خودتان پس از Push و نصب باید آزموده شود.
