<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ApiGateway
{
    /**
     * No configured host allowlist: any PUBLIC HTTPS DNS hostname is accepted.
     * Reject IP literals, localhost names, userinfo, redirects and custom ports.
     * DNS resolution is checked separately at send time to avoid SSRF.
     */
    public static function isAllowedUrl(string $url): bool
    {
        if (strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' ||
            !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) ||
            (isset($parts['port']) && (int)$parts['port'] !== 443)) return false;
        $host = strtolower(rtrim($parts['host'], '.'));
        if (filter_var($host, FILTER_VALIDATE_IP) || strlen($host) > 253 ||
            !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)) return false;
        $tld = substr(strrchr($host, '.'), 1);
        return !in_array($tld, ['localhost','local','internal','test','invalid','example'], true);
    }

    private static function inSubnet(string $ip, string $network, int $bits): bool
    {
        $a = @inet_pton($ip);
        $b = @inet_pton($network);
        if (!is_string($a) || !is_string($b) || strlen($a) !== strlen($b)) return false;
        $whole = intdiv($bits, 8);
        if (substr($a, 0, $whole) !== substr($b, 0, $whole)) return false;
        $remaining = $bits % 8;
        if ($remaining === 0) return true;
        $mask = (0xff << (8 - $remaining)) & 0xff;
        return (ord($a[$whole]) & $mask) === (ord($b[$whole]) & $mask);
    }

    private static function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        // Additional special-use ranges that can be reachable on private networks.
        $special = str_contains($ip, ':')
            ? [['::ffff:0:0',96], ['64:ff9b::',96], ['64:ff9b:1::',48], ['2001::',32], ['2002::',16]]
            : [['100.64.0.0',10], ['198.18.0.0',15], ['192.0.0.0',24], ['192.88.99.0',24]];
        foreach ($special as [$range, $bits]) {
            if (self::inSubnet($ip, $range, $bits)) return false;
        }
        return true;
    }

    /**
     * Resolve at the moment of sending, reject ALL private/reserved DNS answers,
     * and pin libcurl to the vetted IP so later DNS rebinding cannot redirect the call.
     */
    private static function publicIpFor(string $host): ?string
    {
        if (!function_exists('dns_get_record')) return null;
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records) || $records === []) return null;
        $ips = [];
        foreach ($records as $record) {
            $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip === '') continue;
            // Includes loopback, RFC1918, link-local, ULA and other special ranges.
            if (!self::isPublicIp($ip)) return null;
            $ips[] = $ip;
        }
        if ($ips === []) return null;
        // IPv4 first where available; IPv6 is also allowed when public.
        usort($ips, static fn($a, $b) => (int)!filter_var($a, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            <=> (int)!filter_var($b, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
        return $ips[0];
    }

    public function send(object $run, object $flow, array $config, string $dedupeKey): array
    {
        $url = (string)($config['url'] ?? '');
        if (!config('journey_builder.api.enabled') || !self::isAllowedUrl($url)) {
            return ['status' => 'failed', 'http_status' => null, 'error' => 'API disabled or invalid public HTTPS endpoint'];
        }
        // Requests must not reach internal/cloud-metadata IPs via arbitrary URLs.
        // Avoid TOCTOU DNS rebinding by passing CURLOPT_RESOLVE to cURL.
        if (!extension_loaded('curl') || !defined('CURLOPT_RESOLVE')) {
            return ['status'=>'failed','http_status'=>null,'error'=>'PHP cURL extension is required for safe external API requests'];
        }
        $host = strtolower(rtrim(parse_url($url, PHP_URL_HOST), '.'));
        $publicIp = self::publicIpFor($host);
        if ($publicIp === null) {
            return ['status'=>'failed','http_status'=>null,'error'=>'Endpoint DNS did not resolve exclusively to public IPs'];
        }

        // Secrets never belong in the graph or responses; resolve credentials server-side.
        $source = (string)($config['token_source'] ?? 'none');
        $token = null;
        if ($source === 'secret') {
            // Runs on earlier revisions must use the matching encrypted token snapshot.
            $historical = (int)$run->revision !== (int)$flow->revision;
            $credentialTable = $historical ? 'jb_revision_credentials' : 'jb_api_credentials';
            $credentialQuery = DB::connection(config('journey_builder.connection','sqlsrv'))
                ->table($credentialTable)->where('flow_id', $flow->id);
            if ($historical) $credentialQuery->where('revision', $run->revision);
            $encrypted = TypedWhere::eq($credentialQuery, 'node_id', $run->node_id, 80)->value('encrypted_token');
            if (!is_string($encrypted) || $encrypted === '') return ['status'=>'failed','http_status'=>null,'error'=>'API token not configured'];
            try { $token = Crypt::decryptString($encrypted); }
            catch (\Throwable $e) { return ['status'=>'failed','http_status'=>null,'error'=>'API token could not be decrypted']; }
        } elseif ($source !== 'none') {
            return ['status'=>'failed','http_status'=>null,'error'=>'Legacy ENV token source is not supported; configure a per-node secret'];
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
                ->withoutRedirecting()->withOptions([
                    'curl' => [
                        CURLOPT_RESOLVE => [$host.':443:'.(str_contains($publicIp, ':') ? '['.$publicIp.']' : $publicIp)],
                        CURLOPT_NOPROXY => '*',
                    ],
                    'allow_redirects' => false,
                ])->withHeaders(['X-Idempotency-Key' => $dedupeKey]);
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
