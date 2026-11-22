# Journey Builder v0.3.4-beta.15 — Onsite ObjectId و انتظار مشاهده

این نسخه بر اساس v0.3.4-beta.14 است. هیچ Migration جدیدی لازم ندارد. **اول روی Staging و یک کاربر آزمایشی** امتحان کنید.

## قرارداد داده MongoDB

نام دیتابیس و Collectionها در کد ثابت هستند:
- Database: `onsite_journey`
- Collection: `onsites` (فیلدهای `_id` از نوع `ObjectId` و `onsite_name` از نوع رشته)
- Collection: `users` (فیلد `_id` از نوع `ObjectId`)
- ساختار پیشنهادشده برای سند کاربر:

```javascript
{
  _id: ObjectId("6aa54a6e1aa2a30fce4f1bce"),
  userId: "<same-as-member_data.userId>",
  onsite_journey: {
    "6a3287a52b0310e5280cf1d4": true
  }
}
```

کلید داخل `onsite_journey`، **رشته ۲۴ کاراکتری شناسه ObjectId** مربوط به رکورد `onsites` است. مقدار `true` یعنی سایت باید آن را نشان بدهد؛ سایت پس از نمایش مقدار همان کلید را `false` می‌کند. پکیج مقدار `false` را خودش تغییر نمی‌دهد. برای انتخاب آنسایت فقط مواردی فهرست می‌شوند که `_id` معتبر و `onsite_name` غیرخالی دارند.

**نکته ضروری:** `member_data.userId` معمولاً یک رشته غیر ObjectId است. تنها وقتی `users.userId` دقیقاً با همین رشته برابر باشد، پکیج کاربر را در MongoDB می‌یابد. اگر شناسه اجرایی جرنی یک ObjectId معتبر ۲۴ رقمی باشد، سند `users` مستقیماً با `_id: ObjectId` یافت می‌شود. اگر ارتباط واقعی متفاوت است، قبل از استفاده عملیاتی باید Lookup تغییر کند. هیچ سند کاربری به‌صورت Upsert ساخته نمی‌شود و در صورت نبودن یا چندتایی‌بودن نتیجه، اجرا خطا می‌گیرد.

### تنظیمات اتصال

فقط یک مقدار اتصال در `.env` لازم است:

```dotenv
JOURNEY_BUILDER_MONGODB_URI="mongodb://USER:PASSWORD@HOST:27017/?authSource=admin"
```

در `config/journey_builder.php` صرفاً URI با همین نام خوانده می‌شود. نام دیتابیس، Collectionها، فیلد `_id`، نام `onsite_name`، مسیر `onsite_journey` و نام `userId` در `OnsiteMongoService.php` ثابت‌اند؛ تنظیمات ENV اضافی نسخه بتا ۱۴ حذف شده‌اند.

اگر `config/journey_builder.php` را قبلاً منتشر کرده‌اید، **تنظیمات onsite را با نسخه جدید جایگزین کنید**؛ زیرا کانفیگ منتشرشده با `composer update` خودکار جایگزین نمی‌شود. بقیه تنظیمات پروژه را حذف نکنید.

```php
'onsite' => [
    'mongo' => [
        'uri' => env('JOURNEY_BUILDER_MONGODB_URI', ''),
    ],
],
```

`php artisan optimize:clear` بعد از تغییر کانفیگ را فراموش نکنید. برای MongoDB افزونه `mongodb` و کتابخانه `mongodb/mongodb` باید نصب باشند. اگر لازم بود:

```bash
composer require 'mongodb/mongodb:^2.5'
```

## دو گره مجزا

1. **افزودن آنسایت:** آنسایت را بر اساس `onsites._id` انتخاب کنید؛ پکیج روی `users.onsite_journey.<onsiteId>` مقدار `true` ثبت می‌کند. `success` یعنی ثبت تخصیص، نه نمایش واقعی. `failure` فقط وقتی سیاست `branch` فعال باشد دنبال می‌شود؛ در غیر این صورت Run برای بررسی متوقف می‌شود.
2. **انتظار مشاهده آنسایت:** در گره دوم همان آنسایت را انتخاب و مهلت را از ۱ تا ۹۰ روز وارد کنید؛ مثلاً ۳ روز. موتور هر دقیقه آخرین وضعیت را می‌خواند. اگر مقدار `false` شد، مسیر `viewed` و اگر مدت تمام شد مسیر `timeout` اجرا می‌شود. نبودن مقدار تخصیص، خطا محسوب می‌شود، نه مشاهده یا Timeout ساختگی.

نمونه ترتیب گره‌ها:

`شروع -> افزودن آنسایت (success) -> انتظار مشاهده آنسایت (viewed/timeout) -> مراحل بعد`.

**هشدار منطقی:** در صورت ورود هم‌زمان یک کاربر به چند جرنی که **همان آنسایت** را تعیین می‌کنند، کلید مشترک MongoDB دارند؛ تغییر به `false` برای هر دو قابل مشاهده است. اگر لازم باشد مصرف هر بار تخصیص مستقل باشد، قرارداد MongoDB باید به سطح `runId` تغییر کند.

## Readiness و تست

`/journeys/readiness` اتصال SQL Server و Ping MongoDB و وجود Collectionهای ثابت `onsites`/`users` را بررسی می‌کند. صحت نگاشت کاربر با تست واقعی مشخص می‌شود؛ Readiness نمی‌تواند این نگاشت را بدون دانستن شناسه نمونه تضمین کند.

بعد از آزمایش روی کاربر مشخص، در MongoDB بخوانید:

```javascript
db.users.findOne(
  {userId: "<same-as-member_data.userId>"},
  {onsite_journey: 1}
)
```

پیش از اجرای این آزمایش، مطمئن شوید کاربر آزمایشی در `users` موجود است. در صورت وجود فقط `_id` و نه فیلد `userId`، نمونه سند بدون داده حساس را برای اصلاح نگاشت بفرستید.

## انتشار با Git Tag

پچ را روی سورس Repository، نه پوشه `vendor`، اعمال کنید:

```bash
git add .
git commit -m "Use onsite ObjectId flags and add onsite viewed wait"
git push origin main
git tag -a v0.3.4-beta.15 -m "Onsite Mongo ObjectId and viewed wait"
git push origin v0.3.4-beta.15
```

بعد از ثبت در Packagist:

```powershell
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.15"
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
```

Migration لازم نیست. تغییرات روی پروژه واقعی، اتصال زنده MongoDB و PHPUnit اینجا اجرا نشده‌اند. تست Syntax و اعتبارسنجی ساختار گراف انجام شده است.
