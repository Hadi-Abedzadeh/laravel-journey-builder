# Journey Builder v0.3.4-beta.17 — MongoDB marketingplus

## تغییر نسبت به beta.16

- نام دیتابیس MongoDB در سرویس `src/Services/OnsiteMongoService.php` از `onsite_journey` به **`marketingplus`** تغییر کرد.
- نام Collectionها **ثابت** باقی مانده‌اند: `onsites` و `users`.
- فیلد اتصال کاربران **`users.userId`** است و با `member_data.userId` از SQL Server به‌صورت رشته‌ای تطبیق داده می‌شود.
- فیلد تخصیص نمایش آنسایت در سند کاربر **`onsite_journey`** است؛ نام فیلد به معنی نام دیتابیس نیست.
- از `onsites` فقط رکوردهای دارای `ObjectId` معتبر و `onsite_name` غیرخالی فهرست می‌شوند.
- صرفاً رشته اتصال MongoDB از `.env` خوانده می‌شود. نام دیتابیس، Collection و نام فیلدهای مرتبط در سورس ثابت‌اند.
- هیچ Migration یا تغییر در دیتابیس SQL Server نیاز نیست.

## تنظیم روی پروژه مصرف‌کننده

`.env` (رمز واقعی را فقط روی سرور قرار دهید، داخل Git ثبت نکنید):

```dotenv
JOURNEY_BUILDER_MONGODB_URI="mongodb://marketingplusUser:YOUR_PASSWORD@192.168.60.157:27017/?authSource=admin"
```

**نکته:** `authSource=admin` محل اعتبارسنجی کاربر است؛ دیتابیس کاری MongoDB به‌صورت ثابت `marketingplus` انتخاب می‌شود.

پس از انتشار نسخه جدید و آپدیت Composer:

```powershell
php artisan optimize:clear
php artisan view:clear
```

بررسی اتصال در داشبورد: `/journeys/readiness` (یا `/journeys/readiness?json=1`).

## آزمایش داده پیش از ارسال واقعی

روی MongoDB با کاربری که فقط مجوز خواندن دارد:

```javascript
use marketingplus
db.onsites.find({onsite_name: {$type: "string"}}, {_id:1, onsite_name:1}).limit(5)
db.users.findOne({userId: "SAMPLE_MEMBER_DATA_USER_ID"}, {_id:1, userId:1, onsite_journey:1})
```

فرمت مورد انتظار پس از تخصیص آنسایت:

```javascript
{ onsite_journey: { "6a3287a52b0310e5280cf1d4": true } }
```

افزودن Onsite با true و تشخیص false در انتظار نمایش، همان رفتار قبلی Beta 16 را حفظ می‌کند. اگر کاربر MongoDB پیدا نشود یا بیش از یکی باشد، سرویس ثبت نمی‌کند.

## وضعیت آزمایش

فقط تغییر ثابت نام دیتابیس و مستندات انجام شده است. دسترسی مستقیم به شبکه خصوصی و MongoDB پروژه موجود نیست و **اتصال زنده تست نشده**. همچنین پسورد نمونه را هرگز در سورس یا ZIP نگذارید.
