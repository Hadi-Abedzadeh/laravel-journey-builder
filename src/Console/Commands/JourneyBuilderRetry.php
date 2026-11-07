<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenJourney\LaravelJourneyBuilder\Services\TypedWhere;

/** Manual, bounded retries. External deliveries never resent without two explicit opt-ins. */
final class JourneyBuilderRetry extends Command
{
    protected $signature = 'journey-builder:retry
        {--run= : Retry one run ID}
        {--flow= : Retry failed runs for one flow ID}
        {--limit=100 : Maximum runs (1..500)}
        {--commit : Apply changes; omit to preview}
        {--retry-external : Authorize new attempt for a failed SMS/API call}
        {--ack-duplicate-risk : Acknowledge that an HTTP error does not prove non-delivery}';
    protected $description = 'Preview or manually retry failed runs; unknown/sending/sent deliveries are never resent';

    public function handle(): int
    {
        $runId = $this->option('run'); $flowId = $this->option('flow');
        if ((!$runId && !$flowId) || ($runId && (!ctype_digit((string)$runId) || (int)$runId<1)) ||
            ($flowId && (!ctype_digit((string)$flowId) || (int)$flowId<1))) {
            $this->error('Specify --run=ID or --flow=ID (positive integers).'); return self::INVALID;
        }
        $limit=(int)$this->option('limit');
        if ($limit<1 || $limit>500) { $this->error('--limit must be 1..500'); return self::INVALID; }
        $external=(bool)$this->option('retry-external');
        if ($external && !$this->option('ack-duplicate-risk')) {
            $this->error('--retry-external requires --ack-duplicate-risk.'); return self::INVALID;
        }
        $commit=(bool)$this->option('commit');
        $db=DB::connection(config('journey_builder.connection'));
        $query=TypedWhere::eq($db->table('jb_runs'), 'state', 'failed',20)->orderBy('id');
        if ($runId) $query->where('id',(int)$runId);
        if ($flowId) $query->where('flow_id',(int)$flowId);
        $runs=$query->limit($limit)->get();
        $stats=['selected'=>$runs->count(),'eligible'=>0,'retried'=>0,'skipped'=>0,'reasons'=>[]];
        foreach ($runs as $candidate) {
            $process=function () use ($db,$candidate,$external,$commit,&$stats) {
                $run=$db->table('jb_runs')->where('id',$candidate->id)->lockForUpdate()->first();
                if (!$run || $run->state!=='failed') return 'not_failed';
                $flow=$db->table('jb_flows')->where('id',$run->flow_id)->first();
                if (!$flow || $flow->status!=='active') return 'flow_not_active';
                if ((int)$flow->revision!==(int)$run->revision) return 'revision_mismatch';
                $nodes=collect(json_decode($flow->graph_json,true)['nodes']??[])->keyBy('id');
                $node=$nodes->get($run->node_id);
                if (!$node) return 'missing_node';
                $kind=$node['type']; $log=null;
                if (in_array($kind,['sms','api'],true)) {
                    $table=$kind==='sms'?'jb_sms_logs':'jb_api_logs';
                    $log=TypedWhere::eq($db->table($table)->where('run_id',$run->id), 'node_id', $run->node_id,80)->first();
                    if ($log) {
                        if ($log->status!=='failed') return 'delivery_'.$log->status.'_blocked';
                        if (!$external) return 'explicit_external_retry_required';
                        if ((int)$log->attempts >= (int) config('journey_builder.max_delivery_attempts',3)) return 'attempt_limit';
                    }
                }
                if (!$commit) return 'eligible';
                $now=now('UTC')->format('Y-m-d H:i:s');
                $db->table('jb_retry_audit')->insert([
                    'flow_id'=>$run->flow_id,'run_id'=>$run->id,'node_id'=>$run->node_id,
                    'retry_kind'=>$log?'external':'run',
                    'previous_status'=>$log? $log->status:null,
                    'previous_error'=>$run->last_error,
                    'created_at'=>$now,
                ]);
                if ($log) {
                    TypedWhere::eq($db->table($kind==='sms'?'jb_sms_logs':'jb_api_logs')->where('id',$log->id), 'status', 'failed', 20)
                        ->update(['status'=>'retry_authorized','updated_at'=>$now]);
                }
                TypedWhere::eq($db->table('jb_runs')->where('id',$run->id), 'state', 'failed',20)->update([
                    'state'=>'ready','next_run_at'=>$now,'locked_until'=>null,
                    'last_error'=>null,'updated_at'=>$now,
                ]);
                return 'retried';
            };
            $result=$db->transaction($process, 3);
            if ($result==='eligible') $stats['eligible']++;
            elseif ($result==='retried') $stats['retried']++;
            else { $stats['skipped']++;$stats['reasons'][$result]=($stats['reasons'][$result]??0)+1; }
        }
        $this->line(json_encode(['dry_run'=>!$commit]+$stats, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
