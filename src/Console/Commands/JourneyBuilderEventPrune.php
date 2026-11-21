<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenJourney\LaravelJourneyBuilder\Services\TypedWhere;

/** Conservatively removes only event history older than every unfinished run. */
final class JourneyBuilderEventPrune extends Command
{
    protected $signature = 'journey-builder:event-prune {--days=120} {--batch=1000} {--commit}';
    protected $description = 'Prune old event history without affecting currently running journeys';

    public function handle(): int
    {
        $days=(int)$this->option('days');
        $batch=(int)$this->option('batch');
        if ($days < 91 || $batch < 1 || $batch > 10000) {
            $this->error('Days must be >=91 and batch must be 1..10000.');
            return self::FAILURE;
        }
        $connection=config('journey_builder.connection','sqlsrv');
        if (!Schema::connection($connection)->hasTable('jb_event_occurrences')) {
            $this->error('Install the Journey Builder event history migration first.');
            return self::FAILURE;
        }
        $db=DB::connection($connection);
        $cutoff=CarbonImmutable::now('UTC')->subDays($days);
        // A condition scoped after_enroll might still reference any event after the oldest
        // unfinished run began, even if that run is paused or waiting for operator input.
        $oldest = TypedWhere::oneOf($db->table('jb_runs'),'state',['ready','waiting','failed'],20)
            ->min('created_at');
        if ($oldest) {
            $oldestTime=CarbonImmutable::parse($oldest,'UTC');
            if ($oldestTime->lessThan($cutoff)) $cutoff=$oldestTime;
        }
        $stamp=$cutoff->format('Y-m-d H:i:s.v');
        $ids=$db->table('jb_event_occurrences')->where('occurred_at','<',$stamp)
            ->orderBy('occurred_at')->orderBy('id')->limit($batch)->pluck('id');
        if ($this->option('commit') && $ids->isNotEmpty()) {
            $deleted=$db->table('jb_event_occurrences')->whereIn('id',$ids->all())
                ->where('occurred_at','<',$stamp)->delete();
        } else { $deleted=0; }
        $this->line(json_encode([
            'eligible_in_batch'=>$ids->count(), 'deleted'=>$deleted,
            'cutoff_utc'=>$stamp, 'dry_run'=>!$this->option('commit'),
        ],JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
