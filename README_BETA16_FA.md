# Journey Builder — v0.3.4-beta.16

این نسخه دو مورد را اصلاح می‌کند و ساختار دیتابیس یا موتور اجرا را تغییر نمی‌دهد.

## ۱. رفع خطای صفحه ویرایش

در `resources/views/dashboard.blade.php` حلقه‌ها و شرط‌های فشرده‌ای که در چند جای فایل بدون خط جدید نوشته شده بودند حذف و ابزارهای ثابت به HTML ساده تبدیل شدند. همچنین مقدار `$ui` در کنترلر ساخته می‌شود و دیگر از دستور درون‌خطی `@php(...)` در انتهای Blade استفاده نمی‌شود. با این کار دستورهای بازِ Blade که ممکن است به `syntax error, unexpected end of file` منتهی شوند از این View حذف شده‌اند.

## ۲. نگاشت MongoDB و SQL Server

کلید `member_data.userId` در SQL Server *رشته* است و دقیقاً با `users.userId` در MongoDB تطبیق داده می‌شود. فیلد `users._id` فقط برای آپدیت ایمن همان سند استفاده می‌شود و نوع آن `ObjectId` باقی می‌ماند. هیچ سند جدیدی در Mongo ساخته نمی‌شود. اگر هیچ کاربر یا بیش از یک کاربر با `userId` برابر پیدا شود، گره Onsite خطا برمی‌گرداند و از تغییر سند نامطمئن خودداری می‌کند.

ساختار اختصاص آنسایت تغییر نکرده است:

```javascript
{
  _id: ObjectId('...'),
  userId: '<same string as member_data.userId>',
  onsite_journey: {
    '<24-char onsite ObjectId hex>': true
  }
}
```

پس از نمایش آنسایت، سیستم Onsite مقدار همان کلید را `false` می‌کند و گره `wait_onsite` آن را بررسی می‌کند.

## انتشار نسخه جدید با Git Tag

پچ را در ریشه مخزن سورس پکیج قرار دهید؛ در پوشه `vendor` پروژه ویرایش نکنید.

```powershell
git add resources/views/dashboard.blade.php src/Http/Controllers/JourneyBuilderController.php src/Services/OnsiteMongoService.php README_BETA16_FA.md
git commit -m "Fix Blade editor rendering and Mongo userId lookup"
git push origin main

git tag -a v0.3.4-beta.16 -m "Editor Blade and Mongo mapping fixes"
git push origin v0.3.4-beta.16
```

پس از شناسایی Tag در Packagist، در پروژه میزبان:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.16"
php artisan view:clear
php artisan optimize:clear
```

این نسخه تغییر جدیدی در `resources/assets` ندارد؛ انتشار دوباره Assetها ضروری نیست. Migration هم ندارد.

## تست

۱. `http://127.0.0.1:8000/journeys/flows/8/edit` را باز کنید.
۲. یک گره Onsite را انتخاب کنید و فهرست `onsite_name` را بررسی کنید.
۳. برای تست اجرای واقعی، یک جرنی دستی و کاربر آزمایشی به کار ببرید؛ پس از اختصاص، سند همان `users.userId` را در MongoDB بررسی کنید.
۴. قبل از فعال‌کردن گره Onsite در Production، اطمینان حاصل کنید که در `users` برای هر `userId` دقیقاً یک سند وجود دارد و `onsite_journey` ساختاری مطابق انتظار دارد.

تست‌های انجام‌شده در زمان آماده‌سازی: PHP lint کنترلر و سرویس و بررسی ساختار Blade. کامپایل Blade با Laravel نصب‌شده و اتصال واقعی MongoDB در محیط نویسنده قابل اجرا نبوده و باید در پروژه میزبان انجام شود.
