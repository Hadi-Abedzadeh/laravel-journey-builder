<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** The Kavenegar endpoint is fixed; credentials are never taken from node URLs. */
final class KavenegarClient
{
    /** @param array<object> $messages A single provider; maximum 200 rows. */
    public function sendArray(object $provider, array $messages): array
    {
        if (count($messages) < 1 || count($messages) > 200) throw new RuntimeException('Kavenegar sendarray accepts 1..200 messages.');
        $ids = array_map(static fn ($row) => (int) $row->id, $messages);
        try {
            $key = Crypt::decryptString($provider->encrypted_api_key);
            if ($key === '' || strlen($key) > 2048 || !preg_match('/^[A-Za-z0-9_-]+$/D', $key)) {
                throw new RuntimeException('Invalid Kavenegar API key.');
            }
            $url = 'https://api.kavenegar.com/v1/'.rawurlencode($key).'/sms/sendarray.json';
            // Kavenegar expects JSON-serialized arrays inside POST form fields.
            // localmessageids makes repeat requests idempotent on the provider side.
            $response = Http::asForm()->connectTimeout(5)
                ->timeout((int) config('journey_builder.sms.timeout', 20))
                ->post($url, [
                    'receptor' => json_encode(array_map(static fn ($r) => $r->receptor, $messages), JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                    'sender' => json_encode(array_map(static fn ($r) => $r->sender, $messages), JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                    'message' => json_encode(array_map(static fn ($r) => $r->message_text, $messages), JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                    'localmessageids' => implode(',', array_map(fn ($row) => $this->localMessageId($provider, (int)$row->id, (int)($row->attempts ?? 1)), $messages)),
                ]);
            $http = $response->status();
            $json = $response->json();
            $status = (int) data_get($json, 'return.status', 0);
            if ($response->successful() && $status === 200 && is_array($entries = data_get($json, 'entries'))) {
                return $this->matchEntries($messages, $entries, $http);
            }
            // Server overload / malformed success: accepted state cannot be established.
            $state = ($http >= 500 || $status >= 500 || $http === 409 || $status === 409 || $status === 0) ? 'unknown' : 'failed';
            return $this->uniform($ids, $state, $http, 'Kavenegar rejected batch; provider status: '.$status);
        } catch (\Throwable $e) {
            // Do not report the raw exception: the request URL contains the secret API key.
            return $this->uniform($ids, 'unknown', null, 'Kavenegar response unavailable; reconcile before retry.');
        }
    }

    /** Get delivery information for up to 500 Kavenegar message IDs, keyed by ID. */
    public function statuses(object $provider, array $messageIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $messageIds)));
        if (!$ids || count($ids) > 500 || min($ids) <= 0) {
            throw new RuntimeException('SMS receipt polling requires 1..500 positive message IDs.');
        }
        try {
            $key = Crypt::decryptString($provider->encrypted_api_key);
            if ($key === '' || strlen($key) > 2048 || !preg_match('/^[A-Za-z0-9_-]+$/D', $key)) {
                throw new RuntimeException('Invalid Kavenegar API key.');
            }
            $response = Http::connectTimeout(5)->timeout((int) config('journey_builder.sms.timeout', 20))
                ->get('https://api.kavenegar.com/v1/'.rawurlencode($key).'/sms/status.json', [
                    'messageid' => implode(',', $ids),
                ]);
            if (!$response->successful() || (int)data_get($response->json(),'return.status',0) !== 200) {
                return [];
            }
            $entries = data_get($response->json(),'entries',[]);
            if (!is_array($entries)) return [];
            $expected = array_flip(array_map('strval', $ids));
            $result = [];
            foreach ($entries as $entry) {
                if (!is_array($entry) || !isset($entry['messageid'],$entry['status'])) continue;
                $id = (string)$entry['messageid'];
                if (!isset($expected[$id]) || !is_numeric($entry['status'])) continue;
                $result[$id] = (int)$entry['status'];
            }
            return $result;
        } catch (\Throwable $e) {
            // Never leak the API key or URL in exception messages or app logs.
            return [];
        }
    }

    /** A stable positive 60-bit local ID, namespaced per provider to prevent cross-project collisions. */
    public function localMessageId(object $provider, int $logId, int $attempt = 1): string
    {
        // One idempotency key per attempt: a positively confirmed undelivered SMS
        // may be explicitly retried, while a network-unknown attempt is never retried.
        $suffix = $attempt > 1 ? '|attempt:'.$attempt : '';
        return (string) hexdec(substr(hash('sha256', (string)$provider->code.'|'.$logId.$suffix), 0, 15));
    }

    private function matchEntries(array $messages, array $entries, int $http): array
    {
        // Kavenegar examples do not guarantee the response ordering matches input ordering.
        $buckets = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) continue;
            $key = $this->key((string)($entry['receptor']??''), (string)($entry['message']??''));
            $buckets[$key][] = $entry;
        }
        $results = [];
        foreach ($messages as $row) {
            $key = $this->key((string)$row->receptor, (string)$row->message_text);
            $entry = !empty($buckets[$key]) ? array_shift($buckets[$key]) : null;
            if (!$entry || !isset($entry['messageid']) || !is_numeric($entry['messageid'])) {
                $results[(int)$row->id] = ['status'=>'unknown','http_status'=>$http,'error'=>'Provider response could not be matched to receptor/message.','provider_message_id'=>null];
                continue;
            }
            $results[(int)$row->id] = [
                'status'=>'sent','http_status'=>$http,'error'=>null,
                'provider_message_id'=>(string)$entry['messageid'],
                'delivery_code'=>(int)($entry['status']??1),
                'delivery_status'=>SmsReceiptPoller::classify((int)($entry['status']??1)),
            ];
        }
        return $results;
    }

    private function key(string $receptor, string $message): string
    {
        // All stored receptors are normalized to local 09... form before sending.
        $receptor = preg_replace('/^(?:\+98|0098)9/', '09', $receptor);
        return hash('sha256', $receptor.'|'.$message);
    }

    private function uniform(array $ids, string $status, ?int $http, string $error): array
    {
        return array_fill_keys($ids, ['status'=>$status, 'http_status'=>$http, 'error'=>$error, 'provider_message_id'=>null]);
    }
}
