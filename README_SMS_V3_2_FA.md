# Journey Builder v3.2 — مدیریت پنل‌های پیامک (کاوه‌نگار)

این بسته بر پایه v3.1 ساخته شده و کد آن به صورت Composer Package قابل انتشار است. اجرای واقعی روی دیتابیس SQL Server و حساب کاوه‌نگار شما در این محیط انجام نشده است. روی Staging تست کنید؛ ارسال واقعی **به‌صورت پیش‌فرض غیرفعال** است.

## امکانات

- مسیر `/journeys/sms/providers`: ثبت، ویرایش، فعال/غیرفعال، تعیین پیش‌فرض، حذف نرم پنل‌ها؛ فعلاً درایور «Kavenegar» پشتیبانی می‌شود. هویت و مجوز داشبورد از Middleware پکیج تبعیت می‌کند.
- در هر گره SMS: انتخاب «پنل پیش‌فرض» یا یک پنل مشخص، ورود متن پیام `message`، و جایگزینی `{{firstName}}`، `{{lastName}}`، `{{phone}}` و هر Attribute با کلید ASCII مشابه. مثلاً `سلام {{firstName}}، وضعیت شما بررسی شد.`
- پارامتر `sender`: شماره فرستنده در تنظیمات پنل. پارامتر `receptor`: شماره از `member_data` با `section=attributes` و `key=phone` یا کلید انتخاب‌شده در پنل؛ `source_sign` اختیاری است.
- `API-KEY`: هنگام ثبت پنل از مدیر دریافت می‌شود و تنها در جدول `jb_sms_providers.encrypted_api_key` با `Crypt::encryptString` ذخیره می‌شود؛ کلید هرگز در Graph، HTML، گزارش‌ها یا خروجی API نمایش داده نمی‌شود. برای رمزگشایی باید `APP_KEY` لاراول ثابت بماند.
- ارسال گروهی: `POST https://api.kavenegar.com/v1/{API-KEY}/sms/sendarray.json`؛ درخواست‌های حداکثر ۲۰۰تایی، با آرایه‌های `message`، `sender`، `receptor` و شناسه‌های یکتای `localmessageids`. ترتیب پاسخ‌ها بر اساس شماره گیرنده + متن، نه ترتیب آرایه، با پیام‌ها مطابقت داده می‌شود. تغییر محدودیت در `JOURNEY_BUILDER_SMS_CHUNK_SIZE` فقط برای عددهای ۱ تا ۲۰۰ مجاز است.
- وضعیت `sent` صرفاً نشان‌دهنده پذیرش درخواست توسط کاوه‌نگار است؛ **تحویل نهایی به گیرنده** هنوز در این نسخه پایش نمی‌شود. برای وضعیت تحویل باید `sms/status.json` و شناسه `provider_message_id` در آینده پیاده‌سازی شود.

## جدول‌ها و انواع داده

Migration جدید: `database/migrations/2026_11_08_180000_add_journey_sms_providers.php`

- `jb_sms_providers`: `id BIGINT`, `code VARCHAR(64)`, `name NVARCHAR(120)`, `driver VARCHAR(32)`, `sender VARCHAR(24)`, `receptor_attribute NVARCHAR(50)`، `source_sign VARCHAR(30)`، `encrypted_api_key VARCHAR(MAX)`، `is_enabled/is_default BIT`، زمان‌ها `DATETIME2(3)`.
- `jb_sms_logs` (ستون‌های الحاقی): `provider_id BIGINT`، `sender VARCHAR(24)`، `receptor VARCHAR(20)`، `message_text NVARCHAR(1800)`، `provider_message_id BIGINT`.
- گزارش بررسی نوع ستون‌ها و ایندکس‌ها: `sql/sqlsrv_sms_schema_audit.sql`.
- Unique index فیلترشده در SQL Server فقط اجازه یک پیش‌فرض را می‌دهد. Migration افزایشی است و هیچ رکورد یا جدولی را حذف نمی‌کند. داده‌های شماره گیرنده و متن پیام تا زمان خروج/پاک‌سازی در `jb_sms_logs` ذخیره می‌شوند.

## نصب روی پروژه datasyncer

1. **پشتیبان SQL Server** بگیرید و ارسال Scheduler را موقتاً متوقف کنید. سورس این ZIP را در مخزن GitHub پکیج خود جایگزین/Push کنید. فایل‌های پروژه لاراول میزبان را با ZIP به صورت دستی جایگزین نکنید.
2. روی پروژه میزبان:

   ```powershell
   composer update hadiabedzadeh/laravel-journey-builder
   php artisan migrate --database=sqlsrv --path=vendor/hadiabedzadeh/laravel-journey-builder/database/migrations/2026_11_08_180000_add_journey_sms_providers.php
   php artisan optimize:clear
   php artisan vendor:publish --tag=journey-builder-assets --force
   ```

   اجرای `php artisan migrate` بدون `--path` در پروژه شما ممکن است به Migration تکراری `jobs` برخورد کند؛ از مسیر بالا استفاده کنید.
3. فایل `config/journey_builder.php` منتشرشده از نسخه قبلی ممکن است تنظیمات جدید را نداشته باشد. مقادیر زیر را به بخش `sms` آن **اضافه کنید** یا با نسخه جدید ادغام کنید (بازنویسی کامل ممکن است تنظیمات قبلی شما را پاک کند):

   ```php
   'managed_enabled' => (bool) env('JOURNEY_BUILDER_MANAGED_SMS_ENABLED', false),
   'chunk_size' => (int) env('JOURNEY_BUILDER_SMS_CHUNK_SIZE', 200),
   ```

