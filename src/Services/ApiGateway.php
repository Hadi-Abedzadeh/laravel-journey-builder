<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ApiGateway
{
    public static function isAllowedUrl(string $url): bool
    {
        if (strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' ||
            !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) ||
            (isset($parts['port']) && (int)$parts['port'] !== 443)) return false;
        $host = strtolower(rtrim($parts['host'], '.'));
        if (filter_var($host, FILTER_VALIDATE_IP) || !preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/D', $host)) return false;
        $allowed = array_map(fn($h) => strtolower(rtrim(trim($h), '.')), config('journey_builder.api.allowed_hosts', []));
        return in_array($host, $allowed, true);
    }

    public function send(object $run, object $flow, array $config, string $dedupeKey): array
    {
        $url = (string)($config['url'] ?? '');
        if (!config('journey_builder.api.enabled') || !self::isAllowedUrl($url)) {
            return ['status' => 'failed', 'http_status' => null, 'error' => 'API disabled or endpoint host not allowed'];
        }
        // Secrets never belong in the graph or responses; resolve credentials server-side.
        $alias = (string)($config['token_alias'] ?? '');
        $source = (string)($config['token_source'] ?? ($alias !== '' ? 'alias' : 'none'));
        $tokens = config('journey_builder.api.tokens', []);
        $token = null;
        if ($source === 'alias') {
            if ($alias === '' || !isset($tokens[$alias])) return ['status'=>'failed','http_status'=>null,'error'=>'Undefined API token alias'];
            $token = $tokens[$alias];
        } elseif ($source === 'secret') {
            $encrypted = TypedWhere::eq(DB::connection(config('journey_builder.connection','sqlsrv'))
                ->table('jb_api_credentials')->where('flow_id', $flow->id), 'node_id', $run->node_id, 80)->value('encrypted_token');
            if (!is_string($encrypted) || $encrypted === '') return ['status'=>'failed','http_status'=>null,'error'=>'API token not configured'];
            try { $token = Crypt::decryptString($encrypted); }
            catch (\Throwable $e) { return ['status'=>'failed','http_status'=>null,'error'=>'API token could not be decrypted']; }
        } elseif ($source !== 'none') {
            return ['status'=>'failed','http_status'=>null,'error'=>'Unsupported token source'];
        }

        $body = json_decode($config['body'] ?? '{}', false, 32, JSON_THROW_ON_ERROR);
        $placeholders = [
            '{{userId}}' => $run->user_id,
            '{{flowCode}}' => $flow->code,
            '{{flowId}}' => (string)$flow->id,
            '{{runId}}' => (string)$run->id,
            '{{nodeId}}' => $run->node_id,
        ];
        $replace = function ($value) use (&$replace, $placeholders) {
            if (is_array($value)) return array_map($replace, $value);
            if (is_object($value)) { foreach ($value as $key => $item) $value->$key = $replace($item); return $value; }
            if (is_string($value)) return strtr($value, $placeholders);
            return $value;
        };
        $body = $replace($body);
        try {
            $req = Http::asJson()->connectTimeout(5)
                ->timeout((int) config('journey_builder.api.timeout', 15))
                ->withoutRedirecting()->withHeaders(['X-Idempotency-Key' => $dedupeKey]);
            if ($token !== null) {
                $header = $config['token_header'] ?? 'bearer';
                if ($header === 'x-api-key') $req = $req->withHeaders(['X-API-Key' => $token]);
                elseif ($header === 'custom') {
                    $name = (string)($config['token_custom_header'] ?? '');
                    if (!preg_match('/^X-[A-Za-z0-9-]{1,60}$/D', $name))
                        return ['status'=>'failed','http_status'=>null,'error'=>'Invalid custom API token header'];
                    $req = $req->withHeaders([$name => $token]);
                }
                else $req = $req->withToken($token);
            }
            $response = $req->send($config['method'] ?? 'POST', $url, ['json' => $body]);
            // 3xx is not success: redirects intentionally disabled to avoid credential leaks.
            if ($response->successful()) return ['status'=>'sent','http_status'=>$response->status(),'error'=>null];
            return ['status'=>'failed','http_status'=>$response->status(),'error'=>'HTTP '.$response->status()];
        } catch (\Throwable $e) {
            report($e);
            // The endpoint may have completed this request before timeout.
            return ['status'=>'unknown','http_status'=>null,'error'=>mb_substr($e->getMessage(),0,700)];
        }
    }
}
