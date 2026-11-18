# Journey Builder v0.3.4-beta.10 — فیلتر نام رویداد و مقدار خالی

## تغییرات

- افزودن فیلد اختیاری `name` به گره «ورود کاربران» (Trigger) و «شرط ویژگی»؛ و امکان انتخاب `section=events` در شرط ویژگی
- افزودن فیلتر اختیاری `key`، `operator` و `value` به گره‌های «شرط رویداد» و «انتظار رویداد» بدون تغییر رفتار جرنی‌هایی که صرفاً `name` رویداد را بررسی می‌کردند
- عملگرهای `is_empty` (مقدار `NULL` یا رشته خالی/فقط فاصله) و `is_not_empty` برای فیلترهای مقداری
- اعمال شروط در موتور ورود خودکار، ورود دستی کاربر و شرایط هنگام اجرای جرنی
- تطبیق نوع‌های SQL Server: `section` و `sign` به شکل پارامتر `VARCHAR` مقایسه می‌شوند؛ `name` و `key` طبق نوع `NVARCHAR` جدول به‌صورت Unicode مقایسه می‌شوند

## مثال

در گره «ورود کاربران»:

| فیلد | مقدار |
| --- | --- |
| section | `events` |
| name | `Reg_Started` |
| key | `NationalCode` |
| operator | `is_empty` — «خالی باشد (NULL / Empty)» |
| value | خالی |
| sign | `membershipRegistration` یا خالی |

این تنظیم فقط ردیف‌هایی را انتخاب می‌کند که هم `name` برابر `Reg_Started` باشد و هم `key` برابر `NationalCode` و مقدار `value` تهی باشد. `NULL` با «نبودن ردیف» یکسان نیست؛ در گره شرط ویژگی، `is_empty` حتماً وجود یک ردیف با همین فیلترها را لازم دارد.

نمونه بررسی SQL (اگر فقط `NULL` و رشته کاملاً خالی می‌خواهید، شرط `LTRIM/RTRIM` را به `value = ''` تغییر بدهید):

```sql
SELECT COUNT(DISTINCT [userId]) AS MatchingUsers
FROM dbo.member_data
WHERE [section] = 'events'
  AND [name] = N'Reg_Started'
  AND [key] = N'NationalCode'
  AND ([value] IS NULL OR LTRIM(RTRIM([value])) = '')
  AND [sign] = 'membershipRegistration';
```

## ارتقا و نصب

این تغییر فقط به `graph_json` و موتور پردازش مربوط است؛ **Migration جدید ندارد**. گراف‌های قبلی با مقدار پیش‌فرض `name` خالی همچنان اجرا می‌شوند.

1. فایل‌های پچ را در ریشه Repository پکیج GitHub کپی کنید (نه در `vendor` پروژه میزبان).
2. قبل از فعال‌سازی تغییرات، از دیتابیس نسخه پشتیبان بگیرید و روی Staging تست کنید.
3. نسخه آزمایشی جدید را Tag کنید:

```powershell
git add .
git commit -m "Filter member_data by event name and empty values"
git push origin main
git tag -a v0.3.4-beta.10 -m "Event name and empty value filters"
git push origin v0.3.4-beta.10
```

4. پس از شناسایی Tag در Packagist، روی پروژه Laravel:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.10"
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
```

5. در مرورگر `Ctrl + F5` بزنید. جرنی فعال را برای ویرایش ابتدا Pause کنید؛ تعریف نسخه جدید برای کاربران جدید اعمال می‌شود و اجراهای قبلی طبق Revision قبلی ادامه خواهند داد.

## نکات عملیاتی

- اسکن اتوماتیک فقط ردیف‌های موجود در `member_data` را می‌بیند؛ فقدان کامل ردیف `NationalCode` با رکورد دارای `value=NULL` یکسان نیست.
- شرط ورود فعلاً بر پایه ردیف‌های منبع و Cursor خود جرنی است؛ این تغییر به معنی بازاسکن خودکار همه تاریخچه پس از ویرایش یا تشخیص «آخرین وضعیت کل کاربر» نیست.
- تنظیم `name` اختیاری است؛ برای رکوردهای `attributes` که `name` آنها NULL است، این فیلد را خالی بگذارید.
- پیش از اجرای روی حجم زیاد، بررسی کنید ایندکس‌هایی برای `section`, `name`, `key`, `updated_at`, `id` مطابق طرح تیم دیتابیس وجود داشته باشند؛ ایجاد ایندکس در این نسخه انجام نمی‌شود.
- فایل `tests/MemberDataNameFilterTest.php` برای Testbench اضافه شده است. بررسی Syntax و تست مستقل matcher انجام شده، ولی اجرای PHPUnit/تست روی SQL Server واقعی در این محیط انجام نشده است.