4. در `.env` تا پایان تست مقدار زیر را نگه دارید:

   ```dotenv
   JOURNEY_BUILDER_MANAGED_SMS_ENABLED=false
   JOURNEY_BUILDER_SMS_CHUNK_SIZE=200
   ```

5. وارد `/journeys/sms/providers` شوید. یک پنل کاوه‌نگار بسازید، خط sender، نام Attribute گیرنده و API-KEY را وارد کنید؛ پنل را فعال و به‌عنوان **پیش‌فرض** تعیین کنید. در `/journeys` یک جرنی Draft بسازید و در گره SMS متن بنویسید. گره‌های قدیمی بدون `delivery_mode` همچنان با Gateway قدیمی اجرا می‌شوند و خودکار مهاجرت داده نمی‌شوند.
6. فقط پس از تست API و یک شماره آزمایشی در Staging، `JOURNEY_BUILDER_MANAGED_SMS_ENABLED=true` را تنظیم کنید، کش را پاک کنید و جرنی تست را فعال کنید.

## زمان‌بندی بسیار مهم

پکیج قبلی فقط `enroll` و `tick` داشت. در نسخه جدید برای پیامک‌های مدیریت‌شده **فرمان سوم** لازم است:

```powershell
php artisan journey-builder:sms-dispatch --batch=1000
```

اگر `JOURNEY_BUILDER_AUTO_SCHEDULE=true` باشد، Service Provider هر سه فرمان را دقیقه‌ای ثبت می‌کند. اگر زمان‌بندی دستی در `routes/console.php` دارید و `AUTO_SCHEDULE=false` است، **فقط** فرمان سوم را کنار دو فرمان موجود اضافه کنید:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('journey-builder:sms-dispatch --batch=1000')
    ->everyMinute()
    ->withoutOverlapping();
```

ترتیب در هر چرخه: `enroll` کاربران را وارد می‌کند، `tick` گره SMS را به وضعیت `pending` منتقل می‌کند، `sms-dispatch` صف را ارسال می‌کند؛ سپس `tick` در نوبت بعدی پاسخ را می‌خواند و کاربر را از **همان گره** جلو می‌برد. برای پیامک‌های ناموفق، `journey-builder:retry --run=... --retry-external --ack-duplicate-risk --commit` و پس از آن `tick` و `sms-dispatch` لازم است. وضعیت `unknown` بدون تطبیق با پنل مقصد به‌صورت خودکار ارسال نمی‌شود.

## نمونه تنظیم متن در گره

- روش ارسال: «پنل پیامکی مدیریت‌شده»
- پنل: «پیش‌فرض سیستم»
- `message`: `سلام {{firstName}} {{lastName}}، این پیام برای شماست.`
- `receptor`: از `member_data` (کلید پیش‌فرض `phone`، آخرین مقدار `updated_at,id`). شماره‌های موبایل ایران مانند `0912...`، `+98912...` به فرمت `09...` نرمال می‌شوند.
- `sender`: در صفحه پنل، مثال `10004346`.
- `API-KEY`: فقط در صفحه تنظیمات پنل؛ در گره SMS وارد نمی‌شود.

## حفاظ‌های عملیاتی

- لاگ SMS به‌صورت یکتا با `(run_id,node_id)` کنترل می‌شود؛ `localmessageids` نیز از ترکیب ثابت شناسه پنل و لاگ تولید می‌شوند. با این وجود، نتیجه ناشناخته (`unknown`) **نباید بدون بررسی پنل** مجدداً فرستاده شود.
- یک درخواست کاوه‌نگار حداکثر ۲۰۰ پیام دارد. فرمان می‌تواند `--batch=1000` باشد؛ پردازش در دسته‌های کوتاه حداکثر ۲۰۰تایی انجام می‌شود. اگر Worker وسط کار قطع شود، فقط پیام‌های `sending` آن دسته ممکن است وضعیت نامشخص پیدا کنند.
- حذف پنل به‌صورت Soft-delete انجام می‌شود و کلید رمزگذاری‌شده قبلی از بین می‌رود؛ حذف پنلی که در صف حل‌نشده یا جرنی فعال/متوقف‌شده استفاده می‌شود مسدود است؛ پنل پیش‌فرضِ بدون مصرف نیز قابل حذف است.
- متغیرهای بدون مقدار و شماره‌های نامعتبر مانع ارسال می‌شوند. تکرار خروجی `sent` فقط نشان‌دهنده پذیرش پیامک توسط کاوه‌نگار است، نه تحویل قطعی.
- همه Routeها از `DashboardAccess` فعلی پکیج تبعیت می‌کنند. **پنل تنظیمات SMS را بدون احراز هویت و مجوز مدیریت در اینترنت در دسترس نگذارید.**
- برای پاک‌سازی Runهای تمام‌شده، تنظیمات اختیاری Retention نسخه قبلی باقی می‌مانند؛ داده‌های `unknown` یا `failed` برای رسیدگی دستی حفظ می‌شوند.

## تست و انتشار

- سورس دارای `tests/ManagedSmsTest.php` است (HTTP fake بدون ارسال واقعی، سفارش ۳ پیام شخصی‌سازی‌شده، دریافت پاسخ در ترتیب معکوس، کنترل یک درخواست و تداوم Run از همان گره).
- در ریشه پکیج: `composer validate --strict`, `composer install`, `vendor/bin/phpunit`.
- تغییرات را Commit/Push و یک Tag پیش‌انتشار جدید مانند `v0.2.0-beta.1` منتشر کنید؛ سپس در پروژه میزبان با Composer به‌روزرسانی کنید.
- محدودیت: صحت عملی اتصال، خطاهای واقعی سرویس کاوه‌نگار، همزمانی SQL Server و Delivery در این محیط آزمایش نشده‌اند.

مستندات رسمی: https://kavenegar.com/rest.html
