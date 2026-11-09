# نسخه ۳.۴ — پوسته اختیاری، منوی یکسان، حرکت بوم و توکن اختصاصی

## تغییرات
- یک Partial یکسان برای نوار ناوبری تمام ۵ صفحه و یک Partial فوتر مشترک ساخته شد.
- پوسته حرفه‌ای در `resources/assets/journey-builder-pro.css` **اختیاری** است. هیچ View آن را خودکار بارگذاری نمی‌کند. بدون آن رابط قبلی فعال است.
- روی بوم طراحی: چرخ موس = عمودی، Shift+چرخ = افقی، ترک‌پد = هر دو جهت، کشیدن فضای خالی با موس = جابه‌جایی در هر دو محور. جابه‌جایی خود گره‌ها مختل نمی‌شود.
- برای هر گره API فقط `بدون توکن` یا `توکن اختصاصی همان گره` قابل انتخاب است. توکن در `jb_api_credentials` به‌صورت رمزگذاری‌شده نگهداری می‌شود.
- متغیر `JOURNEY_BUILDER_API_ALLOWED_HOSTS` حذف شد. نام هر دامنه عمومی HTTPS بدون تعریف دستی پذیرفته می‌شود، ولی مقصدهای داخلی/خصوصی و تغییر مسیرها برای جلوگیری از SSRF مجاز نیستند. PHP باید افزونه `curl` داشته باشد و درخواست با DNS Pinning انجام می‌شود.
- `JOURNEY_BUILDER_API_ENABLED` همچنان کلید فعال‌سازی صریح اتصال API است.

## نصب بدون تغییر ظاهر
۱. کدها را به مخزن GitHub پکیج Push کنید.
۲. روی پروژه میزبان Composer update و انتشار assetها را انجام دهید:

```powershell
composer update hadiabedzadeh/laravel-journey-builder
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan optimize:clear
```

## آزمایش پوسته به‌صورت اختیاری
فایل `journey-builder-pro.css` را کنار `style.css` در `public/vendor/journey-builder/` قرار دهید و **پس از** style.css در قالب View لود کنید:

```blade
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/style.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/journey-builder-pro.css') }}">
```

برای بازگشت به ظاهر قبلی فقط لینک CSS دوم را حذف کنید. اطلاعات دیتابیس و موتور جرنی تغییر نمی‌کنند. فایل فونت `vazirmatn-fanum.woff2` باید کنار CSS موجود باشد؛ فایل فونت در بسته قرار داده نشده است.

## نکات ارتقا
- هیچ Migration جدیدی نیاز نیست.
- جرنی‌هایی که از توکن `alias` محیطی استفاده می‌کردند، باید قبل از اجرای مجدد به توکن اختصاصی گره منتقل شوند.
- ارسال واقعی API را فقط بعد از تست روی محیط Staging فعال کنید؛ HTTP ناموفق/نامشخص باید طبق رویه قبلی بررسی شود.
- پشتیبانی از HTTPS روی پورت 443 است. مقصدهایی که فقط DNS خصوصی یا پورت‌های غیر استاندارد دارند رد می‌شوند.
- این پچ از نظر Syntax قابل بررسی است ولی تست End-to-End روی SQL Server و سرویس‌های واقعی انجام نشده است.
