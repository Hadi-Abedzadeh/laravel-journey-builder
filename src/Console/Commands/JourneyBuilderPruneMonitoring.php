<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Bounded-retention for command heartbeats; does NOT erase run or delivery records. */
final class JourneyBuilderPruneMonitoring extends Command
{
    protected $signature = 'journey-builder:monitor-prune {--days= : Retention window for command log} {--commit : Apply deletion; without this, preview only}';
    protected $description = 'Preview/prune old scheduler execution history (does not affect flows, runs, SMS or API logs)';

    public function handle(): int
    {
        $days = (int)($this->option('days') ?: config('journey_builder.monitoring.command_log_days',14));
        if ($days < 1 || $days > 3650) { $this->error('days must be between 1 and 3650.'); return self::INVALID; }
        $db = DB::connection(config('journey_builder.connection','sqlsrv'));
        $threshold = now('UTC')->subDays($days);
        $query = $db->table('jb_command_runs')->where('started_at','<',$threshold);
        $total = $query->count();
        if (!$this->option('commit')) {
            $this->line(json_encode(['dry_run'=>true,'expired_command_rows'=>$total,'retention_days'=>$days]));
            return self::SUCCESS;
        }
        // Delete old command history only; operational summary remains available from recent runs.
        $deleted = $db->table('jb_command_runs')->where('started_at','<',$threshold)->delete();
        $this->line(json_encode(['dry_run'=>false,'deleted'=>$deleted,'retention_days'=>$days]));
        return self::SUCCESS;
    }
}
