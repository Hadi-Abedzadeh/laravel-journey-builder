<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Measures ACTUAL Artisan executions; schedule:list alone is not a heartbeat. */
final class CommandMonitor
{
    public function run(string $name, \Closure $callback): array
    {
        $db = DB::connection(config('journey_builder.connection', 'sqlsrv'));
        $available = Schema::connection(config('journey_builder.connection', 'sqlsrv'))->hasTable('jb_command_runs');
        $started = microtime(true);
        $id = null;
        if ($available) {
            $id = $db->table('jb_command_runs')->insertGetId([
                'command_name'=>$name, 'status'=>'running', 'started_at'=>now('UTC'),
                'processed'=>0,'failed'=>0,
            ]);
        }
        try {
            $result = $callback();
            $processed = $name === 'enroll'
                ? array_sum(array_map(static fn($r)=>(int)($r['scanned']??0),$result))
                : (int)($result[$name==='sms-dispatch'?'claimed':'processed'] ?? 0);
            $failed = (int)($result['failed'] ?? 0);
            if ($id) $db->table('jb_command_runs')->where('id',$id)->update([
                'status'=>$failed ? 'partial' : 'success', 'finished_at'=>now('UTC'),
                'duration_ms'=>min(PHP_INT_MAX, (int)round((microtime(true)-$started)*1000)),
                'processed'=>$processed,'failed'=>$failed,
                'details'=>mb_substr(json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),0,2000),
            ]);
            return $result;
        } catch (Throwable $e) {
            if ($id) {
                try {
                    $db->table('jb_command_runs')->where('id',$id)->update([
                        'status'=>'failed','finished_at'=>now('UTC'),
                        'duration_ms'=>min(PHP_INT_MAX, (int)round((microtime(true)-$started)*1000)),
                        'failed'=>1,'error_message'=>mb_substr($e->getMessage(),0,2000),
                    ]);
                } catch (Throwable $loggingFailure) { report($loggingFailure); }
            }
            throw $e;
        }
    }
}
