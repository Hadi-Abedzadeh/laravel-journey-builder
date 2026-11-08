<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenJourney\LaravelJourneyBuilder\Services\RunRetention;
use OpenJourney\LaravelJourneyBuilder\Services\TypedWhere;

final class JourneyBuilderPrune extends Command
{
    protected $signature = 'journey-builder:prune {--flow= : Limit to a flow ID} {--batch=200 : Up to 1000 exited runs per pass} {--commit : Delete after aggregation; dry-run by default}';
    protected $description = 'Compact exited runs while retaining keyed dedupe hashes and aggregate metrics';

    public function handle(RunRetention $retention): int
    {
        $flow=$this->option('flow'); $batch=(int)$this->option('batch');
        if (($flow!==null && (!ctype_digit((string)$flow) || (int)$flow < 1)) || $batch<1 || $batch>1000) {
            $this->error('Valid options: --flow=positive_id, --batch=1..1000'); return self::INVALID;
        }
        $db=DB::connection(config('journey_builder.connection'));
        $query=TypedWhere::eq($db->table('jb_runs'), 'state', 'exited', 20)
            ->whereNotExists(function ($q) {
                TypedWhere::oneOf($q->selectRaw('1')->from('jb_sms_logs as s')
                    ->whereColumn('s.run_id','jb_runs.id'), 's.status', ['unknown','sending','retry_authorized'],20);
            })
            ->whereNotExists(function ($q) {
                TypedWhere::oneOf($q->selectRaw('1')->from('jb_api_logs as a')
                    ->whereColumn('a.run_id','jb_runs.id'), 'a.status', ['unknown','sending','retry_authorized'],20);
            })->orderBy('id');
        if ($flow) $query->where('flow_id',(int)$flow);
        $ids=$query->limit($batch)->pluck('id');
        $done=0;
        if ($this->option('commit')) {
            if (!config('journey_builder.retention.purge_on_exit', false)) {
                $this->error('Enable JOURNEY_BUILDER_PURGE_ON_EXIT=true after migration and key setup.'); return self::FAILURE;
            }
            $retention->assertReady();
            foreach ($ids as $id) {
                if ($retention->pruneExitedRun((int)$id)) $done++;
            }
        }
        $this->line(json_encode(['dry_run'=>!$this->option('commit'),'candidates'=>$ids->count(),'pruned'=>$done],JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
