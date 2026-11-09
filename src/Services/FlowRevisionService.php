<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Store immutable graph + encrypted API credentials once per revision, not once per user. */
final class FlowRevisionService
{
    public function assertInstalled(): void
    {
        $s = Schema::connection(config('journey_builder.connection', 'sqlsrv'));
        foreach (['jb_flow_revisions','jb_revision_credentials'] as $table) {
            if (!$s->hasTable($table)) throw new RuntimeException('Run the Journey Builder monitoring/revision migration before editing a paused flow.');
        }
    }

    public function snapshotIfUsed(ConnectionInterface $db, object $flow): bool
    {
        $this->assertInstalled();
        $hasRuns = $db->table('jb_runs')->where('flow_id',$flow->id)->where('revision',$flow->revision)->exists();
        if (!$hasRuns) return false;
        $present = $db->table('jb_flow_revisions')->where('flow_id',$flow->id)->where('revision',$flow->revision)->exists();
        if (!$present) {
            $db->table('jb_flow_revisions')->insert([
                'flow_id'=>$flow->id, 'revision'=>$flow->revision, 'graph_json'=>$flow->graph_json,
                'created_at'=>now('UTC'),
            ]);
            // Historical secrets must not be read from mutable current credentials.
            foreach ($db->table('jb_api_credentials')->where('flow_id',$flow->id)->get(['node_id','encrypted_token']) as $credential) {
                $db->table('jb_revision_credentials')->insert([
                    'flow_id'=>$flow->id, 'revision'=>$flow->revision, 'node_id'=>$credential->node_id,
                    'encrypted_token'=>$credential->encrypted_token, 'created_at'=>now('UTC'),
                ]);
            }
        }
        return true;
    }

    public function graphForRun(ConnectionInterface $db, object $flow, object $run): array
    {
        if ((int)$run->revision === (int)$flow->revision) return json_decode($flow->graph_json,true,512,JSON_THROW_ON_ERROR);
        $this->assertInstalled();
        $historical = $db->table('jb_flow_revisions')->where('flow_id',$flow->id)->where('revision',$run->revision)->value('graph_json');
        if (!$historical) throw new RuntimeException('Missing immutable journey revision '.$run->revision.' for run '.$run->id);
        return json_decode($historical,true,512,JSON_THROW_ON_ERROR);
    }
}
