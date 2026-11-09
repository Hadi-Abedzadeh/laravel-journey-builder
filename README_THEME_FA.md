# فعال‌سازی دائمی ظاهر حرفه‌ای Journey Builder — پچ 3.4.1

این بسته فقط منابع نمایشی پکیج را تغییر می‌دهد و دیتابیس، SMS، API یا Engine را دستکاری نمی‌کند.

## نصب در مخزن GitHub پکیج (نه در پوشه vendor سایت)

محتوای فایل ZIP (پوشه‌های resources/views و resources/assets) را در ریشه مخزن
`Hadi-Abedzadeh/laravel-journey-builder` کپی و فایل‌های View را جایگزین کن.

برای به‌روزرسانی کد و انتشار یک برچسب آزمایشی جدید (پس از بررسی نبود تگ مشابه):

```powershell
git status
git add resources/views resources/assets/journey-builder-pro.css
git commit -m "Enable professional dashboard theme on all pages"
git push origin main
git tag -a v0.3.4-beta.1 -m "Dashboard theme activation"
git push origin v0.3.4-beta.1
```

Packagist از Tag گیت نسخه را شناسایی می‌کند؛ بعد از انتشار، سایت مصرف‌کننده می‌تواند از نسخه قفل‌شده استفاده کند:

```powershell
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.1"
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
```

## فونت

فایل فونت ضمیمه نشده است. اگر `vazirmatn-fanum.woff2` مجاز و در اختیار خودت است، در سایت نهایی زیر قرار بده:

`public/vendor/journey-builder/vazirmatn-fanum.woff2`

## آزمون

در مرورگر صفحات `/journeys`, `/journeys/sms`, `/journeys/sms/providers` و صفحه ویرایش و جزئیات جرنی را بررسی کن. در DevTools > Network وضعیت 200 برای `journey-builder-pro.css` و `vazirmatn-fanum.woff2` را چک کن.

در نسخه‌های بعد تگ جدید بساز؛ تگ قبلی را تغییر نده. در `composer.json` خود پکیج فیلد `version` قرار نده.
