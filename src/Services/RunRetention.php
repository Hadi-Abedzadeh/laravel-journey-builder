<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * An exited run is compacted atomically: its step/delivery records are rolled into
 * aggregate counters; its user id is replaced by an irreversible keyed digest.
 * Failed/unknown runs are deliberately NEVER pruned (operator action still needed).
 */
final class RunRetention
{
    private function db(): ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection', 'sqlsrv'));
    }

    public function userDigest(string $userId): ?string
    {
        $secret = (string) config('journey_builder.retention.enrollment_hash_key', '');
        return $secret === '' ? null : hash_hmac('sha256', $userId, $secret);
    }

    public function assertReady(): void
    {
        $key = (string) config('journey_builder.retention.enrollment_hash_key', '');
        $purge = (bool) config('journey_builder.retention.purge_on_exit', false);
        if (($purge || $key !== '') && strlen($key) < 32) {
            throw new RuntimeException('Journey retention requires a stable JOURNEY_BUILDER_ENROLLMENT_HASH_KEY (>=32 characters).');
        }
        if (($purge || $key !== '') && !Schema::connection(config('journey_builder.connection', 'sqlsrv'))->hasTable('jb_run_hashes')) {
            throw new RuntimeException('Apply the Journey Builder v3.1 additive migration before enabling enrollment digests/retention.');
        }
        if ($purge && (!Schema::connection(config('journey_builder.connection', 'sqlsrv'))->hasTable('jb_flow_totals')
            || !Schema::connection(config('journey_builder.connection', 'sqlsrv'))->hasTable('jb_node_totals'))) {
            throw new RuntimeException('Apply the Journey Builder v3.1 metrics migration before enabling retention.');
        }
    }

    /** Return true only when an exited run was actually deleted and counted. */
    public function pruneExitedRun(int $runId): bool
    {
        $this->assertReady();
        if (!config('journey_builder.retention.purge_on_exit', false)) return false;
        $db = $this->db();

        return $db->transaction(function () use ($db, $runId) {
            $run = $db->table('jb_runs')->where('id', $runId)->lockForUpdate()->first();
            if (!$run || $run->state !== 'exited') return false;
            // Per-flow serialization protects first-time metrics row and per-node upserts.
            $flow = $db->table('jb_flows')->where('id', $run->flow_id)->lockForUpdate()->first();
            if (!$flow) throw new RuntimeException('Cannot prune a run whose flow no longer exists.');

            // Retain unresolved external outcomes for manual reconciliation, even for legacy exited runs.
            foreach (['jb_sms_logs','jb_api_logs'] as $table) {
                if (TypedWhere::oneOf($db->table($table)->where('run_id',$runId),
                    'status',['unknown','sending','retry_authorized'],20)->exists()) return false;
            }

            $digest = $this->userDigest((string) $run->user_id);
            if ($digest === null) throw new RuntimeException('Missing stable enrollment digest key.');
            if (!TypedWhere::eq($db->table('jb_run_hashes')->where('flow_id',$run->flow_id),'user_digest',$digest,64)->exists()) {
                $db->table('jb_run_hashes')->insert([
                    'flow_id'=>$run->flow_id, 'user_digest'=>$digest, 'created_at'=>now('UTC'),
                ]);
            }

            $sms = $db->table('jb_sms_logs')->where('run_id', $runId)
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total','status');
            $api = $db->table('jb_api_logs')->where('run_id', $runId)
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total','status');
            $nodes = $db->table('jb_step_logs')->where('run_id', $runId)
                ->selectRaw('to_node_id, COUNT(*) AS total')->groupBy('to_node_id')->get();
            $increments = [
                'pruned_runs'=>1,
                'sms_sent'=>(int)($sms['sent']??0),
                'sms_failed'=>(int)($sms['failed']??0),
                'sms_unknown'=>(int)($sms['unknown']??0),
                'api_sent'=>(int)($api['sent']??0),
                'api_failed'=>(int)($api['failed']??0),
                'api_unknown'=>(int)($api['unknown']??0),
            ];
            $now = now('UTC');
            if ($db->table('jb_flow_totals')->where('flow_id', $run->flow_id)->exists()) {
                $db->table('jb_flow_totals')->where('flow_id',$run->flow_id)
                    ->incrementEach($increments, ['updated_at'=>$now]);
            } else {
                $db->table('jb_flow_totals')->insert(['flow_id'=>$run->flow_id] + $increments + ['updated_at'=>$now]);
            }
            foreach ($nodes as $node) {
                $q = TypedWhere::eq($db->table('jb_node_totals')->where('flow_id',$run->flow_id), 'node_id', $node->to_node_id, 80);
                if ($q->exists()) {
                    $q->increment('arrivals',(int)$node->total);
                } else {
                    $db->table('jb_node_totals')->insert([
                        'flow_id'=>$run->flow_id,'node_id'=>$node->to_node_id,'arrivals'=>(int)$node->total,
                    ]);
                }
            }
            // Individual data is deleted within the same transaction as metric aggregation.
            foreach (['jb_step_logs','jb_sms_logs','jb_api_logs','jb_retry_audit'] as $table) {
                $db->table($table)->where('run_id', $runId)->delete();
            }
            $db->table('jb_runs')->where('id', $runId)->delete();
            return true;
        }, 3);
    }
}
