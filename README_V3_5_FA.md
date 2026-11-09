# Journey Builder — Monitoring, Delete, Paused Editor (v0.3.4-beta.4)

این بسته **سورس کامل پکیج** است و بر اساس نسخه‌های قبلی `hadiabedzadeh/laravel-journey-builder` ساخته شده است. برای Laravel 12 / PHP 8.2+ طراحی شده؛ اجرای End-to-End با SQL Server و سرویس پیامک میزبان قبل از Production لازم است.

## امکانات افزوده

- `/journeys/monitoring` — مشاهده آخرین اجرای **واقعی** فرمان‌های `enroll`، `tick`، `sms-dispatch` و مدت اجرا، خطاها، backlog و لاگ عملیات مدیران.
- `/journeys` — دکمه حذف جرنی در حالت `paused`/`draft`، سطل جرنی‌ها (`/journeys?trash=1`) و بازیابی. **حذف منطقی است، نه DROP یا DELETE دائمی**. جرنی فعال ابتدا باید غیرفعال شود.
- ذخیره طرح در `paused` حتی اگر Run یا تاریخچه داشته باشد. **برای Runهای قبلی، نسخه تعریف و توکن‌های رمزگذاری‌شده قدیمی فقط یک بار در جدول‌های Revision ذخیره می‌شود**. پس از Resume، کاربران قدیمی روی مسیر قدیمی ادامه می‌دهند و کاربران جدید روی نسخه جدید. روی جرنی `active` ویرایش همچنان ممنوع است.
- `journey-builder:monitor-prune` برای پیش‌نمایش یا پاک‌سازی فقط تاریخچه اجرای دستورها. پیش‌فرض نگهداری ۱۴ روز؛ با Auto Schedule هر روز اجرا می‌شود.
- قالب و فونت CSS حرفه‌ای قبلی حفظ شده‌اند (برای بارگذاری CSS فایل‌ها را Publish کنید؛ فونت اختصاصی را در مسیر public/vendor/journey-builder نگه دارید).

## هشدار قبل از ارتقا

1. از SQL Server و جدول‌های `jb_*` نسخه پشتیبان تهیه کنید.
2. چند دقیقه قبل از Migration و تغییرات، **Scheduler یا Workerهای مربوط به جرنی** را موقتاً متوقف کنید (از متوقف‌کردن اجزای مستقل دیگر پروژه خودداری کنید). ارسال در حال اجرای خارجی را نمی‌توان لغو کرد.
3. کد پکیج را ابتدا روی Staging منتشر و نصب کنید.
4. Migration جدید **باید پیش از بازکردن داشبورد جدید** اجرا شود؛ چون ستون `deleted_at` افزوده می‌شود.
5. Migration های دیگر قبلی که در پروژه هنوز اجرا نشده‌اند (مثلاً `jb_retry_audit` یا حالت ورود دستی) را بررسی کنید. جدول‌های فعلی حذف نمی‌شوند. دستور `migrate:fresh` ممنوع.

## انتشار نسخه با Git Tag

کد ZIP را در ریشه Repository **پکیج** استخراج کنید، نه پوشه `vendor` پروژه Laravel:

```powershell
cd D:\laravel-journey-builder
git status
git add .
git commit -m "Add journey monitoring, soft delete, and safe paused editing"
git push origin main

git tag -a v0.3.4-beta.4 -m "Journey Builder monitoring and paused editing"
git push origin v0.3.4-beta.4
```

پس از اینکه Packagist این Tag را دریافت کرد، در پروژه میزبان:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.4"

php artisan migrate --database=sqlsrv --path=vendor/hadiabedzadeh/laravel-journey-builder/database/migrations/2026_11_09_150000_add_journey_monitoring_and_revision_tables.php

