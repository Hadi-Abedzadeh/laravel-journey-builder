<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class Engine
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection', 'sqlsrv'));
    }

    private function now(): CarbonImmutable { return CarbonImmutable::now('UTC'); }
    private function stamp(?CarbonImmutable $date = null): string { return ($date ?? $this->now())->setTimezone('UTC')->format('Y-m-d H:i:s'); }
    private function memberStamp(string $utc): string
    {
        return CarbonImmutable::parse($utc, 'UTC')
            ->setTimezone(config('journey_builder.member_timezone', config('app.timezone','UTC')))
            ->format('Y-m-d H:i:s');
    }

    // Each flow owns an independent scan cursor. Unique(flow_id,user_id) prevents duplicate enrollment,
    // but permits the same person to participate in several flows without shared state.
    public function enroll(int $batch = 200): array
    {
        $db = $this->db(); $results = [];
        app(RunRetention::class)->assertReady();
        foreach (TypedWhere::eq($db->table('jb_flows'), 'status', 'active', 20)->get() as $flow) {
            // A manual-only flow never scans the entire member source.
            if (($flow->enrollment_mode ?? 'automatic') === 'manual') continue;
            $graph = json_decode($flow->graph_json, true);
            $trigger = collect($graph['nodes'] ?? [])->firstWhere('type', 'trigger');
            if (!$trigger) continue;
            $cfg = $trigger['config'];
            if (empty($cfg['key'])) continue;
            $query = TypedWhere::eq($db->table(config('journey_builder.source_table', 'member_data'))
                ->select(['id','userId','value','updated_at']), 'section', $cfg['section'], 20)
                ->where('key', $cfg['key'])
                ->whereNotNull('userId')->whereNotNull('updated_at');
            if (trim((string)($cfg['sign'] ?? '')) !== '') {
                TypedWhere::eq($query, 'sign', $cfg['sign'], 30);
            }
            if ($flow->enroll_cursor_at !== null) {
                $query->where(function ($q) use ($flow) {
                    $q->where('updated_at', '>', $flow->enroll_cursor_at)
                        ->orWhere(function ($inner) use ($flow) {
                            $inner->where('updated_at', '=', $flow->enroll_cursor_at)
                                ->where('id', '>', $flow->enroll_cursor_id);
                        });
                });
            }
            $rows = $query->orderBy('updated_at')->orderBy('id')->limit(min(1000,max(1,$batch)))->get();
            $created = 0;
            foreach ($rows as $row) {
                if (TriggerMatcher::matches($row->value, $cfg['operator'], (string)($cfg['value'] ?? ''))) {
                    try {
                        $enrolled = $db->transaction(function () use ($db, $flow, $row, $trigger) {
                            $key = app(RunRetention::class)->userDigest((string) $row->userId);
                            if ($key !== null) {
                                // Compact permanent deduplication barrier after completed runs are erased.
                                if (TypedWhere::eq($db->table('jb_run_hashes')->where('flow_id', $flow->id), 'user_digest', $key, 64)->exists()) return false;
                                $db->table('jb_run_hashes')->insert([
                                    'flow_id'=>$flow->id, 'user_digest'=>$key, 'created_at'=>$this->stamp(),
                                ]);
                            }
                            $db->table('jb_runs')->insert([
                                'flow_id'=>$flow->id, 'user_id'=>$row->userId, 'node_id'=>$trigger['id'],
                                'state'=>'ready', 'revision'=>$flow->revision,
                                'next_run_at'=>$this->stamp(), 'created_at'=>$this->stamp(), 'updated_at'=>$this->stamp(),
                            ]);
                            return true;
                        });
                        if ($enrolled) $created++;
                    } catch (QueryException $e) {
                        $details = implode(' ', (array)($e->errorInfo ?? []));
                        if (!preg_match('/\b(2601|2627)\b/', $details)) throw $e;
                    }
                }
                // The source scan position belongs exclusively to this flow.
                $db->table('jb_flows')->where('id',$flow->id)->update([
                    'enroll_cursor_at'=>$row->updated_at, 'enroll_cursor_id'=>$row->id,
                ]);
            }
            $results[$flow->code] = ['scanned'=>$rows->count(), 'enrolled'=>$created];
        }
        return $results;
    }

    private function eventSeen(string $userId, array $cfg, ?string $sinceUtc = null): bool
    {
        $query = TypedWhere::eq(
            TypedWhere::eq($this->db()->table(config('journey_builder.source_table', 'member_data')), 'userId', $userId, 100),
            'section', 'events', 20)->where('name', $cfg['event']);
        if (!empty($cfg['sign'])) TypedWhere::eq($query, 'sign', $cfg['sign'], 30);
        if ($sinceUtc !== null) $query->where('updated_at', '>=', $this->memberStamp($sinceUtc));
        return $query->exists();
    }

    private function attributeMatches(string $userId, array $cfg): bool
    {
        $query = TypedWhere::eq(
            TypedWhere::eq($this->db()->table(config('journey_builder.source_table', 'member_data')), 'userId', $userId, 100),
            'section', 'attributes', 20)->where('key', $cfg['key']);
        if (!empty($cfg['sign'])) TypedWhere::eq($query, 'sign', $cfg['sign'], 30);
        $value = $query->orderByDesc('updated_at')->orderByDesc('id')->value('value');
        return TriggerMatcher::matches($value, $cfg['operator'], (string)($cfg['value'] ?? ''));
    }

    // Lease rows and re-check flow state inside advance; no worker reads another flow's run as its own.
    public function tick(int $batch = 100): array
    {
        $db=$this->db(); $processed=0; $failed=0;
        $due = TypedWhere::oneOf(
            TypedWhere::eq($db->table('jb_runs as r')->join('jb_flows as f','f.id','=','r.flow_id'), 'f.status', 'active', 20),
            'r.state', ['ready','waiting'], 20)
            ->where('r.next_run_at','<=',$this->stamp())
            ->where(function($q){$q->whereNull('r.locked_until')->orWhere('r.locked_until','<',$this->stamp());})
            ->orderBy('r.id')->limit(min(500,max(1,$batch)))->pluck('r.id');
        foreach ($due as $id) {
            $run = $db->transaction(function () use ($db,$id) {
                $run = $db->table('jb_runs')->where('id',$id)->lockForUpdate()->first();
                if (!$run || !in_array($run->state,['ready','waiting'],true) ||
                    ($run->locked_until && $run->locked_until > $this->stamp()) ||
                    ($run->next_run_at && $run->next_run_at > $this->stamp()) ||
                    !TypedWhere::eq($db->table('jb_flows')->where('id',$run->flow_id), 'status', 'active', 20)->exists()) return null;
                $db->table('jb_runs')->where('id',$id)->update(['locked_until'=>$this->stamp($this->now()->addMinutes(3))]);
                return $run;
            });
            if (!$run) continue;
            try { $this->advance($run); $processed++; }
            catch (\Throwable $e) {
                $failed++; report($e);
                $db->table('jb_runs')->where('id',$id)->update([
                    'state'=>'failed','last_error'=>mb_substr($e->getMessage(),0,2000),
                    'next_run_at'=>null,'updated_at'=>$this->stamp(),
                ]);
            } finally {
                // Keep the same lease throughout a chain of immediate steps, release once per tick.
                $db->table('jb_runs')->where('id',$id)->update(['locked_until'=>null]);
            }
        }
        return ['processed'=>$processed, 'failed'=>$failed];
    }

    private function advance(object $run): void
    {
        $db=$this->db();
        $flow = $db->table('jb_flows')->where('id',$run->flow_id)->first();
        if (!$flow || $flow->status!=='active') return; // Paused while claimed: leave run unmodified.
        // Paused journeys may be edited: older runs continue on an immutable graph revision.
        $graph=app(FlowRevisionService::class)->graphForRun($db,$flow,$run);
        $nodes=collect($graph['nodes'])->keyBy('id');
        $edges=[];
        foreach ($graph['edges'] as $edge) $edges[$edge['source']][$edge['port']]=$edge['target'];
        for ($hop=0;$hop<60;$hop++) {
            $node=$nodes->get($run->node_id);
            if (!$node) throw new \RuntimeException('Unknown journey node: '.$run->node_id);
            $cfg=$node['config'] ?? []; $next='next';
            switch ($node['type']) {
                case 'trigger': break;
                case 'exit':
                    $db->transaction(function () use ($run) {
                        $this->write($run,['state'=>'exited','completed_at'=>$this->stamp(),'next_run_at'=>null,'wait_started_at'=>null]);
                        if (config('journey_builder.retention.purge_on_exit', false)) {
                            app(RunRetention::class)->pruneExitedRun((int) $run->id);
                        }
                    });
                    return;
                case 'slot':
                    $allowed=$this->nextSlot($cfg);
                    if ($allowed->greaterThan($this->now())) {
                        $this->write($run,['state'=>'waiting','next_run_at'=>$this->stamp($allowed)]);
                        return;
                    }
                    break;
                case 'condition':
                    $since=($cfg['scope']??'ever')==='after_enroll'?$run->created_at:null;
                    $next=$this->eventSeen($run->user_id,$cfg,$since)?'yes':'no';
                    break;
                case 'attribute':
                    $next=$this->attributeMatches($run->user_id,$cfg)?'yes':'no';
                    break;
                case 'split':
                    $hash=hexdec(substr(hash('sha256',$flow->id.'|'.$run->user_id.'|'.$run->node_id),0,7));
                    $next=($hash%100)<(int)$cfg['percent']?'a':'b';
                    break;
                case 'delay':
                case 'wait_event':
                    if (!$run->wait_started_at) $this->write($run,['wait_started_at'=>$this->stamp()]);
                    $started=CarbonImmutable::parse($run->wait_started_at,'UTC');
                    $due=$started->addHours((int)$cfg['hours']);
                    if ($node['type']==='wait_event' && $this->eventSeen($run->user_id,$cfg,$run->wait_started_at)) {
                        $next='event';
                    } elseif ($this->now()->greaterThanOrEqualTo($due)) {
                        $next=$node['type']==='wait_event'?'timeout':'next';
                    } else {
                        $poll=$node['type']==='wait_event'?$this->now()->addMinute():$due;
                        $this->write($run,['state'=>'waiting','next_run_at'=>$this->stamp($poll->lessThan($due)?$poll:$due)]);
                        return;
                    }
                    break;
                case 'email':
                case 'webpush':
                    $result=app(ChannelQueue::class)->enqueueOrStatus($run,$flow,$cfg,$node['type']);
                    if ($result==='queued') {
                        $this->write($run,['state'=>'waiting','next_run_at'=>$this->stamp($this->now()->addMinute())]);
                        return;
                    }
                    if ($result==='unknown') {
                        $this->write($run,['state'=>'failed','next_run_at'=>null,'last_error'=>'Channel outcome unknown; reconcile before retry']);
                        return;
                    }
                    if ($result==='failed') {
                        $this->write($run,['state'=>'failed','next_run_at'=>null,'last_error'=>'Email/push delivery failed; explicit retry required']);
                        return;
                    }
                    $next='success';
                    break;
                case 'sms':
                    $result=$this->external($run,$flow,$cfg,'sms');
                    if ($result==='queued') {
                        $this->write($run,['state'=>'waiting','next_run_at'=>$this->stamp($this->now()->addMinute())]);
                        return;
                    }
                    if ($result==='unknown') {
                        $this->write($run,['state'=>'failed','next_run_at'=>null,'last_error'=>'SMS outcome unknown; manual reconciliation required']);
                        return;
                    }
                    if ($result==='failed') {
                        $this->write($run,['state'=>'failed','next_run_at'=>null,'last_error'=>'SMS/API delivery failed; retry from this node is required']);
                        return;
                    }
                    $next='success';
                    break;
                case 'api':
                    $result=$this->external($run,$flow,$cfg,'api');
                    if ($result==='unknown') {
                        $this->write($run,['state'=>'failed','next_run_at'=>null,'last_error'=>'API outcome unknown; manual reconciliation required']);
                        return;
                    }
                    if ($result==='failed') {
                        $this->write($run,['state'=>'failed','next_run_at'=>null,'last_error'=>'SMS/API delivery failed; retry from this node is required']);
                        return;
                    }
                    $next='success';
                    break;
                default: throw new \RuntimeException('Unsupported journey node');
            }
            $target=$edges[$run->node_id][$next]??null;
            if (!$target) throw new \RuntimeException('Unconnected output '.$next.' on '.$run->node_id);
            $from=$run->node_id;
            $this->db()->transaction(function() use ($run,$target,$from,$next) {
                $this->write($run,['node_id'=>$target,'wait_started_at'=>null,'state'=>'ready','next_run_at'=>$this->stamp()]);
                $this->db()->table('jb_step_logs')->insert([
                    'flow_id'=>$run->flow_id,'run_id'=>$run->id,'user_id'=>$run->user_id,
                    'from_node_id'=>$from,'to_node_id'=>$target,'port'=>$next,'created_at'=>$this->stamp(),
                ]);
            });
        }
        throw new \RuntimeException('Too many immediate transitions; possible cycle');
    }

    private function external(object $run, object $flow, array $cfg, string $type): string
    {
        if ($type==='sms' && ($cfg['delivery_mode'] ?? 'legacy')==='managed') {
            return app(ManagedSmsQueue::class)->enqueueOrStatus($run, $flow, $cfg);
        }
        $db=$this->db();
        $table=$type==='sms'?'jb_sms_logs':'jb_api_logs';
        $existing=TypedWhere::eq($db->table($table)->where('run_id',$run->id), 'node_id', $run->node_id, 80)->first();
        if ($existing) {
            if (in_array($existing->status,['sent','failed'],true)) return $existing->status;
            if ($existing->status !== 'retry_authorized') {
                // Connection may have dropped after provider accepted the request: never retry blindly.
                $db->table($table)->where('id',$existing->id)->update(['status'=>'unknown','updated_at'=>$this->stamp()]);
                return 'unknown';
            }
            // The operator explicitly authorized this extra attempt using the retry command.
            TypedWhere::eq($db->table($table)->where('id',$existing->id), 'status', 'retry_authorized',20)->update([
                'status'=>'sending','attempts'=>(int)$existing->attempts+1,
                'http_status'=>null,'error_message'=>null,'updated_at'=>$this->stamp(),
            ]);
        }
        $time=$this->stamp();
        $fields=[
            'flow_id'=>$flow->id,'run_id'=>$run->id,'node_id'=>$run->node_id,
            'user_id'=>$run->user_id,'status'=>'sending', 'attempts'=>1,
            'created_at'=>$time,'updated_at'=>$time,
        ];
        if ($type==='sms') $fields['template']=$cfg['template'];
        else { $fields['method']=$cfg['method']??'POST'; $fields['target_host']=parse_url($cfg['url'],PHP_URL_HOST)?:''; }
        // Unique(run_id,node_id) is a final DB-level duplicate-sending barrier.
        $id=$existing ? $existing->id : $db->table($table)->insertGetId($fields);
        $dedupe='jb-'.$type.'-'.$run->id.'-'.$run->node_id;
        $result=$type==='sms'
            ? app(SmsGateway::class)->send($run->user_id,$cfg['template'],(string)($cfg['message']??''),$dedupe)
            : app(ApiGateway::class)->send($run,$flow,$cfg,$dedupe);
        $db->table($table)->where('id',$id)->update([
            'status'=>$result['status'],'http_status'=>$result['http_status'],
            'error_message'=>$result['error'],'sent_at'=>$result['status']==='sent'?$this->stamp():null,
            'updated_at'=>$this->stamp(),
        ]);
        return $result['status'];
    }

    private function write(object $run, array $changes): void
    {
        $changes['updated_at']=$this->stamp();
        $this->db()->table('jb_runs')->where('id',$run->id)->update($changes);
        foreach ($changes as $key=>$value) $run->$key=$value;
    }

    private function nextSlot(array $cfg): CarbonImmutable
    {
        $local=$this->now()->setTimezone(config('journey_builder.timezone','Asia/Tehran'));
        for($i=0;$i<9;$i++) {
            $day=$local->startOfDay()->addDays($i);
            if(!in_array((int)$day->format('w'),$cfg['days'],true)) continue;
            $from=$day->setTimeFromTimeString($cfg['from']);
            $to=$day->setTimeFromTimeString($cfg['to']);
            if($i===0 && $local->greaterThanOrEqualTo($from) && $local->lessThanOrEqualTo($to)) return $this->now();
            if($from->greaterThan($local)) return $from->setTimezone('UTC');
        }
        throw new \RuntimeException('No weekly time slot available');
    }
}
