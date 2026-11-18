# Journey Builder — v0.3.4-beta.9

## تغییرات

- امکان ثبت حداکثر ۱۲ هدر متنی اضافه برای هر گره HTTP API، با کلید/مقدار مستقل
- پشتیبانی از `Authorization: Basic` در محل رمزگذاری‌شده‌ی توکن هر گره
- `Content-Type: application/json` خودکار است؛ هدرهای غیرمحرمانه‌ی اضافه را می‌توان از داشبورد ثبت کرد
- متغیرهای `{{phone}}` و سایر Attributeهای `member_data` در مقادیر رشته‌ای JSON درخواست API به آخرین مقدارشان تبدیل می‌شوند
- رد کردن فرمت WebEngage مانند `{{user["system"]["phone"]}}` (باید `{{phone}}` استفاده شود)
- کارت‌های جعبه‌ابزار طراح کوچک‌تر شدند؛ ابعاد گره‌های بوم تغییر نکرده

## مثال تنظیم CRM

URL نمونه ارائه‌شده `http://crmapi.irfarabi.net:91/...` با مقررات امنیتی فعلی پکیج **پشتیبانی نمی‌شود**: API Gateway فقط HTTPS دامنه عمومی روی پورت ۴۴۳ را می‌پذیرد. برای اجرای این درخواست، ابتدا یک ورودی امن HTTPS برای سرویس فراهم کنید. کلید واقعی Basic را داخل فیلد توکن اختصاصی وارد کنید، نه JSON گراف.

### تنظیم فرم

1. `method = POST`
2. `token_source = secret`
3. `token_header = basic`
4. در فیلد Token صرفاً بخش Base64 (بدون `Basic `) را وارد کنید.
5. هدر Content-Type به‌صورت خودکار JSON می‌شود؛ هدرهای بیشتر از قسمت «افزودن هدر» تعریف می‌شوند.
6. متن نمونه درخواست:

```json
{
  "Mobile": "{{phone}}",
  "Subject": "مارکتینگ - پیگیری تکمیل ثبت نام - مرحله 1",
  "Body": "ثبت‌نام شما ناقص مانده است!\n\nبا تکمیل ثبت‌نام خود در فارابی، از فرصت‌های جدید سرمایه‌گذاری بهره‌مند شوید.\nhttps://account.irfarabi.com/reg/?utm_source=31hp904&utm_medium=MAJ-SMS&utm_campaign=General-Signup"
}
```

## اتصالات

هر خروجی گره فقط یک اتصال دارد؛ اما خروجی‌های جدا مانند success/failure می‌توانند به مقصدهای متفاوت وصل شوند. قابلیت Fork موازی در این نسخه وجود ندارد.

## آپدیت

پس از ادغام تغییرات در Repository و بررسی تست‌ها:

```powershell
git add .
git commit -m "Support API Basic auth, multiple headers and compact toolbox"
git push origin main
git tag -a v0.3.4-beta.9 -m "Journey Builder API request improvements"
git push origin v0.3.4-beta.9
```

سپس پس از شناسایی Tag در Packagist:

```powershell
cd D:\datasyncer
composer require "hadiabedzadeh/laravel-journey-builder:0.3.4-beta.9"
php artisan vendor:publish --tag=journey-builder-assets --force
php artisan view:clear
php artisan optimize:clear
```

نیازی به Migration نیست. برای حساب‌های واقعی از HTTPS و توکن تعویض‌شده استفاده کنید. توکن Basic درج‌شده در گفتگو نباید در فایل‌های سورس یا README گنجانده شود.