php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
php artisan route:list --path=journeys
php artisan schedule:list
php artisan journey-builder:status --flow=5
```

اگر نسخه تگ‌دار هنوز در Packagist دیده نمی‌شود، به‌جای تغییر Tag قبلی، وضعیت را با `composer show hadiabedzadeh/laravel-journey-builder --all` بررسی کنید و Packagist Update بزنید.

## استفاده

- فهرست: `/journeys`
- نظارت: `/journeys/monitoring`
- جزئیات Runها و لاگ‌های SMS/API: `/journeys/flows/{id}`
- سطل: `/journeys?trash=1`

### نمونه ویرایش جرنی ۵

1. جرنی ۵ را غیرفعال (`paused`) کنید.
2. صفحه `/journeys/flows/5/edit` را باز و تغییرات را ذخیره کنید. دکمه ذخیره نباید به دلیل وجود Run قدیمی قفل باشد.
3. طرح جدید به شماره Revision جدید ذخیره می‌شود؛ Run قبلی با Revision خود ادامه خواهد داد.
4. پس از بررسی اتصال‌ها، وضعیت را `active` کنید. اگر جرنی یک SMS/API فعال دارد، Scheduler ممکن است ارسال واقعی انجام دهد. برای تست به‌جای این جرنی از یک جرنی صرفاً دستی و شماره تست استفاده کنید.

**توجه:** تغییر طرح، کاربران قبلی را به شکل خودکار از ابتدای طرح جدید شروع نمی‌کند؛ این رفتار عمداً برای جلوگیری از ارسال تکراری حفظ شده است.

### نظارت

```powershell
php artisan journey-builder:monitor-prune --days=14
# فقط با تأیید مدیر:
php artisan journey-builder:monitor-prune --days=14 --commit
```

وقتی `JOURNEY_BUILDER_AUTO_SCHEDULE=true` باشد، پاک‌سازی روزانه تاریخچه اجرای فرمان‌ها ساعت 02:15 به Schedule اضافه می‌شود. اگر Scheduler به‌صورت دستی تعریف شده است، می‌توانید همین Command را روزانه به routes/console.php اضافه کنید؛ هم‌زمان دو تعریف یکسان ثبت نکنید.

```dotenv
JOURNEY_BUILDER_COMMAND_LOG_DAYS=14
```

صفحه نظارت فقط زمانی **آخرین اجرای واقعی** را نشان می‌دهد که Artisan Commandها واقعاً اجرا شده باشند؛ `schedule:list` به‌تنهایی اجرای وظایف را ثابت نمی‌کند. برای نظارت بر ارسال ناموفق از صفحه جزئیات هر جرنی و `/journeys/sms` نیز استفاده کنید.

## دیتابیس نسخه جدید

- `jb_flows.deleted_at` — `DATETIME2(3) NULL`
- `jb_flows.deleted_from_status` — `VARCHAR(20) NULL`
- `jb_flow_revisions` — Snapshot یک‌بار برای هر `flow_id,revision` شامل `NVARCHAR(MAX)` طرح
- `jb_revision_credentials` — نسخه رمزگذاری‌شده توکن‌های API، `VARCHAR(MAX)` (نه plaintext)
- `jb_command_runs` — لاگ فرمان‌ها؛ `VARCHAR` وضعیت/فرمان، `BIGINT` شمارنده‌ها، `DATETIME2(3)` زمان‌ها، `NVARCHAR(2000)` خطاها
- `jb_admin_events` — رویدادهای مدیریتی و زمان تغییرها

Migration روی SQL Server نوع ستون‌ها را صریح تعریف می‌کند؛ Migrationهای قدیمی شما را تغییر نمی‌دهد. برای بررسی نوع ستون‌های موجود از اسکریپت `sql/sqlsrv_audit.sql` کمک بگیرید.

## تست و محدودیت‌ها

Syntax فایل‌های PHP و JS و اعتبارسنجی JSON پکیج قابل بررسی است، اما اجرای PHPUnit و اتصال به SQL Server واقعی نیازمند پروژه Laravel همراه وابستگی‌ها و دیتابیس Staging است. هیچ پیامک واقعی در این محیط ارسال نشده. اگر جرنی قبلی در حال اجرای یک تماس HTTP بوده، Pause یا Delete نمی‌تواند تماس شروع‌شده را بازگرداند. داده‌ها و لاگ‌ها در حذف منطقی حفظ می‌شوند تا بتوان جرنی را بازیابی کرد؛ پاک‌سازی خروج کاربران طبق تنظیمات قبلی Retention مجزا عمل می‌کند.
