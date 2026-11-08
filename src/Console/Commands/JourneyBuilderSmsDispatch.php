<?php
namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use OpenJourney\LaravelJourneyBuilder\Services\SmsDispatcher;

final class JourneyBuilderSmsDispatch extends Command
{
    protected $signature = 'journey-builder:sms-dispatch {--batch=1000 : Maximum SMS rows per run, chunked into requests of at most 200}';
    protected $description = 'Safely dispatch queued managed SMS in provider-specific batches';

    public function handle(SmsDispatcher $dispatcher): int
    {
        $batch = (int) $this->option('batch');
        if ($batch < 1 || $batch > 5000) { $this->error('Batch must be 1..5000.'); return self::FAILURE; }
        $this->line(json_encode($dispatcher->dispatch($batch), JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
