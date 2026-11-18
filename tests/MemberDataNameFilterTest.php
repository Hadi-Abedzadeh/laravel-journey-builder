<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenJourney\LaravelJourneyBuilder\JourneyBuilderServiceProvider;
use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use OpenJourney\LaravelJourneyBuilder\Services\Engine;
use OpenJourney\LaravelJourneyBuilder\Services\ManualEnrollment;
use OpenJourney\LaravelJourneyBuilder\Services\TriggerMatcher;
use Orchestra\Testbench\TestCase;
use ReflectionMethod;

final class MemberDataNameFilterTest extends TestCase
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
        $app['config']->set('journey_builder.retention.enrollment_hash_key','');
    }

    protected function setUp(): void
    {
        parent::setUp();
        (require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_200000_add_journey_manual_enrollment_mode.php')->up();
        Schema::create('member_data', static function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('userId',100);
            $t->string('section',20);
            $t->string('name',50)->nullable();
            $t->string('key',50);
            $t->string('value',100)->nullable();
            $t->string('sign',30)->nullable();
            $t->dateTime('updated_at',3);
        });
        foreach ([
            ['u-eligible','Reg_Started','NationalCode',null],
            ['u-not-empty','Reg_Started','NationalCode','1234567890'],
            ['u-other-event','Reg_Done','NationalCode',null],
            ['u-other-key','Reg_Started','RegistrationDate',null],
        ] as [$userId,$name,$key,$value]) {
            DB::table('member_data')->insert([
                'userId'=>$userId,'section'=>'events','name'=>$name,'key'=>$key,'value'=>$value,
                'sign'=>'membershipRegistration','updated_at'=>now('UTC')->subMinute(),
            ]);
        }
    }

    private function graph(): array
    {
        return [
            'nodes'=>[
                ['id'=>'start','type'=>'trigger','x'=>25,'y'=>20,'config'=>[
                    'section'=>'events','name'=>'Reg_Started','sign'=>'membershipRegistration',
                    'key'=>'NationalCode','operator'=>'is_empty','value'=>'',
                ]],
                ['id'=>'exit','type'=>'exit','x'=>25,'y'=>200,'config'=>[]],
            ],
            'edges'=>[['id'=>'link','source'=>'start','port'=>'next','target'=>'exit']],
        ];
    }

    private function createFlow(string $mode = 'automatic'): int
    {
        static $number = 0;
        $number++;
        return (int) DB::table('jb_flows')->insertGetId([
            'code'=>'jn_filter_'.$number,'title'=>'Event filter test','status'=>'active','revision'=>1,
            'enrollment_mode'=>$mode,'graph_json'=>json_encode($this->graph(), JSON_THROW_ON_ERROR),
            'created_at'=>now('UTC'),'updated_at'=>now('UTC'),
        ]);
    }

    public function test_empty_filter_requires_a_real_row_and_accepts_null_or_blank(): void
    {
        $this->assertTrue(TriggerMatcher::matches(null, 'is_empty'));
        $this->assertTrue(TriggerMatcher::matches('', 'is_empty'));
        $this->assertTrue(TriggerMatcher::matches('   ', 'is_empty'));
        $this->assertFalse(TriggerMatcher::matches('123', 'is_empty'));
        $this->assertTrue(TriggerMatcher::matches('123', 'is_not_empty'));
        $this->assertFalse(TriggerMatcher::matches(null, 'is_not_empty'));
        Definition::validate($this->graph(), true);
    }

    public function test_automatic_enrollment_combines_section_name_key_and_null_value(): void
    {
        $id = $this->createFlow();
        $result = (new Engine())->enroll(50);
        $this->assertCount(1, $result);
        $this->assertSame(2, array_values($result)[0]['scanned']);
        $this->assertSame(1, array_values($result)[0]['enrolled']);
        $this->assertSame('u-eligible', DB::table('jb_runs')->where('flow_id',$id)->value('user_id'));
    }

    public function test_manual_enrollment_uses_the_same_name_and_null_filter(): void
    {
        $id = $this->createFlow('manual');
        $service = new ManualEnrollment();
        $this->assertTrue($service->check($id, 'u-eligible')['trigger_matches']);
        $this->assertFalse($service->check($id, 'u-not-empty')['trigger_matches']);
        $this->assertFalse($service->check($id, 'u-other-event')['trigger_matches']);
        $this->assertFalse($service->check($id, 'u-other-key')['trigger_matches']);
        $this->assertTrue($service->commit($id, 'u-eligible')['enrolled']);
    }

    public function test_mid_journey_conditions_can_filter_named_event_fields(): void
    {
        $engine = new Engine();
        $attribute = new ReflectionMethod(Engine::class, 'attributeMatches');
        $cfg = ['section'=>'events','name'=>'Reg_Started','key'=>'NationalCode',
            'sign'=>'membershipRegistration','operator'=>'is_empty','value'=>''];
        $this->assertTrue($attribute->invoke($engine,'u-eligible',$cfg));
        $this->assertFalse($attribute->invoke($engine,'u-not-empty',$cfg));
        $this->assertFalse($attribute->invoke($engine,'u-other-event',$cfg));

        $event = new ReflectionMethod(Engine::class, 'eventSeen');
        $eventCfg = ['event'=>'Reg_Started','sign'=>'membershipRegistration',
            'key'=>'NationalCode','operator'=>'is_empty','value'=>''];
        $this->assertTrue($event->invoke($engine,'u-eligible',$eventCfg));
        $this->assertFalse($event->invoke($engine,'u-not-empty',$eventCfg));
        $this->assertFalse($event->invoke($engine,'u-other-event',$eventCfg));
    }
}
