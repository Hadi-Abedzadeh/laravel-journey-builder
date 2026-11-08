<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use OpenJourney\LaravelJourneyBuilder\JourneyBuilderServiceProvider;
use OpenJourney\LaravelJourneyBuilder\Services\Engine;
use OpenJourney\LaravelJourneyBuilder\Services\RetryService;
use OpenJourney\LaravelJourneyBuilder\Services\RunRetention;
use OpenJourney\LaravelJourneyBuilder\Services\SmsGateway;
use Orchestra\Testbench\TestCase;

final class RetryAndRetentionTest extends TestCase
{
    protected function getPackageProviders($app): array { return [JourneyBuilderServiceProvider::class]; }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default','sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]);
        $app['config']->set('journey_builder.connection','sqlite');
        $app['config']->set('journey_builder.auto_schedule',false);
        $app['config']->set('journey_builder.retention.purge_on_exit',false);
    }

    private function migrate(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_100000_add_journey_builder_retention_tables.php')->up();
    }

    private function makeFlow(bool $withSms = true): array
    {
        $start = ['id'=>'start', 'type'=>'trigger', 'x'=>20, 'y'=>20,
            'config'=>['section'=>'attributes','key'=>'JST','operator'=>'equals','value'=>'NNN','sign'=>'']];
        $sms=['id'=>'sms1','type'=>'sms','x'=>100,'y'=>20,'config'=>['template'=>'test_sms','message'=>'']];
        $exit=['id'=>'exit1','type'=>'exit','x'=>300,'y'=>20,'config'=>[]];
        $nodes=$withSms?[$start,$sms,$exit]:[$start,$exit];
        $edges=$withSms?[
            ['id'=>'e1','source'=>'start','port'=>'next','target'=>'sms1'],
            ['id'=>'e2','source'=>'sms1','port'=>'success','target'=>'exit1'],
        ]:[['id'=>'e1','source'=>'start','port'=>'next','target'=>'exit1']];
        $now=now('UTC');
        $flowId=DB::table('jb_flows')->insertGetId([
            'code'=>'jn_'.bin2hex(random_bytes(8)), 'title'=>'Testing flow', 'status'=>'active',
            'revision'=>1, 'graph_json'=>json_encode(['nodes'=>$nodes,'edges'=>$edges]),
            'enroll_cursor_at'=>null,'enroll_cursor_id'=>0,'created_at'=>$now,'updated_at'=>$now,
        ]);
        return [$flowId, $nodes];
    }

    public function test_failed_sms_can_resume_same_node_after_explicit_retry(): void
    {
        $this->migrate();
        [$flowId] = $this->makeFlow();
        $now=now('UTC');
        $runId=DB::table('jb_runs')->insertGetId([
            'flow_id'=>$flowId,'user_id'=>'user1','node_id'=>'sms1','state'=>'ready','revision'=>1,
            'next_run_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $gateway = new class extends SmsGateway {
            public int $calls=0;
            public function send(string $userId,string $template,string $message,string $dedupeKey): array {
                $this->calls++;
                return ['status'=>$this->calls===1?'failed':'sent','http_status'=>$this->calls===1?503:200,'error'=>$this->calls===1?'HTTP 503':null];
            }
        };
        app()->instance(SmsGateway::class,$gateway);
        (new Engine)->tick(10);
        $run=DB::table('jb_runs')->where('id',$runId)->first();
        self::assertSame('failed',$run->state);
        self::assertSame('sms1',$run->node_id);
        self::assertSame('explicit_external_retry_and_ack_required',(new RetryService)->retry($runId,$flowId,true,false,false));
        self::assertSame('eligible',(new RetryService)->retry($runId,$flowId,false,true,true));
        self::assertSame('retried',(new RetryService)->retry($runId,$flowId,true,true,true));
        self::assertSame('sms1',DB::table('jb_runs')->where('id',$runId)->value('node_id'));
        (new Engine)->tick(10);
        self::assertSame('exited',DB::table('jb_runs')->where('id',$runId)->value('state'));
        self::assertSame(2,$gateway->calls);
        self::assertSame(2, (int)DB::table('jb_sms_logs')->where('run_id',$runId)->value('attempts'));
    }

    public function test_unknown_sms_is_not_retried_even_with_acknowledgement(): void
    {
        $this->migrate();
        [$flowId]=$this->makeFlow(); $now=now('UTC');
        $runId=DB::table('jb_runs')->insertGetId([
            'flow_id'=>$flowId,'user_id'=>'user2','node_id'=>'sms1','state'=>'failed','revision'=>1,
            'next_run_at'=>null,'created_at'=>$now,'updated_at'=>$now,
        ]);
        DB::table('jb_sms_logs')->insert([
            'flow_id'=>$flowId,'run_id'=>$runId,'node_id'=>'sms1','user_id'=>'user2',
            'template'=>'test_sms','status'=>'unknown','attempts'=>1,'created_at'=>$now,'updated_at'=>$now,
        ]);
        self::assertSame('delivery_unknown_blocked',(new RetryService)->retry($runId,$flowId,true,true,true));
        self::assertSame('failed',DB::table('jb_runs')->where('id',$runId)->value('state'));
    }

    public function test_exit_pruning_keeps_aggregate_and_deduplication_without_user_records(): void
    {
        $this->migrate();
        config()->set('journey_builder.retention.purge_on_exit',true);
        config()->set('journey_builder.retention.enrollment_hash_key',str_repeat('k',48));
        [$flowId]=$this->makeFlow(false); $now=now('UTC');
        $runId=DB::table('jb_runs')->insertGetId([
            'flow_id'=>$flowId,'user_id'=>'private-user-id','node_id'=>'exit1','state'=>'ready','revision'=>1,
            'next_run_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
        ]);
        DB::table('jb_step_logs')->insert([
            'flow_id'=>$flowId,'run_id'=>$runId,'user_id'=>'private-user-id',
            'from_node_id'=>'start','to_node_id'=>'exit1','port'=>'next','created_at'=>$now,
        ]);
        (new Engine)->tick(10);
        self::assertSame(0,DB::table('jb_runs')->where('id',$runId)->count());
        self::assertSame(0,DB::table('jb_step_logs')->where('run_id',$runId)->count());
        self::assertSame(1,(int)DB::table('jb_flow_totals')->where('flow_id',$flowId)->value('pruned_runs'));
        self::assertSame(1,(int)DB::table('jb_node_totals')->where('flow_id',$flowId)->where('node_id','exit1')->value('arrivals'));
        self::assertTrue(DB::table('jb_run_hashes')->where('flow_id',$flowId)
            ->where('user_digest',(new RunRetention)->userDigest('private-user-id'))->exists());
        self::assertFalse((new RunRetention)->pruneExitedRun($runId));
    }
}
