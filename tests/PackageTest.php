<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use OpenJourney\LaravelJourneyBuilder\JourneyBuilderServiceProvider;
use Orchestra\Testbench\TestCase;

final class PackageTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [JourneyBuilderServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]);
        $app['config']->set('journey_builder.connection', 'sqlite');
        $app['config']->set('journey_builder.dashboard_access', 'auth');
        $app['config']->set('journey_builder.auto_schedule', false);
    }

    public function test_package_bootstraps_and_has_no_separate_login_routes(): void
    {
        $this->assertTrue(Route::has('jb.index'));
        $this->assertTrue(Route::has('jb.status'));
        $this->assertFalse(Route::has('jb.login'));
        $this->assertFalse(Route::has('jb.logout'));
        $this->assertTrue(Route::has('jb.sms.providers'));
    }

    public function test_package_migration_is_non_destructive_and_creates_audit(): void
    {
        $file = __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php';
        $migration = require $file;
        $migration->up();
        foreach (['jb_flows','jb_runs','jb_sms_logs','jb_api_logs','jb_step_logs','jb_api_credentials','jb_retry_audit'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        $retention = require __DIR__.'/../database/migrations/2026_11_08_100000_add_journey_builder_retention_tables.php';
        $retention->up();
        foreach (['jb_run_hashes','jb_flow_totals','jb_node_totals'] as $table) $this->assertTrue(Schema::hasTable($table));
        $smsMigration = require __DIR__.'/../database/migrations/2026_11_08_180000_add_journey_sms_providers.php';
        $smsMigration->up();
        $this->assertTrue(Schema::hasTable('jb_sms_providers'));
        foreach (['provider_id','sender','receptor','message_text','provider_message_id'] as $column) {
            $this->assertTrue(Schema::hasColumn('jb_sms_logs',$column));
        }
        // Re-running the up() method does not alter existing tables or records.
        $migration->up();
        $retention->up();
        $smsMigration->up();
        $migration->down();
        $retention->down();
        $this->assertTrue(Schema::hasTable('jb_runs'));
    }

    public function test_status_command_does_not_require_flows(): void
    {
        $migration = require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php';
        $migration->up();
        $exit = Artisan::call('journey-builder:status', ['--json'=>true]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('[]', Artisan::output());
    }

    public function test_retry_without_run_or_flow_is_rejected(): void
    {
        $this->assertNotSame(0, Artisan::call('journey-builder:retry', []));
    }
}
