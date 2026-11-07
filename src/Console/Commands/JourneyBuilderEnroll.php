<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use OpenJourney\LaravelJourneyBuilder\Services\Engine;
use Illuminate\Console\Command;

class JourneyBuilderEnroll extends Command
{
    protected $signature='journey-builder:enroll {--batch=200}';
    protected $description='Enroll members in each active journey according to its own trigger';

    public function handle(Engine $engine): int
    {
        $this->line(json_encode($engine->enroll((int)$this->option('batch')), JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
