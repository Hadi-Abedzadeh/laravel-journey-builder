<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;

/** Reconcile accepted SMS submissions against Kavenegar delivery status. */
final class SmsReceiptPoller
{
    public static function classify(int $providerCode): string
    {
        return match ($providerCode) {
            1, 2 => 'queued',
            4, 5 => 'submitted',
            10 => 'delivered',
            6, 11, 13, 14 => 'undelivered',
            100 => 'unknown',
            default => 'unknown',
        };
    }

    public function poll(int $batch = 500): array
    {
        if (!config('journey_builder.sms.receipt_poll_enabled', false)) {
            return ['checked'=>0,'delivered'=>0,'undelivered'=>0,'unknown'=>0,'disabled'=>true];
        }
        $db = DB::connection(config('journey_builder.connection','sqlsrv'));
        $now = now('UTC');
        $counts = ['checked'=>0,'delivered'=>0,'undelivered'=>0,'unknown'=>0];
        // Never leave a receipt-waiting run blocked forever after provider retention expires.
        // Kavenegar documents a 48-hour status lookup window; 47h is a conservative cutoff.
        TypedWhere::eq($db->table('jb_sms_logs')->whereNotNull('provider_message_id')
            ->where('sent_at','<=',$now->copy()->subHours(47))
            ->where(function($q) {
                $q->whereNull('delivery_status')->orWhere(function ($q) {
                    TypedWhere::oneOf($q,'delivery_status',['queued','submitted'],20);
                });
            }), 'status','sent',20)->update([
                'delivery_status'=>'unknown','delivery_checked_at'=>$now,'updated_at'=>$now,
            ]);
        $rows = TypedWhere::eq($db->table('jb_sms_logs')->whereNotNull('provider_id')
            ->whereNotNull('provider_message_id'), 'status', 'sent', 20)
            ->where(function($q) { $q->whereNull('delivery_status')->orWhere(function ($q) { TypedWhere::oneOf($q,'delivery_status',['queued','submitted'],20); }); })
            ->where('sent_at','>', $now->copy()->subHours(47))
            ->orderByRaw('COALESCE([delivery_checked_at], [sent_at]) ASC')
            ->orderBy('id')->limit(min(2000,max(1,$batch)))
            ->get(['id','provider_id','provider_message_id']);

        foreach ($rows->groupBy('provider_id') as $providerId => $messages) {
            $provider = $db->table('jb_sms_providers')->where('id',$providerId)->first();
            if (!$provider || $provider->driver !== 'kavenegar') continue;
            foreach ($messages->chunk(500) as $chunk) {
                $statuses = app(KavenegarClient::class)->statuses($provider,
                    $chunk->pluck('provider_message_id')->all());
                foreach ($chunk as $item) {
                    $id = (string)$item->provider_message_id;
                    if (!array_key_exists($id, $statuses)) continue;
                    $code = $statuses[$id];
                    $class = self::classify($code);
                    $update = [
                        'delivery_status'=>$class,
                        'delivery_code'=>$code,
                        'delivery_checked_at'=>$now,
                        'updated_at'=>$now,
                    ];
                    if ($class === 'delivered') $update['delivered_at'] = $now;
                    $changed = $db->table('jb_sms_logs')->where('id',$item->id)
                        ->where('provider_message_id',$item->provider_message_id)
                        ->where(function ($q) {
                            $q->whereNull('delivery_status')->orWhere(function ($q) {
                                TypedWhere::oneOf($q,'delivery_status',['queued','submitted'],20);
                            });
                        })->update($update);
                    if ($changed) {
                        $counts['checked']++;
                        if (isset($counts[$class])) $counts[$class]++;
                    }
                }
            }
        }
        return $counts;
    }
}
