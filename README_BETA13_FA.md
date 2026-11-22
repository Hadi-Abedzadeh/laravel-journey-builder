# Journey Builder v0.3.4-beta.13 — بهبود بوم و Minimap

این نسخه روی v0.3.4-beta.12 ساخته شده و **صرفاً رابط ویرایش** را تغییر می‌دهد.

## تغییرات

- **اتصال بالا/پایین:** ورودی هر گره در مرکز بالای کارت و خروجی یا خروجی‌های آن زیر کارت است. خطوط SVG با جایگاه تازه هماهنگ می‌شوند. برچسب خروجی‌ها باقی می‌ماند.
- **حرکت به چپ:** اطراف گراف، به‌ویژه سمت چپ، فضای مجازی اضافه شده؛ گره شروع در نمای Fit در مرکز مجموعه قرار می‌گیرد. مختصات موجود گراف هنگام باز کردن صفحه بازنویسی نمی‌شوند. اگر گرهی به فضای چپ مختصات اصلی منتقل شود، هنگام ویرایش مختصات ذخیره‌شده گره‌ها برای سازگاری با محدودیت‌های Laravel با هم بازپایه‌گذاری می‌شوند، بدون جابه‌جایی بصری بقیه گره‌ها؛ برای ثبت این تغییر، ذخیره لازم است.
- **زوم با موس:** چرخ موس روی محیط بوم، حول محل نشانگر زوم می‌کند. کلید Shift + چرخ موس حرکت افقی و Alt + چرخ موس حرکت عمودی را انجام می‌دهد. ترک‌پد دو‌محوره همچنان بوم را جابه‌جا می‌کند؛ Drag فضای خالی نیز برای Pan فعال است. دکمه‌های زوم و نمایش همه گره‌ها باقی می‌مانند.
- **Minimap:** در گوشه پایین چپ ویرایشگر، نقشه کوچک تمام گره‌ها، اتصالات، و مستطیل محدوده دید اضافه شده. با کلیک/درگ روی نقشه به آن نقطه می‌روید. کلیدهای جهت‌دار پس از Focus روی Minimap نیز کار می‌کنند. دکمه جمع کردن دارد.

## نصب / انتشار با Tag

ابتدا از Repository پکیج و فایل‌های تغییرکرده نسخه پشتیبان بگیرید. **پچ سبک** را در ریشه مخزن `Hadi-Abedzadeh/laravel-journey-builder` استخراج کنید؛ فایل‌های `vendor` را دستی تغییر ندهید.

```powershell
git add resources/assets/builder.js resources/assets/style.css resources/assets/journey-builder-pro.css resources/views/dashboard.blade.php README_BETA13_FA.md
git commit -m "Add top-bottom node ports, mouse zoom and minimap"
git push origin main

git tag -a v0.3.4-beta.13 -m "Journey editor minimap and vertical connectors"
git push origin v0.3.4-beta.13
```

پس از به‌روزرسانی Packagist:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.13"
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan view:clear
php artisan optimize:clear
```

در مرورگر Ctrl+F5 بزنید و `.../journeys/flows/<id>/edit` را دوباره باز کنید. اگر CSS/JS قبلی کش شده، پاسخ 200 و نسخه جدید Asset را در تب Network بررسی کنید.

## سازگاری و نکات

- هیچ Migration یا تغییر DB وجود ندارد؛ موتور `enroll`، `tick`، ارسال SMS/API و فرمت `graph_json` تغییر نکرده‌اند.
- ابعاد گره‌ها ثابت می‌ماند؛ مسیر خط‌ها برای اتصال عمودی دوباره محاسبه می‌شود.
- بوم با ۲ تا ۱۰۰ گره و مسیرهای چندشاخه باید ابتدا در محیط تست و سپس در پروژه اصلی بررسی شود. محدودیت تعداد گره همچنان از سرور اعمال می‌شود.
- `node --check` و بررسی نحوی PHP/Blade انجام شده است؛ **اجرای کامل رابط روی Laravel/مرورگر عملیاتی و آزمون End-to-End روی داده‌های واقعی انجام نشده است**. آن را ابتدا در محیط Staging بررسی کنید.
