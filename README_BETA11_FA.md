# Journey Builder v0.3.4-beta.11 — Event History & SMS Receipts

این نسخه افزایشی است و جداول موجود `member_data`، `jb_runs` و جرنی‌های قبلی را بازسازی نمی‌کند. برای نصب روی محیط تست، از SQL Server پشتیبان بگیرید و اجرای Scheduler ارسال را موقتاً متوقف کنید.

## امکانات

- گره شروع برای `section=events` می‌تواند فقط `name=Reg_Started` داشته باشد (`key` اختیاری، عملگر `exists`). **این حالت در مسیر Enroll همچنان داده‌های موجود را اسکن می‌کند و Trigger لحظه‌ای WebEngage محسوب نمی‌شود.**
- آرشیو مستقل وقوع رویدادها: `jb_event_occurrences`. این آرشیو **خودکار از `updateOrInsert` ساخته نمی‌شود**. باید کد دریافت رویداد، پس از موفقیت ذخیره اطلاعات، یک بار برای هر رخداد واقعی `EventRecorder::record()` را فراخوانی کند. داده‌های گذشته قابل بازیابی قطعی نیستند.
- با فعال‌سازی آرشیو، `Wait for Event` از شناسه ترتیبی رویداد برای تشخیص رخداد **پس از ورود به مرحله انتظار** استفاده می‌کند؛ برای `condition.scope=after_enroll` از زمان UTC وقوع استفاده می‌شود. در حالت غیرفعال، رفتار قبلی `member_data` حفظ می‌شود.
- `SMS` می‌تواند موفقیت را بر اساس «پذیرش توسط پنل» (پیش‌فرض قدیمی) یا «تحویل واقعی» تشخیص دهد.
- `SMS`, `API`, `Email` و `Web Push`: رفتار در خطای قطعی می‌تواند «توقف برای Retry» یا «عبور از شاخه Failure» باشد؛ **نتیجه نامشخص/Unknown همیشه برای بررسی دستی متوقف می‌شود**.
- دستور `journey-builder:sms-status` وضعیت پیامک‌های پذیرفته‌شده را به صورت گروهی (حداکثر ۵۰۰ شناسه در هر فراخوانی، طبق مستندات کاوه‌نگار) استعلام می‌کند.

## نصب و تنظیمات

پس از Commit/Tag نسخه و نصب آن:

```powershell
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.11"
php artisan migrate --database=sqlsrv --path=vendor/hadiabedzadeh/laravel-journey-builder/database/migrations/2026_11_18_140000_add_event_history_and_sms_receipts.php
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
```

با غیرفعال بودن دو سوییچ زیر، موتور جرنی‌های قبلی بدون تغییر در semanticsِ انتظار و تحویل کار می‌کند:

```dotenv
JOURNEY_BUILDER_EVENT_HISTORY_ENABLED=false
JOURNEY_BUILDER_EVENT_RETENTION_DAYS=120
JOURNEY_BUILDER_SMS_RECEIPTS_ENABLED=false
```

### اتصال ثبت رویداد به کد منبع

هنگامی که رویداد واقعی از سیستم منبع دریافت شد، **یک بار برای کل رویداد** پس از ذخیره موفق داده‌ها فراخوانی کنید (نه برای هر key جدا):

```php
use OpenJourney\LaravelJourneyBuilder\Services\EventRecorder;

// After source member_data upsert has committed successfully:
app(EventRecorder::class)->record(
    userId: $userId,
    eventName: 'Reg_Done',
    payload: ['RegType' => 'RegDone'],
    sign: 'membershipRegistration',
);
```

نکته مهم: اگر فراخوانی فقط بعد از COMMIT انجام شود و پروسه در فاصله آن دو سقوط کند، ممکن است Event ثبت نشود. برای تحویل تضمین‌شده، فراخوانی EventRecorder باید در **همان transaction روی همان connection** انجام شود یا از transactional outbox استفاده شود. نام رویداد را از داده واقعی بخوانید، نه مقدار ثابت نمونه.

