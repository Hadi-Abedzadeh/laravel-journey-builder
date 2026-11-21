<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use OpenJourney\LaravelJourneyBuilder\Services\SmsReceiptPoller;
use OpenJourney\LaravelJourneyBuilder\Services\CommandMonitor;

final class JourneyBuilderSmsStatus extends Command
{
    protected $signature = 'journey-builder:sms-status {--batch=500}';
    protected $description = 'Poll Kavenegar receipt status of already accepted SMS messages';

    public function handle(SmsReceiptPoller $poller): int
    {
        $this->line(json_encode(app(CommandMonitor::class)->run('sms-status',
            fn()=>$poller->poll((int)$this->option('batch'))), JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
