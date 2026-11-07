<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SmsGateway
{
    /** No network retry: a timeout might mean the provider accepted the SMS. */
    public function send(string $userId, string $template, string $message, string $dedupeKey): array
    {
        $cfg = config('journey_builder.sms');
        if (!($cfg['enabled'] ?? false) || empty($cfg['url'])) {
            return ['status'=>'failed', 'http_status'=>null,'error'=>'SMS gateway is disabled or URL is missing'];
        }
        $payload = ['userId'=>$userId, 'template'=>$template];
        if (trim($message) !== '') $payload['message'] = $message;
        if ($cfg['include_phone'] ?? false) {
            $payload['phone'] = TypedWhere::eq(
                TypedWhere::eq(DB::connection(config('journey_builder.connection'))
                ->table(config('journey_builder.source_table', 'member_data')), 'userId', $userId, 100),
                'section', 'attributes', 20)
                ->where('key', 'phone')->orderByDesc('updated_at')->orderByDesc('id')->value('value');
        }
        try {
            $request = Http::asJson()->connectTimeout(5)->timeout((int)($cfg['timeout'] ?? 15))
                ->withHeaders(['X-Idempotency-Key'=>$dedupeKey]);
            if (!empty($cfg['token'])) $request = $request->withToken($cfg['token']);
            $response = $request->post($cfg['url'], $payload);
            if ($response->successful()) return ['status'=>'sent','http_status'=>$response->status(),'error'=>null];
            return ['status'=>'failed','http_status'=>$response->status(),
                'error'=>'HTTP '.$response->status()];
        } catch (\Throwable $e) {
            // Ambiguous delivery. Do not retry automatically; human reconciliation required.
            report($e);
            return ['status'=>'unknown','http_status'=>null,'error'=>mb_substr($e->getMessage(),0,1000)];
        }
    }
}