بعد از اطمینان از ثبت پایدار رویدادها، برای گره‌های جدید و آزمایشی می‌توانید آرشیو را فعال کنید:

```dotenv
JOURNEY_BUILDER_EVENT_HISTORY_ENABLED=true
```

وقتی فعال باشد و EventRecorder هنوز به کد تولیدکننده رویداد متصل نشده باشد، Wait for Event هیچ رویداد جدیدی پیدا نمی‌کند؛ در نتیجه، فعال‌کردن سراسری آن بدون هماهنگی با منبع داده خطرناک است.

### پاک‌سازی تاریخچه Event برای دیتابیس‌های پرترافیک

برای جلوگیری از رشد نامحدود `jb_event_occurrences`، فرمان زیر ابتدا پیش‌نمایش می‌دهد و فقط در صورت `--commit` پاک می‌کند:

```powershell
php artisan journey-builder:event-prune --days=120 --batch=10000
php artisan journey-builder:event-prune --days=120 --batch=10000 --commit
```

حداقل مدت نگهداری ۹۱ روز است (حداکثر تنظیم Wait for Event فعلی ۹۰ روز). فرمان قبل از حذف، قدیمی‌ترین اجرای تکمیل‌نشده را هم در نظر می‌گیرد تا رویدادهای موردنیاز Runهای فعال/متوقف‌شده حذف نشوند. در Auto Schedule فقط وقتی آرشیو Event فعال باشد، پاک‌سازی روزانه ثبت می‌شود. این کار **سوابق قدیمی را بعد از حذف قابل بازیابی نمی‌کند**.

### وضعیت تحویل SMS

پس از Migration و آزمایش با یک پیامک:

```dotenv
JOURNEY_BUILDER_SMS_RECEIPTS_ENABLED=true
```

```powershell
php artisan journey-builder:sms-status --batch=100
```

اگر `JOURNEY_BUILDER_AUTO_SCHEDULE=true` باشد، فرمان فوق هر دقیقه ثبت می‌شود. در غیر این صورت خودتان فقط یک بار به Scheduler اضافه کنید:

```php
Schedule::command('journey-builder:sms-status --batch=500')->everyMinute()->withoutOverlapping();
```

مقادیر `delivery_status`: `queued`, `submitted`, `delivered`, `undelivered`, `unknown` و مقدار NULL برای پیام‌هایی که هنوز استعلام نشده‌اند. پاسخ API دارای `return.status=200` موفقیت درخواست است، نه تحویل نهایی پیامک. `messageid` در `provider_message_id` ذخیره می‌شود. کد `10` = تحویل واقعی.

> محدودیت رسمی: `sms/status` فقط پیامک‌های ۴۸ ساعت گذشته را پشتیبانی می‌کند. در این بسته، پیامک‌هایی که ۴۷ ساعت پس از پذیرش هنوز وضعیت قطعی ندارند، به `unknown` منتقل می‌شوند و **بدون ارسال مجدد خودکار** برای بررسی باقی می‌مانند. اگر `RunRetention` روی خروج کاربران فعال باشد، تا زمان قطعی‌شدن وضعیت، رکورد پیامک پاک نخواهد شد.

## انتشار Tag

از ریشه مخزن پکیج:

```powershell
git add .
git commit -m "Add event history and Kavenegar delivery receipts"
git push origin main
git tag -a v0.3.4-beta.11 -m "Event history and delivery receipts"
git push origin v0.3.4-beta.11
```

**وضعیت اعتبارسنجی:** Syntax کدها با PHP و JavaScript بررسی شده؛ تست‌هایی برای PostgreSQL/SQLite از طریق Testbench نوشته شده‌اند، اما اجرای PHPUnit با Laravel واقعی، بررسی Performance در SQL Server، استعلام زنده از کاوه‌نگار و تست Race Conditionها باید روی Staging انجام شود. استفاده سراسری در Production بدون این تست‌ها توصیه نمی‌شود.
