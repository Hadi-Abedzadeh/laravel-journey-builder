# ارتقا به Journey Builder v3.3 — شماره گیرنده در هر جرنی و تست تک‌کاربره

## موارد جدید

- **پنل‌های پیامکی** فقط مشخصات پنل و کلید API و `sender` را نگه می‌دارند. `receptor` و `source_sign` دیگر در فرم تعریف/ویرایش پنل وجود ندارند. ستون‌های قدیمی `jb_sms_providers.receptor_attribute` و `source_sign` برای حفظ داده‌های قبلی در SQL Server حذف نمی‌شوند، اما در پردازش SMS استفاده نمی‌شوند.
- **گره SMS مدیریت‌شده** دو فیلد جدید دارد: `receptor_attribute` (نام کلید attribute حاوی شماره موبایل، پیش‌فرض `phone`) و `receptor_sign` (فیلتر اختیاری `sign` برای یافتن شماره). متن `message` همچنان در گره نوشته می‌شود. شماره و پیام نهایی در `jb_sms_logs` ثبت و مانند نسخه قبل با `sendarray` گروه‌بندی می‌شوند.
- **ورود کاربر خاص برای تست** از صفحه جزئیات جرنی یا دستور `journey-builder:enroll-user` انجام می‌شود. حالت «فقط دستی» مانع Enroll خودکار تمام کاربران جدید *همان جرنی* می‌شود و بقیه جرنی‌ها را تغییر نمی‌دهد.
- اجرای ورود دستی فقط Run آماده می‌سازد؛ HTTP/SMS در همین درخواست ارسال نمی‌شود. اما اگر جرنی `active` باشد و Scheduler در حال اجرا باشد، Tick و sms-dispatch ممکن است عملیات خارجی را بلافاصله شروع کنند.

## روش امن ارتقا در datasyncher

1. ابتدا از کد پروژه و دیتابیس پشتیبان بگیر. اجرای خودکار Enroll/Tick/SMS Dispatcher را موقتاً در Staging متوقف کن.
2. فایل‌های پکیج جدید را داخل Repository پکیجت (نه app/ پروژه میزبان) قرار بده؛ commit/push و سپس در پروژه `composer update hadiabedzadeh/laravel-journey-builder` اجرا کن.
3. فقط Migration افزایشی جدید را اجرا کن. جدول‌های قبلی پاک یا بازسازی نمی‌شوند:

```powershell
php artisan migrate --database=sqlsrv --path=vendor/hadiabedzadeh/laravel-journey-builder/database/migrations/2026_11_08_200000_add_journey_manual_enrollment_mode.php
```

4. منابع استاتیک را به‌روزرسانی کن (اگر ویرایش‌های شخصی داری اول کپی نگه‌دار):

```powershell
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
php artisan route:list --path=journeys
php artisan list --raw | findstr journey-builder
```

5. برای یک تست **جرنی جدید** بساز، گره Trigger و گره SMS را کامل تنظیم کن و قبل از فعال‌کردن در دیتابیس حالت ورود دستی را انتخاب کن (صفحه جزئیات جرنی). سپس جرنی را Active کن. برگرد و **فقط userId تستی** را اضافه کن. اگر trigger با داده آن کاربر تطبیق ندارد، در CLI گزینه `--ignore-trigger` به همراه تأیید صریح قابل استفاده است.
6. هنگام تست با SMS واقعی، شماره موجود در Attribute انتخاب‌شده باید شماره‌ای باشد که مالک آن هستی یا برای تست آن مجوز داری. پیشنهاد می‌شود از سرور/پنل آزمایشی یا وضعیت غیرفعال SMS استفاده کنی.

## فرمان ورود دستی

پیش‌نمایش (بدون هیچ تغییر):

```powershell
php artisan journey-builder:enroll-user --flow=4 --user-id="EXACT_USER_ID"
```

ثبت فقط همین کاربر (بدون ارسال مستقیم پیامک):

```powershell
php artisan journey-builder:enroll-user --flow=4 --user-id="EXACT_USER_ID" --commit
```

برای کاربر تستی موجود که Trigger را پاس نمی‌کند (فقط با تأیید اپراتور):

```powershell
php artisan journey-builder:enroll-user --flow=4 --user-id="EXACT_USER_ID" --ignore-trigger --commit
```

**محدودیت‌ها:** جرنی باید `active` و `enrollment_mode=manual` باشد؛ `userId` باید از قبل در `member_data` وجود داشته باشد؛ یک کاربر در یک جرنی دوباره ثبت نمی‌شود. گزینه `--ignore-trigger` تنها شرط ورود Trigger را دور می‌زند و سایر شروط داخل فلو پابرجا هستند. اگر خروج کاربر پاک شده باشد، Hash باقی مانده مانع ورود مجدد است.

## SQL Server و تغییرات schema

Migration جدید فقط `jb_flows.enrollment_mode VARCHAR(12) NOT NULL DEFAULT('automatic')` را اضافه می‌کند. هیچ جدول یا رکوردی حذف نمی‌شود. فیلد جدید در WHERE تنها به صورت مقدار سروری کنترل‌شده استفاده می‌شود. ذخیره `receptor` در `jb_sms_logs.receptor VARCHAR(20)` قبلاً وجود داشته و تغییر نکرده است. `message_text` از نوع `NVARCHAR(1800)` باقی مانده تا پیام فارسی سالم بماند.

## تست و عیب‌یابی

```powershell
php artisan journey-builder:status --flow=4
php artisan journey-builder:enroll --batch=1000
php artisan journey-builder:tick --batch=1
```

**احتیاط:** دو فرمان آخر می‌توانند جرنی‌های فعال دیگر را هم پردازش کنند؛ برای تست تک‌کاربره از صفحه و فرمان `enroll-user` استفاده کن. حتی با `enroll-user` نیز Scheduler جداگانه اجرا می‌شود. قبل از استفاده عملی حتماً اجرای واقعی روی SQL Server، Queue و API پیامک تست شود.
