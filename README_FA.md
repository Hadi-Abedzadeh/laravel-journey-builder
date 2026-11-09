# اصلاح ویرایش جرنی غیرفعال — v0.3.4-beta.2 (پیشنهادی)

## علت خطا
در نسخه 3.4.1، دکمه ذخیره و تنظیمات JavaScript فقط برای `draft` فعال بودند؛ وقتی جرنی غیرفعال می‌شد وضعیت آن `paused` بود، نه `draft`. کنترلر ذخیره و تغییر توکن هم فقط `draft` را می‌پذیرفتند.

## رفتار جدید
- `draft` یا `paused` **بدون هیچ سابقه اجرا**: ویرایش عنوان، گراف و توکن اختصاصی API مجاز است.
- `active` و هر جرنی دارای سابقه اجرا، حتی اگر `paused` باشد: ویرایش گراف ممنوع است. این وضعیت عمدی است، چون برخی Runها و گزارش‌های قدیمی هنوز به گره‌های قبلی وابسته‌اند.
- هنگام ذخیره جرنی بدون سابقه، Cursor ورود کاربران صفر می‌شود تا تغییر شرط شروع باعث جا افتادن کاربران قبلی نشود.
- حذف دستی Run، Log، Hash یا تغییر مستقیم `status` در SQL توصیه نمی‌شود؛ تاریخچه و سازگاری نسخه‌ها به هم می‌خورد.

## فایل‌های پچ
سه فایل را با حفظ مسیر در **ریشه مخزن پکیج** جایگزین کنید (نه `D:\datasyncer\vendor`):

- `src/Http/Controllers/JourneyBuilderController.php`
- `resources/views/dashboard.blade.php`
- `tests/PausedEditingTest.php`

فایل Blade از پچ تأییدشده ظاهر حرفه‌ای v3.4.1 گرفته شده؛ CSS حرفه‌ای دست‌نخورده باقی می‌ماند.

## بررسی قبل از نصب
برای جرنی شماره 4 در SQL Server:

```sql
SELECT f.id, f.title, f.status,
  (SELECT COUNT_BIG(*) FROM dbo.jb_runs WHERE flow_id=f.id) AS runs,
  (SELECT COUNT_BIG(*) FROM dbo.jb_run_hashes WHERE flow_id=f.id) AS hashes,
  (SELECT COUNT_BIG(*) FROM dbo.jb_step_logs WHERE flow_id=f.id) AS steps,
  (SELECT COUNT_BIG(*) FROM dbo.jb_sms_logs WHERE flow_id=f.id) AS sms_logs,
  (SELECT COUNT_BIG(*) FROM dbo.jb_api_logs WHERE flow_id=f.id) AS api_logs,
  (SELECT COUNT_BIG(*) FROM dbo.jb_flow_totals WHERE flow_id=f.id) AS aggregate_rows
FROM dbo.jb_flows f WHERE f.id=4;
```

اگر status=paused و تمام سابقه‌ها صفر باشد، ویرایش پس از نصب پچ آزاد می‌شود.

## انتشار به GitHub و Packagist
در پوشه مخزن پکیج:

```powershell
git status
git add src/Http/Controllers/JourneyBuilderController.php resources/views/dashboard.blade.php tests/PausedEditingTest.php
git commit -m "Allow editing paused journeys without execution history"
git push origin main
git tag -a v0.3.4-beta.2 -m "Paused journey editing fix"
git push origin v0.3.4-beta.2
```

اگر Tag ذکرشده قبلاً وجود دارد، به‌جایش نسخه جدیدتر و آزاد انتخاب کنید و **Tag قبلی را جابه‌جا نکنید**.

در Packagist در صورت نیاز Update بزنید و در پروژه مصرف‌کننده:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.2"
php artisan optimize:clear
```

این پچ فقط Blade و کنترلر را تغییر می‌دهد، Migration یا Asset جدید ندارد. اگر View را Publish یا Override کرده‌اید، نسخه محلی را هم با پچ تطبیق دهید. `vendor:publish --tag=journey-builder-assets` برای این پچ لازم نیست.

### بررسی تست
`php -l` روی فایل‌های PHP اجرا شده؛ اجرای واقعی PHPUnit/SQL Server در این محیط انجام نشده است. دستور `vendor/bin/phpunit` را در Repository پکیج اجرا کنید.
