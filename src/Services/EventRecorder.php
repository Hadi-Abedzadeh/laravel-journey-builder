<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Append-only event capture: called ONE time for each logical event by the source ingestion code.
 * Does not infer events from an upserted member_data snapshot.
 */
final class EventRecorder
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection', 'sqlsrv'));
    }

    public function record(string $userId, string $eventName, array $payload = [], ?string $sign = null): int
    {
        if ($userId === '' || strlen($userId) > 100 || $eventName === '' || mb_strlen($eventName) > 50 ||
            ($sign !== null && strlen($sign) > 30)) {
            throw new InvalidArgumentException('Invalid event identity or source sign');
        }
        foreach ($payload as $key => $value) {
            if (!is_string($key) || mb_strlen($key) > 50 ||
                !(is_scalar($value) || $value === null) ||
                (is_string($value) && mb_strlen($value) > 1000)) {
                throw new InvalidArgumentException('Event payload must contain bounded scalar key/value pairs');
            }
        }
        return (int)$this->db()->table('jb_event_occurrences')->insertGetId([
            'user_id' => $userId,
            'event_name' => $eventName,
            'sign' => $sign,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'occurred_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function latestId(string $userId, string $eventName): int
    {
        $query = TypedWhere::eq($this->db()->table('jb_event_occurrences'), 'user_id', $userId, 100)
            ->where('event_name', $eventName);
        return (int) ($query->max('id') ?: 0);
    }

    public function seenSince(string $userId, array $config, string $sinceUtc, ?int $afterId = null): bool
    {
        $query = TypedWhere::eq($this->db()->table('jb_event_occurrences'), 'user_id', $userId, 100)
            ->where('event_name', (string)$config['event']);
        if ($afterId !== null) $query->where('id', '>', $afterId);
        else $query->where('occurred_at','>', CarbonImmutable::parse($sinceUtc,'UTC')->format('Y-m-d H:i:s.v'));
        if (trim((string)($config['sign']??'')) !== '') {
            TypedWhere::eq($query, 'sign', $config['sign'], 30);
        }
        $key = (string)($config['key'] ?? '');
        if ($key === '') return $query->exists();
        foreach ($query->select(['payload_json'])->cursor() as $event) {
            $values = json_decode($event->payload_json, true);
            if (!is_array($values) || !array_key_exists($key, $values)) continue;
            if (TriggerMatcher::matches($values[$key], (string)($config['operator']??'exists'), (string)($config['value']??''))) return true;
        }
        return false;
    }
}
