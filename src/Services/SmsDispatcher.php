<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;

/** Claim/send in short chunks: a crash never leaves thousands of unsent rows locked. */
final class SmsDispatcher
{
    public function dispatch(int $batch = 1000): array
    {
        if (!config('journey_builder.sms.managed_enabled', false)) {
            return ['claimed'=>0,'sent'=>0,'failed'=>0,'unknown'=>0,'disabled'=>true];
        }
        $db = DB::connection(config('journey_builder.connection', 'sqlsrv'));
        $batch = min(5000, max(1, $batch));
        $chunkSize = min(200, max(1, (int)config('journey_builder.sms.chunk_size', 200)));
        $counts = ['claimed'=>0,'sent'=>0,'failed'=>0,'unknown'=>0,'requests'=>0];
        $now = now('UTC');
        // A timeout or crash might have happened after provider acceptance.
        // Never automatically resend an old 'sending' row.
        TypedWhere::eq($db->table('jb_sms_logs')->whereNotNull('provider_id')
            ->where('updated_at', '<', $now->copy()->subMinutes(10)), 'status', 'sending', 20)
            ->update(['status'=>'unknown','error_message'=>'Dispatch worker interrupted; manual reconciliation required','updated_at'=>$now]);

        while ($counts['claimed'] < $batch) {
            $take = min($chunkSize, $batch - $counts['claimed']);
            $rows = $db->transaction(function () use ($db, $take) {
                $rows = TypedWhere::oneOf(
                    TypedWhere::eq($db->table('jb_sms_logs as s')
                        ->join('jb_flows as f','f.id','=','s.flow_id')
                        ->join('jb_sms_providers as p','p.id','=','s.provider_id'), 'f.status', 'active', 20),
                    's.status', ['pending','retry_authorized'], 20)
                    ->where('p.is_enabled', 1)->whereNull('p.deleted_at')
                    ->orderBy('s.id')->limit($take)->lockForUpdate()
                    ->get(['s.*']);
                foreach ($rows as $row) {
                    $db->table('jb_sms_logs')->where('id', $row->id)->update([
                        'status'=>'sending','attempts'=>(int)$row->attempts+1,'updated_at'=>now('UTC'),
                    ]);
                }
                return $rows;
            }, 3);
            if ($rows->isEmpty()) break;
            $counts['claimed'] += $rows->count();

            foreach ($rows->groupBy('provider_id') as $providerId => $group) {
                $provider = $db->table('jb_sms_providers')->where('id', $providerId)->first();
                $items = $group->all();
                $counts['requests']++;
                $result = $provider && $provider->driver === 'kavenegar'
                    ? app(KavenegarClient::class)->sendArray($provider, $items)
                    : array_fill_keys(array_map(static fn($r) => (int)$r->id, $items), [
                        'status'=>'unknown','http_status'=>null,'error'=>'Unknown SMS provider; manual reconciliation required','provider_message_id'=>null,
                    ]);
                foreach ($items as $row) {
                    $status = $result[(int)$row->id] ?? [
                        'status'=>'unknown','http_status'=>null,'error'=>'Missing provider result','provider_message_id'=>null,
                    ];
                    $db->table('jb_sms_logs')->where('id', $row->id)->update([
                        'status'=>$status['status'],'http_status'=>$status['http_status'],
                        'error_message'=>$status['error'],'provider_message_id'=>$status['provider_message_id'],
                        'sent_at'=>$status['status']==='sent'?now('UTC'):null,
                        'updated_at'=>now('UTC'),
                    ]);
                    $counts[$status['status']]++;
                }
            }
        }
        return $counts;
    }
}
