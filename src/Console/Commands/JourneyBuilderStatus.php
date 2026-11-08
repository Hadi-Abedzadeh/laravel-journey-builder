<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenJourney\LaravelJourneyBuilder\Services\TypedWhere;

final class JourneyBuilderStatus extends Command
{
    protected $signature = 'journey-builder:status {--flow= : Flow numeric ID or code} {--json : Machine readable JSON}';
    protected $description = 'Show journey status, run states, due times, and delivery outcomes without secrets';

    public function handle(): int
    {
        $db = DB::connection(config('journey_builder.connection'));
        $q = $db->table('jb_flows');
        $filter = trim((string) $this->option('flow'));
        if ($filter !== '') {
            $q->where(function ($query) use ($filter, $db) {
                if (ctype_digit($filter)) $query->where('id', (int)$filter);
                else TypedWhere::eq($query, 'code', $filter, 100);
            });
        }
        $flows = $q->orderBy('id')->get();
        if ($flows->isEmpty() && $filter !== '') { $this->error('Flow not found.'); return self::FAILURE; }
        $summary = [];
        $now = now('UTC')->format('Y-m-d H:i:s');
        foreach ($flows as $flow) {
            $runs = $db->table('jb_runs')->where('flow_id',$flow->id)
                ->selectRaw('state, COUNT(*) AS total')->groupBy('state')->pluck('total','state')->all();
            $sms = $db->table('jb_sms_logs')->where('flow_id',$flow->id)
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total','status')->all();
            $api = $db->table('jb_api_logs')->where('flow_id',$flow->id)
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total','status')->all();
            $next = TypedWhere::oneOf($db->table('jb_runs')->where('flow_id',$flow->id), 'state',['ready','waiting'],20)->min('next_run_at');
            $due = TypedWhere::oneOf($db->table('jb_runs')->where('flow_id',$flow->id), 'state',['ready','waiting'],20)->where('next_run_at','<=',$now)->count();
            $archived = $db->table('jb_flow_totals')->where('flow_id',$flow->id)->first();
            if ($archived) {
                $runs['exited'] = (int)($runs['exited']??0) + (int)$archived->pruned_runs;
                foreach (['sent','failed','unknown'] as $status) {
                    $sms[$status] = (int)($sms[$status]??0) + (int)$archived->{'sms_'.$status};
                    $api[$status] = (int)($api[$status]??0) + (int)$archived->{'api_'.$status};
                }
            }
            $summary[] = [
                'id'=>(int)$flow->id,'code'=>$flow->code,'title'=>$flow->title,'status'=>$flow->status,
                'revision'=>(int)$flow->revision,'runs'=>$runs,'sms'=>$sms,'api'=>$api,
                'due'=>(int)$due,'next_run_at'=>$next,
            ];
        }
        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['ID','Title','Status','Ready','Waiting','Failed','Exited','Due','Next due','SMS sent','SMS unknown'],
                array_map(fn($r)=>[$r['id'],$r['title'],$r['status'],
                    $r['runs']['ready']??0,$r['runs']['waiting']??0,$r['runs']['failed']??0,
                    $r['runs']['exited']??0,$r['due'],$r['next_run_at']??'-',
                    $r['sms']['sent']??0,$r['sms']['unknown']??0],$summary));
        }
        return self::SUCCESS;
    }

}
