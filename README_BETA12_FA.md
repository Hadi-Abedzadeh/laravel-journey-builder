# Journey Builder v0.3.4-beta.12 — بوم بزرگ و بدون برش گره‌ها

این آپدیت نسبت به v0.3.4-beta.11 فقط روی UI ویرایش جرنی اثر می‌گذارد.

## علت مشکل

در `resources/assets/style.css` اندازه بوم `2600x2200` و SVG خطوط اتصال هم همین اندازه بود. در `resources/assets/builder.js` حرکت گره‌ها تا `x=2360` و `y=2080` محدود شده بود. در جرنی‌های بزرگ، مخصوصاً اسکریپت SQL واردشده از WebEngage، گره‌ها در مختصات بزرگ‌تر مثل `x=3360` و `y=2700` قرار می‌گیرند و از سمت راست / پایین بریده می‌شوند.

## اصلاحات

- اندازه بوم، نگهدارنده آن و SVG اتصال‌ها با توجه به دورترین موقعیت گره‌ها و اندازه پنجره به‌صورت خودکار تعیین می‌شود. بوم پایه بزرگ‌تر است و هنگام افزودن یا جابه‌جایی گره رشد می‌کند.
- محدودیت قدیمی حرکت گره‌ها برداشته شده است؛ سقف جدید 10000 مطابق اعتبارسنجی فعلی پکیج است.
- امکان «نمایش همه گره‌ها»، بزرگ‌نمایی و کوچک‌نمایی اضافه شده است.
- دکمه «بوم تمام‌صفحه» پنل‌های کناری را موقتاً پنهان می‌کند تا عرض بیشتری برای طراحی فراهم شود؛ با Escape یا همان دکمه به حالت معمول برگردید.
- در زمان تغییر Zoom، مختصات Drag و Drop و جابه‌جایی گره با مقیاس انتخاب‌شده هماهنگ می‌مانند.
- پنل‌های اصلی تنظیمات و جعبه ابزار در حالت عادی حفظ می‌شوند.

نکته: این تغییر فقط *اندازه دیداری* را تغییر می‌دهد و در داده‌های ذخیره‌شده `graph_json` دستکاری نمی‌کند. هنگام استفاده از «نمایش همه»، برای گراف‌های خیلی بزرگ اندازه گره‌ها کوچک می‌شود؛ با دکمه + یا اسکرول و Pan روی هر شاخه تمرکز کنید.

## انتشار پکیج در GitHub و Packagist

پچ سبک را در ریشه مخزن **پکیج** `https://github.com/Hadi-Abedzadeh/laravel-journey-builder` کپی کنید، نه در `D:\datasyncer\vendor`:

```powershell
git add resources/assets/builder.js resources/assets/style.css resources/assets/journey-builder-pro.css resources/views/dashboard.blade.php README_BETA12_FA.md
git commit -m "Expand journey canvas and add fit-to-view controls"
git push origin main

git tag -a v0.3.4-beta.12 -m "Expandable journey canvas and fullscreen editor"
git push origin v0.3.4-beta.12
```

بعد از به‌روزرسانی نسخه در Packagist:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.12"
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan view:clear
php artisan optimize:clear
```

سپس در صفحه ویرایش جرنی با `Ctrl+F5` کش مرورگر را پاک کنید.

## فایل‌های تغییر یافته

- `resources/assets/builder.js`
- `resources/assets/style.css`
- `resources/assets/journey-builder-pro.css`
- `resources/views/dashboard.blade.php`

Migration جدید لازم نیست و Scheduler تغییری نکرده است.

## تست

Syntax فایل JS بررسی شده و بوم روی گراف 41 گره‌ای با مختصات تا `3360x2700` در Chromium آزمایش شده است. دکمه‌های Zoom، Fit All و Fullscreen بدون خطای JavaScript عمل کردند. اجرای کامل Blade در لاراول خود پروژه انجام نشده است؛ نسخه را ابتدا در Staging بررسی کنید.
