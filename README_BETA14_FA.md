# Journey Builder v0.3.4-beta.14 — Onsite و Readiness

این نسخه بر پایه v0.3.4-beta.13 است. فایل‌های SQL Server و Migrationهای قبلی تغییر نکرده‌اند؛ Migration تازه‌ای لازم نیست.

## تغییرات محیط ویرایش

- Minimap: فاصله از پایین صفحه `75px`؛ دکمه جمع کردن حذف شده است.
- متن راهنمای پنل ابزار به توضیح `Shift + Wheel` و `Alt + Wheel` محدود شده است.
- متن‌های اضافی `.muted-box` و توضیحات `.help` در پنل تنظیمات گره از نمایش حذف شده‌اند.

## MongoDB — لازم برای Onsite

پشتیبانی Onsite به‌صورت **اختیاری** است، تا نصب پکیج در پروژه‌هایی که MongoDB ندارند مختل نشود. افزونه PHP و کتابخانه رسمی MongoDB را در پروژه میزبان نصب کنید:

```powershell
php -m | findstr mongodb
composer require "mongodb/mongodb:^2.5"
```

اگر `php -m` افزونه `mongodb` را نشان نمی‌دهد، قبل از دستور Composer نسخه سازگار افزونه را برای PHP سرور نصب کنید. راهنمای رسمی: https://www.mongodb.com/docs/php-library/current/get-started/

### تنظیمات `.env`

```dotenv
JOURNEY_BUILDER_DB_CONNECTION=sqlsrv
JOURNEY_BUILDER_MONGODB_URI="mongodb://USER:PASSWORD@HOST:27017/?authSource=admin"
JOURNEY_BUILDER_MONGODB_DATABASE=onsite_journey
JOURNEY_BUILDER_MONGODB_ONSITES_COLLECTION=onsites
JOURNEY_BUILDER_MONGODB_USERS_COLLECTION=users
JOURNEY_BUILDER_MONGODB_NAME_FIELD=onsite_name
JOURNEY_BUILDER_MONGODB_USER_ID_FIELD=userId
JOURNEY_BUILDER_MONGODB_ASSIGNMENTS_FIELD=onsites
JOURNEY_BUILDER_MONGODB_UPSERT_USER=false
JOURNEY_BUILDER_ONSITE_ENABLED=false
```

- در بوم، انتخاب آنسایت از فهرست `onsites.onsite_name` انجام می‌شود؛ نام انتخاب‌شده داخل گراف ذخیره می‌شود، نه اطلاعات MongoDB.
- هنگام اجرای آن گره، سرویس وجود همان نام در `onsites` را دوباره بررسی می‌کند؛ سپس سند متناظر کاربر در `onsite_journey.users` را با شرط `userId = member_data.userId` می‌یابد و عملیات `$addToSet` روی آرایه `onsites` اعمال می‌کند.
- این طراحی فرض می‌کند نمونه سند به شکل زیر است:

```json
{
  "userId": "USER_ID_FROM_MEMBER_DATA",
  "onsites": ["Welcome Banner"]
}
```

**این ساختار فرضی است.** قبل از فعال‌سازی روی کاربران واقعی، حتماً یک نمونه سند غیرحساس از Collection `users` بررسی کنید. اگر کلید واقعی کاربر `_id` است یا نام آرایه مورد انتظار `onsite_journey.onsites` است، مقادیر `JOURNEY_BUILDER_MONGODB_USER_ID_FIELD` و `JOURNEY_BUILDER_MONGODB_ASSIGNMENTS_FIELD` را بر همان اساس تنظیم کنید. نام فیلد تو در تو با نقطه (`.`) قابل استفاده است.

`UPSERT_USER` پیش‌فرض `false` است تا در صورت نبودن سند کاربر، داده ناقص وارد MongoDB نشود. فقط اگر سرویس Onsite شما از ایجاد سند جدید پشتیبانی می‌کند، آن را `true` کنید. برای جلوگیری از ثبت تکراری، عملیات اضافه‌کردن نام آنسایت با `$addToSet` انجام می‌شود.

**مهم:** Success در گره Onsite فعلاً فقط به معنی «ثبت تخصیص آنسایت در MongoDB» است، نه «کاربر آن را مشاهده کرده است». دریافت Callback مشاهده/کلیک باید جداگانه طراحی شود.

پس از تأیید Mapping و تست، برای فعال‌کردن انتقال واقعی:

```dotenv
JOURNEY_BUILDER_ONSITE_ENABLED=true
```

و کش تنظیمات را پاک کنید: `php artisan optimize:clear`.

## صفحه Readiness

پس از انتشار نسخه جدید، از منوی داشبورد، «وضعیت اتصال‌ها» را باز کنید:

- `/journeys/readiness`
- `/journeys/readiness?json=1` (نتیجه JSON؛ وضعیت HTTP `503` در صورت اتصال ناموفق)

هر دو مسیر با همان Middleware احراز هویت داشبورد محافظت می‌شوند. Readiness یک `SELECT 1` روی SQL Server و یک `ping` همراه با بررسی نام Collectionها روی MongoDB انجام می‌دهد؛ هیچ داده‌ای را در دیتابیس‌ها تغییر نمی‌دهد. URI و رمز عبور در خروجی نمایش داده نمی‌شوند.

## انتشار با Git Tag

پچ سبک را در **ریشه مخزن پکیج** اعمال کنید (نه داخل `vendor` پروژه):

```powershell
git status
git add .
git commit -m "Add MongoDB onsite assignments and readiness dashboard"
git push origin main
git tag -a v0.3.4-beta.14 -m "Onsite MongoDB integration and connection readiness"
git push origin v0.3.4-beta.14
```

بعد از تشخیص Tag توسط Packagist، در `D:\datasyncer`:

```powershell
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.14"
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan view:clear
php artisan optimize:clear
php artisan route:list --path=journeys
```

در مرورگر با `Ctrl + F5` Assets را نوسازی کنید. روی جرنی تستیِ محدود به کاربر دستی، Onsite را پیش از فعال‌سازی کمپین عمومی آزمایش کنید.

## تست و محدودیت‌ها

فایل‌های PHP با `php -l` و JavaScript با `node --check` قابل ارزیابی‌اند. تست End-to-End واقعی روی MongoDB/SQL Server و ساختار Collectionهای شما در این بسته انجام نشده است. پکیج به مجموعه‌ها یا داده‌های قبلی MongoDB دست نمی‌زند مگر گره Onsite فعال در مسیر کاربر اجرا شود.
