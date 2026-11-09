<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use OpenJourney\LaravelJourneyBuilder\Services\ChannelDispatcher;
use OpenJourney\LaravelJourneyBuilder\Services\CommandMonitor;

final class JourneyBuilderChannelDispatch extends Command
{
    protected $signature='journey-builder:channel-dispatch {channel : email or webpush} {--batch=200 : Maximum rows per execution}';
    protected $description='Dispatch email and Najva push outbox rows with per-provider quotas';
    public function handle(ChannelDispatcher $dispatcher): int
    {
        $channel=(string)$this->argument('channel');$batch=(int)$this->option('batch');
        if (!in_array($channel,['email','webpush'],true)||$batch<1||$batch>1000) {
            $this->error('Usage: channel-dispatch email|webpush --batch=1..1000');return self::FAILURE;
        }
        $result=app(CommandMonitor::class)->run($channel.'-dispatch',fn()=>$dispatcher->dispatch($channel,$batch));
        $this->line(json_encode($result,JSON_UNESCAPED_UNICODE));return self::SUCCESS;
    }
}
