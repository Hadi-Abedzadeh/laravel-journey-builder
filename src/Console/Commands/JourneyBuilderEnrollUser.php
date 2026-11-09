<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use OpenJourney\LaravelJourneyBuilder\Services\ManualEnrollment;

final class JourneyBuilderEnrollUser extends Command
{
    protected $signature = 'journey-builder:enroll-user {--flow= : Numeric journey ID} {--user-id= : Exact source userId} {--commit : Write one run, otherwise preview only} {--ignore-trigger : Explicitly allow a known test user even when trigger does not match}';
    protected $description = 'Preview / manually enroll one existing user in a manual-only journey, without sending any SMS';

    public function handle(ManualEnrollment $service): int
    {
        $flow = filter_var($this->option('flow'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $userId = (string) ($this->option('user-id') ?? '');
        if (!$flow || $userId === '') {
            $this->error('Usage: journey-builder:enroll-user --flow=3 --user-id="EXACT_ID" [--commit]');
            return self::FAILURE;
        }
        try {
            $ignore = (bool) $this->option('ignore-trigger');
            $commit = (bool) $this->option('commit');
            if ($ignore && $commit && !$this->confirm('Trigger does not have to match. Confirm the user is a TEST account and the flow may send real SMS/API.', false)) {
                $this->warn('Cancelled.');
                return self::FAILURE;
            }
            $result = $commit ? $service->commit($flow, $userId, $ignore) : $service->check($flow, $userId, $ignore);
            $this->line(json_encode($result + ['dry_run'=>!$commit], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            if (!$commit) $this->comment('Preview only. Add --commit to enroll exactly this user. Automatic tick may execute external actions after committing.');
            return (!($result['eligible'] ?? false) && !($result['enrolled'] ?? false)) ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
