<?php

namespace OpenJourney\LaravelJourneyBuilder;

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderEnroll;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderTick;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderStatus;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderRetry;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderPrune;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderSmsDispatch;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderChannelDispatch;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderEnrollUser;
use OpenJourney\LaravelJourneyBuilder\Console\Commands\JourneyBuilderPruneMonitoring;

final class JourneyBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $defaults = require __DIR__ . '/../config/journey_builder.php';
        $this->mergeConfigFrom(__DIR__ . '/../config/journey_builder.php', 'journey_builder');
        // Shallow Laravel merge would drop new nested sms settings for hosts with an old published config.
        $this->app['config']->set('journey_builder', array_replace_recursive(
            $defaults, (array) $this->app['config']->get('journey_builder', [])
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'journey-builder');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/journey_builder.php' => config_path('journey_builder.php'),
        ], 'journey-builder-config');
        $this->publishes([
            __DIR__ . '/../resources/assets' => public_path('vendor/journey-builder'),
        ], 'journey-builder-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([
                JourneyBuilderEnroll::class, JourneyBuilderTick::class,
                JourneyBuilderStatus::class, JourneyBuilderRetry::class, JourneyBuilderPrune::class, JourneyBuilderSmsDispatch::class, JourneyBuilderEnrollUser::class, JourneyBuilderPruneMonitoring::class, JourneyBuilderChannelDispatch::class,
            ]);
        }

        // Disabled by default because existing installations have schedule definitions in routes/console.php.
        if (config('journey_builder.auto_schedule', false)) {
            Schedule::command('journey-builder:enroll --batch=1000')->everyMinute()->withoutOverlapping();
            Schedule::command('journey-builder:tick --batch=100')->everyMinute()->withoutOverlapping();
            Schedule::command('journey-builder:sms-dispatch --batch=1000')->everyMinute()->withoutOverlapping();
            Schedule::command('journey-builder:channel-dispatch email --batch=200')->everyMinute()->withoutOverlapping();
            Schedule::command('journey-builder:channel-dispatch webpush --batch=200')->everyMinute()->withoutOverlapping();
            Schedule::command('journey-builder:monitor-prune --commit')->dailyAt('02:15')->withoutOverlapping();
        }
    }
}
