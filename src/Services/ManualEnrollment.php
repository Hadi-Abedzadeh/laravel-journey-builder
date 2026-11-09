<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Single-user enrollment for isolated testing; never advances the journey or sends messages. */
final class ManualEnrollment
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection', 'sqlsrv'));
    }

    public function check(int $flowId, string $userId, bool $ignoreTrigger = false): array
    {
        if ($flowId <= 0 || $userId === '' || strlen($userId) > 100 || trim($userId) !== $userId) {
            throw new RuntimeException('A valid flow ID and exact userId (1..100 bytes) are required.');
        }
        $db = $this->db();
        if (!Schema::connection(config('journey_builder.connection','sqlsrv'))->hasColumn('jb_flows', 'enrollment_mode')) {
            throw new RuntimeException('Run Journey Builder v3.3 manual-enrollment migration first.');
        }
        app(RunRetention::class)->assertReady();
        $flow = $db->table('jb_flows')->where('id', $flowId)->first();
        if (!$flow) throw new RuntimeException('Journey does not exist.');
        if ($flow->status !== 'active') throw new RuntimeException('Journey must be active to accept a test user.');
        if ($flow->enrollment_mode !== 'manual') throw new RuntimeException('Set journey enrollment mode to manual before adding one test user.');
        $graph = json_decode($flow->graph_json, true, 512, JSON_THROW_ON_ERROR);
        $trigger = collect($graph['nodes'] ?? [])->firstWhere('type', 'trigger');
        if (!$trigger || empty($trigger['config']['key'])) throw new RuntimeException('Journey trigger is not configured.');
        $source = $db->table(config('journey_builder.source_table','member_data'));
        $exists = TypedWhere::eq($source, 'userId', $userId, 100)->exists();
        if (!$exists) throw new RuntimeException('userId was not found in the source table.');
        $cfg = $trigger['config'];
        $sourceQuery = TypedWhere::eq(
            TypedWhere::eq($db->table(config('journey_builder.source_table','member_data')), 'userId', $userId, 100),
            'section', (string)$cfg['section'], 20
        )->where('key', (string)$cfg['key']);
        if (!empty($cfg['sign'])) TypedWhere::eq($sourceQuery, 'sign', (string)$cfg['sign'], 30);
        $row = $sourceQuery->orderByDesc('updated_at')->orderByDesc('id')->first(['value']);
        $matches = TriggerMatcher::matches($row?->value, (string)($cfg['operator'] ?? 'exists'), (string)($cfg['value'] ?? ''));
        $existing = TypedWhere::eq($db->table('jb_runs')->where('flow_id', $flowId), 'user_id', $userId, 100)->exists();
        $digest = app(RunRetention::class)->userDigest($userId);
        if ($digest !== null) {
            $existing = $existing || TypedWhere::eq($db->table('jb_run_hashes')->where('flow_id',$flowId), 'user_digest', $digest, 64)->exists();
        }
        return [
            'flow_id' => $flowId, 'user_found'=>true, 'trigger_matches'=>$matches,
            'trigger_bypassed'=>$ignoreTrigger && !$matches,
            'already_enrolled'=>$existing, 'eligible'=>!$existing && ($matches || $ignoreTrigger),
            'start_node_id'=>$trigger['id'], 'revision'=>(int)$flow->revision,
        ];
    }

    public function commit(int $flowId, string $userId, bool $ignoreTrigger = false): array
    {
        $result = $this->check($flowId, $userId, $ignoreTrigger);
        if (!$result['eligible']) return $result + ['enrolled'=>false];
        $db = $this->db();
        try {
            return $db->transaction(function () use ($db, $flowId, $userId, $ignoreTrigger) {
                // Lock the flow before rechecking to prevent mode/status changes during enrollment.
                $flow = $db->table('jb_flows')->where('id', $flowId)->lockForUpdate()->first();
                if (!$flow || $flow->status !== 'active' || $flow->enrollment_mode !== 'manual') {
                    throw new RuntimeException('Journey status or enrollment mode changed.');
                }
                $result = $this->check($flowId, $userId, $ignoreTrigger);
                if (!$result['eligible']) return $result + ['enrolled'=>false];
                $digest = app(RunRetention::class)->userDigest($userId);
                if ($digest !== null) {
                    $db->table('jb_run_hashes')->insert(['flow_id'=>$flowId,'user_digest'=>$digest,'created_at'=>now('UTC')]);
                }
                $id = $db->table('jb_runs')->insertGetId([
                    'flow_id'=>$flowId,'user_id'=>$userId,'node_id'=>$result['start_node_id'],
                    'state'=>'ready','revision'=>$result['revision'],
                    'next_run_at'=>now('UTC'),'created_at'=>now('UTC'),'updated_at'=>now('UTC'),
                ]);
                return $result + ['enrolled'=>true, 'run_id'=>$id];
            },3);
        } catch (QueryException $e) {
            $details = implode(' ', (array) ($e->errorInfo ?? []));
            if (preg_match('/\b(2601|2627)\b/', $details)) {
                return $this->check($flowId,$userId,$ignoreTrigger) + ['enrolled'=>false];
            }
            throw $e;
        }
    }
}
