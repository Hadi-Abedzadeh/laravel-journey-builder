<?php

namespace OpenJourney\LaravelJourneyBuilder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenJourney\LaravelJourneyBuilder\Services\TypedWhere;
use OpenJourney\LaravelJourneyBuilder\Services\RetryService;

/** Manual, bounded retries. External deliveries never resent without two explicit opt-ins. */
final class JourneyBuilderRetry extends Command
{
    protected $signature = 'journey-builder:retry
        {--run= : Retry one run ID}
        {--flow= : Retry failed runs for one flow ID}
        {--limit=100 : Maximum runs (1..500)}
        {--commit : Apply changes; omit to preview}
        {--retry-external : Authorize new attempt for a failed SMS/API call}
        {--ack-duplicate-risk : Acknowledge that an HTTP error does not prove non-delivery}';
    protected $description = 'Preview or manually retry failed runs; unknown/sending/sent deliveries are never resent';

    public function handle(): int
    {
        $runId = $this->option('run'); $flowId = $this->option('flow');
        if ((!$runId && !$flowId) || ($runId && (!ctype_digit((string)$runId) || (int)$runId<1)) ||
            ($flowId && (!ctype_digit((string)$flowId) || (int)$flowId<1))) {
            $this->error('Specify --run=ID or --flow=ID (positive integers).'); return self::INVALID;
        }
        $limit=(int)$this->option('limit');
        if ($limit<1 || $limit>500) { $this->error('--limit must be 1..500'); return self::INVALID; }
        $external=(bool)$this->option('retry-external');
        if ($external && !$this->option('ack-duplicate-risk')) {
            $this->error('--retry-external requires --ack-duplicate-risk.'); return self::INVALID;
        }
        $commit=(bool)$this->option('commit');
        $db=DB::connection(config('journey_builder.connection'));
        $query=TypedWhere::eq($db->table('jb_runs'), 'state', 'failed',20)->orderBy('id');
        if ($runId) $query->where('id',(int)$runId);
        if ($flowId) $query->where('flow_id',(int)$flowId);
        $runs=$query->limit($limit)->get();
        $stats=['selected'=>$runs->count(),'eligible'=>0,'retried'=>0,'skipped'=>0,'reasons'=>[]];
        foreach ($runs as $candidate) {
            $result=app(RetryService::class)->retry((int)$candidate->id, $flowId ? (int)$flowId : null, $commit, $external, (bool)$this->option('ack-duplicate-risk'));
            if ($result==='eligible') $stats['eligible']++;
            elseif ($result==='retried') $stats['retried']++;
            else { $stats['skipped']++;$stats['reasons'][$result]=($stats['reasons'][$result]??0)+1; }
        }
        $this->line(json_encode(['dry_run'=>!$commit]+$stats, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
