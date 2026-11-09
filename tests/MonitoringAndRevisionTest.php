<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use Illuminate\Support\Facades\DB;
use OpenJourney\LaravelJourneyBuilder\JourneyBuilderServiceProvider;
use OpenJourney\LaravelJourneyBuilder\Services\CommandMonitor;
use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use OpenJourney\LaravelJourneyBuilder\Services\FlowRevisionService;
use Orchestra\Testbench\TestCase;
use RuntimeException;

final class MonitoringAndRevisionTest extends TestCase
{
    protected function getPackageProviders($app): array { return [JourneyBuilderServiceProvider::class]; }
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default','sqlite');
        $app['config']->set('database.connections.sqlite',[ 'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true ]);
        $app['config']->set('journey_builder.connection','sqlite');
        $app['config']->set('journey_builder.auto_schedule',false);
    }

    private function migrate(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_09_150000_add_journey_monitoring_and_revision_tables.php')->up();
    }

    public function test_existing_user_stays_on_prior_graph_revision_after_edit(): void
    {
        $this->migrate();
        $db=DB::connection('sqlite');
        $original=Definition::blank();
        $id=$db->table('jb_flows')->insertGetId([
            'code'=>'jn_revision_test','title'=>'rev test','status'=>'paused','revision'=>1,
            'graph_json'=>json_encode($original), 'created_at'=>now('UTC'),'updated_at'=>now('UTC'),
        ]);
        $db->table('jb_runs')->insert([
            'flow_id'=>$id,'revision'=>1,'user_id'=>'test-user','node_id'=>'start','state'=>'ready',
            'created_at'=>now('UTC'),'updated_at'=>now('UTC'),'next_run_at'=>now('UTC'),
        ]);
        $flow=$db->table('jb_flows')->where('id',$id)->first();
        $this->assertTrue(app(FlowRevisionService::class)->snapshotIfUsed($db,$flow));
        $db->table('jb_flows')->where('id',$id)->update(['graph_json'=>'{"nodes":[],"edges":[]}', 'revision'=>2]);
        $new=$db->table('jb_flows')->where('id',$id)->first();
        $run=$db->table('jb_runs')->where('flow_id',$id)->first();
        $this->assertSame($original,app(FlowRevisionService::class)->graphForRun($db,$new,$run));
        $this->assertSame(1,(int)$run->revision);
    }

    public function test_scheduler_heartbeats_record_success_and_failure(): void
    {
        $this->migrate();
        $monitor=app(CommandMonitor::class);
        $this->assertSame(['processed'=>2,'failed'=>0],$monitor->run('tick',static fn()=>['processed'=>2,'failed'=>0]));
        try { $monitor->run('sms-dispatch',static function () { throw new RuntimeException('test interruption'); }); }
        catch (RuntimeException $e) { $this->assertSame('test interruption',$e->getMessage()); }
        $rows=DB::connection('sqlite')->table('jb_command_runs')->orderBy('id')->get();
        $this->assertCount(2,$rows);
        $this->assertSame('success',$rows[0]->status);
        $this->assertSame('failed',$rows[1]->status);
    }
}
