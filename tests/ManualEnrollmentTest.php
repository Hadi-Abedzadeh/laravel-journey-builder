<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenJourney\LaravelJourneyBuilder\JourneyBuilderServiceProvider;
use OpenJourney\LaravelJourneyBuilder\Services\Engine;
use OpenJourney\LaravelJourneyBuilder\Services\ManualEnrollment;
use Orchestra\Testbench\TestCase;

final class ManualEnrollmentTest extends TestCase
{
    protected function getPackageProviders($app): array { return [JourneyBuilderServiceProvider::class]; }
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]);
        $app['config']->set('journey_builder.connection','sqlite');
        $app['config']->set('journey_builder.auto_schedule',false);
        $app['config']->set('journey_builder.retention.purge_on_exit',false);
    }

    public function test_manual_enrollment_only_adds_requested_user_and_skips_automatic_scan(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_100000_add_journey_builder_retention_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_200000_add_journey_manual_enrollment_mode.php')->up();
        Schema::create('member_data', static function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('userId',100);$t->string('section',20);$t->string('key',50);
            $t->string('value',1000)->nullable();$t->string('sign',30)->nullable();$t->dateTime('updated_at',3);
        });
        $now=now('UTC')->subMinute();
        foreach (['test-one','other-user'] as $user) {
            DB::table('member_data')->insert([
                'userId'=>$user,'section'=>'attributes','key'=>'JST','value'=>'NNN','updated_at'=>$now,
            ]);
        }
        $nodes=[
            ['id'=>'start','type'=>'trigger','x'=>20,'y'=>20,'config'=>['section'=>'attributes','sign'=>'','key'=>'JST','operator'=>'equals','value'=>'NNN']],
            ['id'=>'finish','type'=>'exit','x'=>50,'y'=>80,'config'=>[]],
        ];
        $flowId=DB::table('jb_flows')->insertGetId([
            'code'=>'jn_manual','title'=>'Manual test','status'=>'active','revision'=>1,'enrollment_mode'=>'manual',
            'graph_json'=>json_encode(['nodes'=>$nodes,'edges'=>[['id'=>'edge','source'=>'start','target'=>'finish','port'=>'next']]]),
            'created_at'=>$now,'updated_at'=>$now,
        ]);
        $service=new ManualEnrollment();
        $this->assertSame(true,$service->check($flowId,'test-one')['eligible']);
        $this->assertSame([], (new Engine())->enroll(100));
        $this->assertSame(0,DB::table('jb_runs')->count());
        $this->assertTrue($service->commit($flowId,'test-one')['enrolled']);
        $this->assertFalse($service->commit($flowId,'test-one')['enrolled']);
        $this->assertSame(1,DB::table('jb_runs')->count());
        $this->assertSame('test-one',DB::table('jb_runs')->first()->user_id);
        $this->assertSame([], (new Engine())->enroll(100));
        $this->assertSame(1,DB::table('jb_runs')->count());
    }

    public function test_manual_user_must_match_latest_attribute_unless_operator_explicitly_overrides(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_100000_add_journey_builder_retention_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_200000_add_journey_manual_enrollment_mode.php')->up();
        Schema::create('member_data', static function (Blueprint $t) {
            $t->bigIncrements('id');$t->string('userId',100);$t->string('section',20);
            $t->string('key',50);$t->string('value',1000)->nullable();$t->string('sign',30)->nullable();$t->dateTime('updated_at',3);
        });
        DB::table('member_data')->insert([
            'userId'=>'test-other','section'=>'attributes','key'=>'JST','value'=>'YES','updated_at'=>now('UTC'),
        ]);
        $flowId=DB::table('jb_flows')->insertGetId([
            'code'=>'jn_manual2','title'=>'Manual test','status'=>'active','revision'=>1,'enrollment_mode'=>'manual',
            'graph_json'=>json_encode(['nodes'=>[
                ['id'=>'start','type'=>'trigger','x'=>20,'y'=>20,'config'=>['section'=>'attributes','sign'=>'','key'=>'JST','operator'=>'equals','value'=>'NNN']],
                ['id'=>'finish','type'=>'exit','x'=>50,'y'=>80,'config'=>[]],
            ],'edges'=>[['id'=>'edge','source'=>'start','target'=>'finish','port'=>'next']]]),
            'created_at'=>now('UTC'),'updated_at'=>now('UTC'),
        ]);
        $service=new ManualEnrollment();
        $this->assertFalse($service->check($flowId,'test-other')['eligible']);
        $this->assertTrue($service->check($flowId,'test-other',true)['eligible']);
    }
}
