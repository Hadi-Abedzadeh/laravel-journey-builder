# Journey Builder v0.3.4-beta.7 — اصلاح نصب کانال‌ها و عناوین وب‌پوش

این بسته سورس کامل بر پایه نسخه `v0.3.4-beta.6` است. قبل از جایگزینی، تغییرات اختصاصی مخزن خودتان را با آن مقایسه کنید. هیچ تغییر مخربی در SQL Server ندارد.

## مشکل Invalid object name

دو خطای `jb_email_providers` و `jb_push_providers` نشان می‌دهند جدول‌های کانال‌ها در **اتصال فعلی SQL Server** ایجاد نشده‌اند. فایل Migration نسخه قبل در پکیج موجود است و باید اجرا شود:

```powershell
cd D:\datasyncer
php artisan migrate --database=sqlsrv --path=vendor/hadiabedzadeh/laravel-journey-builder/database/migrations/2026_11_09_170000_add_journey_email_and_push.php
php artisan optimize:clear
```

بعد از اجرا بررسی کنید:

```sql
SELECT name FROM sys.tables WHERE schema_id = SCHEMA_ID('dbo')
  AND name IN ('jb_email_providers','jb_push_providers','jb_push_subscriptions','jb_channel_dispatches');
```

اگر Laravel می‌گوید Migration از قبل اجرا شده ولی جدول‌ها نیستند، ابتدا خروجی `php artisan migrate:status --database=sqlsrv` و تنظیم `journey_builder.connection` را بررسی کنید؛ بدون بررسی Migration را Rollback یا جدول‌های قبلی را حذف نکنید.

## تغییرات ظاهری

- «وب‌پوش نجوا» در منو، جعبه ابزار، صفحه ویرایش و مدیریت پنل‌ها به «وب‌پوش نوتیفیکیشن» تغییر کرد.
- عبارت «نجوا» فقط در جزئیات فنی ویژه ارائه‌دهنده (مانند Najva Website ID و دستورالعمل اتصال) باقی مانده است.
- مسیر URL و شناسه فنی `webpush` تغییر نکرد؛ گراف‌های قبلی سازگار باقی می‌مانند.
- در صورت نبودن جدول کانال، صفحه مدیریت به‌جای SQLSTATE خطای راهنمای نصب نشان می‌دهد.

## انتشار با Tag

فایل‌های این بسته را روی مخزن اصلی GitHub جایگزین کنید (نه پوشه vendor میزبان). پس از بررسی `git diff`:

```powershell
git add .
git commit -m "Fix channel provider setup and generic webpush labels"
git push origin main
git tag -a v0.3.4-beta.7 -m "Channel setup and webpush UI fixes"
git push origin v0.3.4-beta.7
```

بعد از شناسایی نسخه توسط Packagist:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.7"
php artisan migrate --database=sqlsrv --path=vendor/hadiabedzadeh/laravel-journey-builder/database/migrations/2026_11_09_170000_add_journey_email_and_push.php
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
```

**توجه**: اگر migration قبلاً با موفقیت اجرا شده، Laravel آن را مجدداً اجرا نمی‌کند. اگر جدول‌ها هنوز نیستند، ابتدا وضعیت Migration را بررسی کنید. این انتشار نیاز به Migration تازه‌ای ندارد. از تنظیمات و دیتابیس قبل از ارتقا Backup بگیرید.

**تست:** PHP lint و بررسی نحوی JS در صورت وجود Node.js؛ تست یکپارچه واقعی روی SQL Server / سرویس نجوا در این محیط انجام نشده است.
