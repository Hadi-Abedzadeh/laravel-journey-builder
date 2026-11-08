<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Only explicit, allowlisted attribute placeholders are interpolated; no PHP/eval/templates. */
final class SmsTemplateRenderer
{
    public function receptor(string $userId, object $provider): string
    {
        $attribute = (string) $provider->receptor_attribute;
        $value = $this->attribute($userId, $attribute, (string) ($provider->source_sign ?? ''));
        $value = strtr(trim((string) $value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        // Canonical domestic mobile number for reliable response matching.
        if (preg_match('/^\+989(\d{9})$/D', $value, $m) || preg_match('/^00989(\d{9})$/D', $value, $m)) {
            return '09'.$m[1];
        }
        if (preg_match('/^9\d{9}$/D', $value)) return '0'.$value;
        if (!preg_match('/^09\d{9}$/D', $value)) throw new RuntimeException('SMS receptor is missing or invalid for the configured attribute.');
        return $value;
    }

    public function render(string $userId, string $message): string
    {
        $keys = [];
        if (preg_match_all('/\{\{\s*([A-Za-z][A-Za-z0-9_]{0,49})\s*\}\}/', $message, $matches)) {
            $keys = array_unique($matches[1]);
        }
        $values = [];
        $attributeKeys = array_values(array_diff($keys, ['userId']));
        $attributes = [];
        if ($attributeKeys) {
            $query = TypedWhere::eq(TypedWhere::eq(
                DB::connection(config('journey_builder.connection', 'sqlsrv'))->table(config('journey_builder.source_table','member_data')),
                'userId', $userId, 100), 'section', 'attributes', 20)
                ->whereIn('key', $attributeKeys)
                ->orderByDesc('updated_at')->orderByDesc('id');
            foreach ($query->get(['key','value']) as $row) {
                // Query is newest first. One lookup for all message placeholders.
                if (!array_key_exists($row->key,$attributes)) $attributes[$row->key]=$row->value;
                if (count($attributes) === count($attributeKeys)) break;
            }
        }
        foreach ($keys as $key) {
            $value = $key === 'userId' ? $userId : ($attributes[$key] ?? null);
            if ($value === null || $value === '') throw new RuntimeException('SMS placeholder has no value: '.$key);
            $values[$key] = (string) $value;
        }
        $rendered = preg_replace_callback('/\{\{\s*([A-Za-z][A-Za-z0-9_]{0,49})\s*\}\}/',
            static fn (array $match) => $values[$match[1]] ?? '', $message);
        if (str_contains($rendered, '{{') || str_contains($rendered, '}}')) throw new RuntimeException('Unknown or malformed SMS placeholder.');
        $rendered = trim($rendered);
        if ($rendered === '' || mb_strlen($rendered) > 1800) throw new RuntimeException('SMS message must be between 1 and 1800 characters.');
        return $rendered;
    }

    private function attribute(string $userId, string $key, string $sign = ''): ?string
    {
        $q = TypedWhere::eq(TypedWhere::eq(
            DB::connection(config('journey_builder.connection', 'sqlsrv'))->table(config('journey_builder.source_table','member_data')),
            'userId', $userId, 100), 'section', 'attributes', 20)->where('key', $key);
        if ($sign !== '') TypedWhere::eq($q, 'sign', $sign, 30);
        $value = $q->orderByDesc('updated_at')->orderByDesc('id')->value('value');
        return $value === null ? null : (string) $value;
    }
}
