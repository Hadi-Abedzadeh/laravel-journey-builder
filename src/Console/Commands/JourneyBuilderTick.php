<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use OpenJourney\LaravelJourneyBuilder\Services\Engine;
use OpenJourney\LaravelJourneyBuilder\Services\CommandMonitor;
use Illuminate\Console\Command;

class JourneyBuilderTick extends Command
{
    protected $signature='journey-builder:tick {--batch=100}';
    protected $description='Advance active drag-and-drop journeys and dispatch pending SMS actions';

    public function handle(Engine $engine): int
    {
        $result=app(CommandMonitor::class)->run('tick', fn()=>$engine->tick((int)$this->option('batch')));
        $this->line(json_encode($result));
        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
