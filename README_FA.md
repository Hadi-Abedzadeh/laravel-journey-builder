# به‌روزرسانی v0.3.4-beta.4

این نسخه گزارش اجرای واقعی Scheduler، حذف/بازیابی منطقی جرنی و ویرایش امن جرنی غیرفعال حتی در صورت داشتن Run را اضافه می‌کند. **راهنمای نصب در `README_V3_5_FA.md` است.**

# Journey Builder — hotfix پیشنهادی v0.3.4-beta.3

## رفع‌شده
1. Migration افزایشی مستقل برای `dbo.jb_retry_audit` با دو ایندکس؛ بدون حذف یا تغییر جدول‌های دیگر.
2. کنترلر پچ ویرایش: برای `draft`/`paused` بدون سابقه، امکان ویرایش دارد؛ نبود موقت `jb_retry_audit` باعث خطای SQL نمی‌شود.
3. صفحه Blade هماهنگ با `$canEdit` و ظاهر حرفه‌ای (CSS حرفه‌ای باید از قبل منتشر شده باشد).
4. تست جدید برای غایب بودن جدول audit؛ اجرای PHPUnit در این محیط انجام نشده.

## مهم: پیش از نصب
- این بسته «پچ» است، نه سورس کامل. در ریشه **Repository پکیج** استخراج کنید، نه در `vendor` پروژه مصرف‌کننده.
- اگر پچ v0.3.4-beta.2 را قبلاً در GitHub اعمال کرده‌اید، فقط Migration و کنترلر را با احتیاط ادغام کنید.
- اگر جرنی قبلاً Run/Log/Hash/آمار آرشیوشده دارد، ویرایش گراف آن عمداً قفل می‌ماند. برای تغییر مسیر، باید نسخه تازه ساخت.
- هیچ Migration قدیمی را rollback نکنید؛ جدولی را حذف نکنید.

## انتشار با Tag جدید
در ریشه مخزن پکیج:

```powershell
git status
git add src/Http/Controllers/JourneyBuilderController.php resources/views/dashboard.blade.php database/migrations/2026_11_09_121000_ensure_jb_retry_audit_table.php tests/PausedEditingTest.php
git commit -m "Fix missing retry audit table and paused flow editor"
git push origin main
git tag -a v0.3.4-beta.3 -m "Fix SQL Server audit migration and paused editor"
git push origin v0.3.4-beta.3
```

ابتدا بررسی کنید که Tag جدید آزاد باشد؛ Tag قدیمی را بازنویسی نکنید.

## نصب در datasyncer
بعد از شناسایی Tag در Packagist:

```powershell
cd D:\datasyncer
composer clear-cache
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.3"
php artisan migrate --database=sqlsrv --path=vendor/hadiabedzadeh/laravel-journey-builder/database/migrations/2026_11_09_121000_ensure_jb_retry_audit_table.php
php artisan optimize:clear
```

این پچ فایل CSS یا JS را تغییر نمی‌دهد و Asset Publish نیاز ندارد. در صورتی که Viewها در پروژه میزبان override شده‌اند، آن‌ها را بررسی کنید.

## تشخیص باقی ماندن قفل ویرایش

```sql
SELECT f.id, f.title, f.status,
  (SELECT COUNT_BIG(*) FROM dbo.jb_runs WHERE flow_id=f.id) AS runs,
  (SELECT COUNT_BIG(*) FROM dbo.jb_step_logs WHERE flow_id=f.id) AS steps,
  (SELECT COUNT_BIG(*) FROM dbo.jb_sms_logs WHERE flow_id=f.id) AS sms,
  (SELECT COUNT_BIG(*) FROM dbo.jb_api_logs WHERE flow_id=f.id) AS api,
  (SELECT COUNT_BIG(*) FROM dbo.jb_retry_audit WHERE flow_id=f.id) AS retries,
  (SELECT COUNT_BIG(*) FROM dbo.jb_run_hashes WHERE flow_id=f.id) AS hashes,
  (SELECT COUNT_BIG(*) FROM dbo.jb_flow_totals WHERE flow_id=f.id) AS totals,
  (SELECT COUNT_BIG(*) FROM dbo.jb_node_totals WHERE flow_id=f.id) AS node_totals
FROM dbo.jb_flows f
WHERE f.id IN (4,5);
```

این فایل‌ها بدون اتصال مستقیم به SQL Server بررسی نحوی شده‌اند. برای Production ابتدا روی Staging و دیتابیس پشتیبان تست کنید.
