<nav class="nav" aria-label="ناوبری اصلی Journey Builder">
    <a class="nav-brand" href="{{ route('jb.index') }}">◈ Journey Builder</a>
    <a href="{{ route('jb.index') }}" @class(['current' => ($active ?? '') === 'journeys'])>مدیریت جرنی‌ها</a>
    <a href="{{ route('jb.sms') }}" @class(['current' => ($active ?? '') === 'sms'])>مرکز پیامک</a>
    <a href="{{ route('jb.sms.providers') }}" @class(['current' => ($active ?? '') === 'providers'])>پنل‌های پیامکی</a>
    <a href="{{ route('jb.monitoring') }}" @class(['current' => ($active ?? '') === 'monitoring'])>لاگ و نظارت</a>
    <div class="spacer"></div>
    <span class="nav-product" aria-hidden="true">Workflow Automation</span>
</nav>
