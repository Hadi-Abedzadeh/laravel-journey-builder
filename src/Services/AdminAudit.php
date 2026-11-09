<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminAudit
{
    public function record(string $event, ?int $flowId, ?string $detail = null): void
    {
        if (!Schema::connection(config('journey_builder.connection','sqlsrv'))->hasTable('jb_admin_events')) return;
        $actor = request()->user()?->getAuthIdentifier();
        DB::connection(config('journey_builder.connection','sqlsrv'))->table('jb_admin_events')->insert([
            'flow_id'=>$flowId, 'event_type'=>$event,
            'actor'=>$actor===null?null:mb_substr((string)$actor,0,100),
            'details'=>$detail===null?null:mb_substr($detail,0,1000),
            'created_at'=>now('UTC'),
        ]);
    }
}
