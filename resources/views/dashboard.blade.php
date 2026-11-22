<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>ویرایش جرنی | {{ $flow->title }}</title><link rel="stylesheet" href="{{ asset('vendor/journey-builder/style.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/journey-builder/journey-builder-pro.css') }}?v=3.4.15">
</head><body>
@include('journey-builder::partials.nav', ['active' => 'journeys'])
<div class="editor-layout"><aside class="side"><h3>جعبه ابزار · Drag & Drop</h3><p class="help">Shift + چرخ موس: حرکت افقی · Alt + چرخ موس: حرکت عمودی</p>
<div class="palette"><div class="palette-label">ورود و شرط‌ها</div>
@foreach([['trigger','ورود از منبع داده','▶','#3182ee'],['condition','شرط وجود رویداد','◇','#e0a434'],['attribute','شرط ویژگی کاربر','◈','#e7a238'],['split','تقسیم درصدی مسیر','⑂','#9d6ddd']] as $item)<div class="palette-entry" draggable="true" data-type="{{ $item[0] }}" style="--accent:{{ $item[3] }}"><span class="iconbox">{{ $item[2] }}</span>{{ $item[1] }}</div>@endforeach
<div class="palette-label">زمان‌بندی و انتظار</div>
@foreach([['slot','بازه زمانی','◷','#ed9563'],['delay','تأخیر چندساعته','◴','#eb9463'],['wait_event','انتظار برای رویداد','⌛','#cf6d75'],['wait_onsite','انتظار مشاهده آنسایت','◴','#d67d6e']] as $item)<div class="palette-entry" draggable="true" data-type="{{ $item[0] }}" style="--accent:{{ $item[3] }}"><span class="iconbox">{{ $item[2] }}</span>{{ $item[1] }}</div>@endforeach
<div class="palette-label">عملیات و پایان</div>
@foreach([['sms','ارسال پیامک','✉','#14a985'],['email','ارسال ایمیل','✉','#7b61e8'],['webpush','وب‌پوش نوتیفیکیشن','◉','#0e9fbd'],['api','درخواست HTTP API','↗','#3677d6'],['onsite','افزودن آنسایت','▣','#10a088'],['exit','خروج از جرنی','■','#8795a8']] as $item)<div class="palette-entry" draggable="true" data-type="{{ $item[0] }}" style="--accent:{{ $item[3] }}"><span class="iconbox">{{ $item[2] }}</span>{{ $item[1] }}</div>@endforeach</div>

</aside><main class="workspace"><header class="editor-toolbar"><h2 id="flow-title">{{ $flow->title }}</h2><span class="badge {{ $flow->status }}">{{ ['active'=>'فعال','paused'=>'غیرفعال','draft'=>'پیش‌نویس'][$flow->status]??$flow->status }}</span><button class="btn primary" id="save" {{ !$canEdit?'disabled':'' }}>ذخیره تغییرات</button><a class="btn" href="{{ route('jb.show',$flow->id) }}">گزارش جرنی</a><button class="btn" id="refresh">به‌روزرسانی</button><div class="jb-canvas-controls" role="group" aria-label="ابزارهای نمایش بوم"><button class="btn" type="button" id="jb-zoom-out" title="کوچک‌نمایی">−</button><span id="jb-zoom-label" aria-live="polite">۱۰۰٪</span><button class="btn" type="button" id="jb-zoom-in" title="بزرگ‌نمایی">+</button><button class="btn" type="button" id="jb-fit-all">نمایش همه گره‌ها</button><button class="btn" type="button" id="jb-expand-editor" aria-pressed="false">بوم تمام‌صفحه</button></div></header>
<div class="viewport" id="viewport"><div class="board-shell" id="board-shell"><div class="board" id="board"><svg id="wires" class="wires" role="img" aria-label="اتصال مراحل"></svg><div id="nodes"></div></div></div></div>
<div class="jb-minimap-panel" id="jb-minimap-panel" aria-label="نمای کلی جرنی">
  <div class="jb-minimap-header"><strong>نمای کلی جرنی</strong></div>
  <canvas id="jb-minimap" width="252" height="160" tabindex="0" aria-label="نقشه کوچک؛ برای جابه‌جایی بوم کلیک یا درگ کنید"></canvas>
  <small class="jb-minimap-hint">باکس آبی: قسمت قابل مشاهده · کلیک و کشیدن برای حرکت</small>
</div>
</main>
<aside class="side"><h3>تنظیمات جرنی</h3>@if($canEdit)<div class="field"><label for="edit-title">نام نمایشی</label><input id="edit-title" value="{{ $flow->title }}" maxlength="200"></div>@else@endif
<hr style="border:0;border-top:1px solid #e3e9f3;margin:20px 0"><h3>تنظیمات مرحله انتخاب‌شده</h3><div class="inspector" id="inspector"></div></aside></div><div class="jb-preview-backdrop" id="jb-preview-backdrop" hidden>
  <section class="jb-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="jb-preview-heading" aria-describedby="jb-preview-description" dir="rtl" tabindex="-1">
    <header class="jb-preview-dialog-head">
      <div><div class="jb-preview-eyebrow">نمایش آزمایشی محتوا</div><h2 id="jb-preview-heading">پیش‌نمایش پیام</h2></div>
      <button type="button" class="jb-preview-close" id="jb-preview-close" aria-label="بستن پیش‌نمایش">×</button>
    </header>
    <p class="jb-preview-description" id="jb-preview-description"></p>
    <div class="jb-preview-content" id="jb-preview-content"></div>
    <footer class="jb-preview-dialog-foot"><span>محتوا فقط در مرورگر شما نمایش داده می‌شود.</span><button type="button" class="btn" id="jb-preview-done">بستن</button></footer>
  </section>
</div>
<div class="toast" id="toast" role="status"></div>
@php($ui=['id'=>$flow->id,'readonly'=>!$canEdit,'graph'=>json_decode($flow->graph_json,true),'saveUrl'=>route('jb.save',$flow->id),'secretNodeIds'=>$secretNodeIds,'tokenUrlPattern'=>route('jb.token',['id'=>$flow->id,'nodeId'=>'__NODE__']),'smsProviders'=>$smsProviders,'emailProviders'=>$emailProviders,'pushProviders'=>$pushProviders,'onsites'=>$onsites,'onsitesError'=>$onsitesError,'smsProvidersUrl'=>route('jb.sms.providers'),'emailProvidersUrl'=>route('jb.channels.index','email'),'pushProvidersUrl'=>route('jb.channels.index','webpush')])
<script>window.JB_UI={{ \Illuminate\Support\Js::from($ui) }};</script><script src="{{ asset('vendor/journey-builder/builder.js') }}?v=3.4.15" defer></script>@include('journey-builder::partials.footer')
</body></html>
