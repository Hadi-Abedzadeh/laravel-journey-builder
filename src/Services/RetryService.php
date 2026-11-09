<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;

/** Resumes the exact failed node; never restarts a user at the trigger. */
final class RetryService
{
    public function retry(int $runId, ?int $expectedFlowId, bool $commit, bool $retryExternal, bool $ackDuplicateRisk): string
    {
        $db = DB::connection(config('journey_builder.connection', 'sqlsrv'));
        return $db->transaction(function () use ($db,$runId,$expectedFlowId,$commit,$retryExternal,$ackDuplicateRisk) {
            $run = $db->table('jb_runs')->where('id',$runId)->lockForUpdate()->first();
            if (!$run) return 'run_not_found';
            if ($expectedFlowId !== null && (int)$run->flow_id !== $expectedFlowId) return 'flow_mismatch';
            if ($run->state !== 'failed') return 'not_failed';
            if ($run->locked_until && $run->locked_until > now('UTC')->format('Y-m-d H:i:s')) return 'worker_lease_active';
            $flow = $db->table('jb_flows')->where('id',$run->flow_id)->lockForUpdate()->first();
            if (!$flow || $flow->status!=='active') return 'flow_not_active';
            try { $graph = app(FlowRevisionService::class)->graphForRun($db, $flow, $run); }
            catch (\RuntimeException $e) { return 'revision_snapshot_missing'; }
            $node=collect($graph['nodes']??[])->firstWhere('id', $run->node_id);
            if (!$node) return 'missing_node';
            $kind=$node['type']; $log=null;
            if (in_array($kind,['sms','api','email','webpush'],true)) {
                // Even if a crash predated logging, an external action needs explicit approval.
                if (!$retryExternal || !$ackDuplicateRisk) return 'explicit_external_retry_and_ack_required';
                $table=match($kind){'sms'=>'jb_sms_logs','api'=>'jb_api_logs',default=>'jb_channel_dispatches'};
                $log=TypedWhere::eq($db->table($table)->where('run_id',$run->id),'node_id',$run->node_id,80)->first();
                if ($log) {
                    if ($log->status !== 'failed') return 'delivery_'.$log->status.'_blocked';
                    if ((int)$log->attempts >= (int)config('journey_builder.max_delivery_attempts',3)) return 'attempt_limit';
                }
            }
            if (!$commit) return 'eligible';
            $now=now('UTC');
            $db->table('jb_retry_audit')->insert([
                'flow_id'=>$run->flow_id, 'run_id'=>$run->id,'node_id'=>$run->node_id,
                'retry_kind'=>in_array($kind,['sms','api','email','webpush'],true)?'external':'run',
                'previous_status'=>$log?->status, 'previous_error'=>$run->last_error, 'created_at'=>$now,
            ]);
            if ($log) {
                TypedWhere::eq($db->table(match($kind){'sms'=>'jb_sms_logs','api'=>'jb_api_logs',default=>'jb_channel_dispatches'})->where('id',$log->id),'status','failed',20)
                    ->update(['status'=>'retry_authorized','updated_at'=>$now]);
            }
            // Leave node_id and wait_started_at untouched: the user resumes at the same step.
            TypedWhere::eq($db->table('jb_runs')->where('id',$run->id), 'state','failed',20)
                ->update(['state'=>'ready','next_run_at'=>$now,'locked_until'=>null,'last_error'=>null,'updated_at'=>$now]);
            return 'retried';
        }, 3);
    }
}
