<?php
namespace OpenJourney\LaravelJourneyBuilder\Tests;

use Illuminate\Support\Facades\DB;
use OpenJourney\LaravelJourneyBuilder\Http\Controllers\JourneyBuilderController;
use OpenJourney\LaravelJourneyBuilder\JourneyBuilderServiceProvider;
use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use Orchestra\Testbench\TestCase;
use ReflectionMethod;

final class PausedEditingTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [JourneyBuilderServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('journey_builder.connection', 'sqlite');
        $app['config']->set('journey_builder.auto_schedule', false);
    }

    public function test_only_unused_draft_or_paused_flow_can_be_edited(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_100000_add_journey_builder_retention_tables.php')->up();
        $db = DB::connection('sqlite');
        $id = $db->table('jb_flows')->insertGetId([
            'code'=>'jn_edit_test', 'title'=>'test', 'status'=>'paused',
            'revision'=>1, 'graph_json'=>json_encode(Definition::blank()),
            'created_at'=>now('UTC'), 'updated_at'=>now('UTC'),
        ]);
        $method = new ReflectionMethod(JourneyBuilderController::class, 'isEditableFlow');
        $controller = new JourneyBuilderController();
        $flow = fn() => $db->table('jb_flows')->where('id',$id)->first();

        $this->assertTrue($method->invoke($controller, $db, $flow()));
        $db->table('jb_flows')->where('id',$id)->update(['status'=>'active']);
        $this->assertFalse($method->invoke($controller, $db, $flow()));
        $db->table('jb_flows')->where('id',$id)->update(['status'=>'draft']);
        $this->assertTrue($method->invoke($controller, $db, $flow()));
        $db->table('jb_flows')->where('id',$id)->update(['status'=>'paused']);
        $db->table('jb_runs')->insert([
            'flow_id'=>$id,'user_id'=>'test','node_id'=>'start','state'=>'ready',
            'revision'=>1,'next_run_at'=>now('UTC'),'created_at'=>now('UTC'),'updated_at'=>now('UTC'),
        ]);
        $this->assertFalse($method->invoke($controller, $db, $flow()));
        $db->table('jb_runs')->where('flow_id',$id)->delete();
        $db->table('jb_flow_totals')->insert([
            'flow_id'=>$id, 'pruned_runs'=>1, 'updated_at'=>now('UTC'),
        ]);
        $this->assertFalse($method->invoke($controller, $db, $flow()));
    }
}
