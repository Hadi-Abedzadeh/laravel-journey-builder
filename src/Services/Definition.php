<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Validation\ValidationException;

final class Definition
{
    public const PORTS = [
        'trigger' => ['next'], 'slot' => ['next'], 'delay' => ['next'],
        'condition' => ['yes', 'no'], 'attribute' => ['yes', 'no'],
        'wait_event' => ['event', 'timeout'], 'split' => ['a', 'b'],
        'sms' => ['success', 'failure'], 'api' => ['success', 'failure'], 'exit' => [],
    ];

    public static function blank(): array
    {
        return [
            'nodes' => [
                ['id' => 'start', 'type' => 'trigger', 'x' => 230, 'y' => 95,
                    'config' => ['section' => 'attributes', 'sign' => '', 'key' => '', 'operator' => 'exists', 'value' => '']],
                ['id' => 'finish', 'type' => 'exit', 'x' => 230, 'y' => 310, 'config' => []],
            ],
            'edges' => [['id' => 'start_to_finish', 'source' => 'start', 'port' => 'next', 'target' => 'finish']],
        ];
    }

    // Drafts can contain unfinished trigger settings; activation requires full validation.
    public static function validate(array $graph, bool $publish = true): array
    {
        $nodes = $graph['nodes'] ?? null;
        $edges = $graph['edges'] ?? null;
        if (!is_array($nodes) || !is_array($edges) || count($nodes) < 2 || count($nodes) > 100 || count($edges) > 250) {
            self::fail('گراف باید بین ۲ تا ۱۰۰ گره و حداکثر ۲۵۰ اتصال داشته باشد.');
        }
        $byId = []; $starts = 0;
        foreach ($nodes as $node) {
            if (!is_array($node) || !is_string($node['id'] ?? null) ||
                !preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $node['id']) || isset($byId[$node['id']])) {
                self::fail('شناسه گره نامعتبر یا تکراری است.');
            }
            $type = $node['type'] ?? '';
            if (!isset(self::PORTS[$type])) self::fail('نوع گره ناشناخته است.');
            $cfg = $node['config'] ?? [];
            if (!is_array($cfg)) self::fail('تنظیمات گره نامعتبر است.');
            if (!is_numeric($node['x'] ?? null) || !is_numeric($node['y'] ?? null) ||
                (float)$node['x'] < 0 || (float)$node['y'] < 0 || (float)$node['x'] > 10000 || (float)$node['y'] > 10000) {
                self::fail('مختصات گره نامعتبر است.');
            }
            if ($type === 'trigger') {
                $starts++;
                if (!in_array($cfg['section'] ?? '', ['attributes', 'events'], true)) self::fail('منبع ورود باید attributes یا events باشد.');
                self::short($cfg['sign'] ?? '', 30, 'sign');
                self::short($cfg['key'] ?? '', 50, 'key');
                if ($publish && trim((string)($cfg['key'] ?? '')) === '') self::fail('برای ورود کاربران، کلید منبع (key) را تعیین کنید.');
                if (!in_array($cfg['operator'] ?? '', ['exists', 'equals', 'not_equals', 'starts_with', 'not_starts_with'], true)) self::fail('عملگر ورودی نامعتبر است.');
                self::short($cfg['value'] ?? '', 1000, 'value');
                if ($publish && !in_array($cfg['operator'], ['exists'], true) && ($cfg['value'] ?? '') === '') self::fail('مقدار فیلتر ورودی خالی است.');
            }
            if ($type === 'condition' || $type === 'wait_event') {
                self::short($cfg['event'] ?? '', 50, 'event name');
                self::short($cfg['sign'] ?? '', 30, 'event sign');
                if ($publish && trim((string)($cfg['event'] ?? '')) === '') self::fail('نام رویداد را وارد کنید.');
                if ($type === 'condition' && !in_array($cfg['scope'] ?? 'ever', ['ever', 'after_enroll'], true)) self::fail('محدوده بررسی رویداد نامعتبر است.');
            }
            if ($type === 'attribute') {
                self::short($cfg['key'] ?? '', 50, 'attribute key');
                self::short($cfg['sign'] ?? '', 30, 'attribute sign');
                self::short($cfg['value'] ?? '', 1000, 'attribute value');
                if ($publish && trim((string)($cfg['key'] ?? '')) === '') self::fail('کلید ویژگی را وارد کنید.');
                if (!in_array($cfg['operator'] ?? '', ['exists','not_exists','equals','not_equals','starts_with','not_starts_with'],true)) self::fail('عملگر ویژگی نامعتبر است.');
            }
            if ($type === 'sms') {
                $mode = $cfg['delivery_mode'] ?? 'legacy'; // Older stored graphs stay on legacy gateway.
                if (!in_array($mode, ['managed', 'legacy'], true)) self::fail('روش ارسال پیامک نامعتبر است.');
                $allowed = ['delivery_mode','template','message','provider_id'];
                if (array_diff(array_keys($cfg), $allowed)) self::fail('تنظیمات ناشناخته گره پیامک.');
                self::short($cfg['template'] ?? '', 150, 'sms template');
                self::short($cfg['message'] ?? '', 1800, 'sms message');
                if ($mode === 'managed') {
                    $id = $cfg['provider_id'] ?? 0;
                    if ((!is_int($id) && !ctype_digit((string)$id)) || (int)$id < 0) self::fail('پنل انتخاب‌شده برای پیامک معتبر نیست.');
                    if ($publish && trim((string)($cfg['message'] ?? '')) === '') self::fail('متن پیامک اجباری است.');
                    $unmatched = preg_replace('/\{\{\s*[A-Za-z][A-Za-z0-9_]{0,49}\s*\}\}/','',(string)($cfg['message']??''));
                    if (str_contains($unmatched,'{{') || str_contains($unmatched,'}}')) {
                        self::fail('متغیرهای SMS باید به‌صورت {{firstName}} باشند.');
                    }
                } elseif ($publish && !preg_match('/^[A-Za-z0-9_.-]{1,150}$/D', $cfg['template'] ?? '')) {
                    self::fail('کد قالب پیامک نسخه قدیمی معتبر نیست.');
                }
            }
            if ($type === 'api') {
                self::short($cfg['url'] ?? '', 1000, 'API URL');
                if ($publish && !ApiGateway::isAllowedUrl((string)($cfg['url'] ?? ''))) self::fail('آدرس API باید HTTPS و روی یک دامنه مجاز در تنظیمات سرور باشد.');
                if (!in_array($cfg['method'] ?? 'POST', ['POST', 'PUT', 'PATCH'], true)) self::fail('متد API معتبر نیست.');
                // Explicit allowlist: no plaintext secret can be smuggled into graph_json.
                $allowedApiConfig = ['url','method','token_source','token_alias','token_header','token_custom_header','body'];
                if (array_diff(array_keys($cfg), $allowedApiConfig)) self::fail('تنظیمات ناشناخته API مجاز نیست؛ توکن را در بخش امن ثبت کنید.');
                self::short($cfg['token_alias'] ?? '', 60, 'API token alias');
                $source = $cfg['token_source'] ?? (($cfg['token_alias'] ?? '') !== '' ? 'alias' : 'none');
                if (!in_array($source, ['none','alias','secret'], true)) self::fail('روش احراز هویت API معتبر نیست.');
                if ($publish && $source === 'alias' && !array_key_exists($cfg['token_alias'] ?? '', config('journey_builder.api.tokens', []))) self::fail('کلید API انتخاب‌شده تعریف نشده است.');
                if (!in_array($cfg['token_header'] ?? 'bearer', ['bearer','x-api-key','custom'], true)) self::fail('نوع هدر توکن نامعتبر است.');
                self::short($cfg['token_custom_header'] ?? '', 64, 'API token header');
                if (($cfg['token_header'] ?? '') === 'custom' && $source !== 'none' &&
                    !preg_match('/^X-[A-Za-z0-9-]{1,60}$/D', $cfg['token_custom_header'] ?? '')) self::fail('نام هدر اختصاصی باید با X- شروع شود.');
                $body = $cfg['body'] ?? '{}';
                if (!is_string($body) || strlen($body) > 6000) self::fail('بدنه درخواست API طولانی یا نامعتبر است.');
                $parsed = json_decode($body);
                if (json_last_error() !== JSON_ERROR_NONE || !is_object($parsed)) self::fail('بدنه API باید JSON Object معتبر باشد.');
            }
            if ($type === 'split' && (!is_numeric($cfg['percent'] ?? null) || (int)$cfg['percent'] < 0 || (int)$cfg['percent'] > 100)) self::fail('درصد مسیر A باید ۰ تا ۱۰۰ باشد.');
            if (in_array($type, ['delay','wait_event'], true) && (!is_numeric($cfg['hours'] ?? null) || (int)$cfg['hours'] < 1 || (int)$cfg['hours'] > 2160)) self::fail('مدت انتظار باید ۱ تا ۲۱۶۰ ساعت باشد.');
            if ($type === 'slot') {
                $days = $cfg['days'] ?? [];
                if (!is_array($days) || !$days || count($days) > 7 || count(array_filter($days, fn($d) => !is_int($d) || $d < 0 || $d > 6)) ||
                    !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $cfg['from'] ?? '') ||
                    !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $cfg['to'] ?? '') || $cfg['to'] <= $cfg['from']) self::fail('بازه زمانی نامعتبر است.');
            }
            $byId[$node['id']] = $node;
        }
        if ($starts !== 1) self::fail('دقیقاً یک گره شروع لازم است.');
        $links = [];
        foreach ($edges as $edge) {
            if (!is_array($edge) || !isset($byId[$edge['source'] ?? ''], $byId[$edge['target'] ?? '']) ||
                !in_array($edge['port'] ?? '', self::PORTS[$byId[$edge['source']]['type']], true) ||
                $edge['source'] === $edge['target']) self::fail('اتصال نامعتبر است.');
            $slot = $edge['source'].'|'.$edge['port'];
            if (isset($links[$slot])) self::fail('از هر خروجی فقط یک اتصال مجاز است.');
            $links[$slot] = $edge['target'];
        }
        // In drafts only incomplete outputs can be missing. An active flow must be completely wired.
        if ($publish) foreach ($byId as $id => $node) foreach (self::PORTS[$node['type']] as $port) {
            if (!isset($links[$id.'|'.$port])) self::fail("خروجی {$port} از گره {$id} به جایی متصل نیست.");
        }
        if (!count(array_filter($nodes, fn($n) => $n['type'] === 'exit'))) self::fail('حداقل یک گره خروج لازم است.');
        if ($publish) {
            $startId = array_values(array_filter($nodes, fn($n) => $n['type'] === 'trigger'))[0]['id'];
            $color = [];
            $walk = function ($id) use (&$walk, &$color, $links, $byId) {
                if (($color[$id] ?? 0) === 1) self::fail('گراف نباید چرخه داشته باشد.');
                if (($color[$id] ?? 0) === 2) return;
                $color[$id] = 1;
                foreach (self::PORTS[$byId[$id]['type']] as $port) $walk($links[$id.'|'.$port]);
                $color[$id] = 2;
            };
            $walk($startId);
            if (count($color) !== count($byId)) self::fail('تمام گره‌ها باید از شروع قابل دسترسی باشند.');
        }
        return ['nodes' => array_values($nodes), 'edges' => array_values($edges)];
    }

    private static function short(mixed $value, int $max, string $name): void
    {
        if (!is_string($value) || mb_strlen($value) > $max) self::fail("مقدار {$name} نامعتبر است.");
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['graph' => $message]);
    }
}
