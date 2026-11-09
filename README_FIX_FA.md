# اصلاح خطای unexpected token "endforeach" در Journey Builder

این پچ فقط ۴ View را اصلاح می‌کند و Migration، Engine، Scheduler و تنظیمات پیامک را تغییر نمی‌دهد.

## نصب روی مخزن اصلی پکیج (نه پوشه vendor)
1. از مخزن و فایل‌های فعلی نسخه پشتیبان بگیرید.
2. محتویات ZIP را در **ریشه مخزن laravel-journey-builder** کپی کنید؛ چهار فایل `resources/views/` جایگزین شوند.
3. تغییرات را Commit و نسخه جدید بسازید:

```powershell
git add resources/views/index.blade.php resources/views/details.blade.php resources/views/monitoring.blade.php resources/views/sms.blade.php
git commit -m "Fix Blade foreach and empty-state syntax"
git push origin main
git tag -a v0.3.4-beta.5 -m "Fix Journey Builder Blade templates"
git push origin v0.3.4-beta.5
```

اگر Tag نسخه 0.3.4-beta.5 از قبل وجود دارد، یک نسخه جدیدتر انتخاب کنید؛ Tag قبلی را جابه‌جا نکنید.

## ارتقا در پروژه datas​yncer
بعد از نمایش نسخه در Packagist:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.5"
php artisan view:clear
php artisan optimize:clear
```

توجه: اگر نسخه فعلی شما تغییرات جدیدتری نسبت به سورس Beta.4 دارد، پیش از جایگزینی فایل‌ها حتماً diff را بررسی کنید.

این پچ روی قالب‌های موجود در بسته beta.4 ساخته شده و بررسی ساختاری انجام شده؛ اجرای واقعی Laravel Blade در محیط کاربر آزمایش نشده است.
