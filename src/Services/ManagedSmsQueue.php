<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Queues an SMS exactly once per (run,node); dispatch is a separate, chunked process. */
final class ManagedSmsQueue
{
    public function enqueueOrStatus(object $run, object $flow, array $config): string
    {
        $db = DB::connection(config('journey_builder.connection', 'sqlsrv'));
        $existing = TypedWhere::eq($db->table('jb_sms_logs')->where('run_id', $run->id), 'node_id', $run->node_id, 80)->first();
        if ($existing) {
            return match ($existing->status) {
                'sent' => 'sent', 'failed' => 'failed', 'unknown' => 'unknown',
                'pending', 'sending', 'retry_authorized' => 'queued',
                default => 'unknown',
            };
        }
        if (!config('journey_builder.sms.managed_enabled', false)) throw new RuntimeException('Managed SMS delivery is disabled.');
        $providerQuery = $db->table('jb_sms_providers')->whereNull('deleted_at')->where('is_enabled', 1);
        $chosen = (int) ($config['provider_id'] ?? 0);
        $provider = $chosen > 0 ? $providerQuery->where('id', $chosen)->first()
            : $providerQuery->where('is_default', 1)->first();
        if (!$provider) throw new RuntimeException('No enabled SMS provider selected/default.');
        $renderer = app(SmsTemplateRenderer::class);
        $receptor = $renderer->receptor($run->user_id, $provider);
        $message = $renderer->render($run->user_id, (string) ($config['message'] ?? ''));
        $now = now('UTC');
        // The unique(run_id,node_id) constraint is the final barrier against duplicate enqueues.
        $db->table('jb_sms_logs')->insert([
            'flow_id' => $flow->id, 'run_id' => $run->id, 'node_id' => $run->node_id,
            'user_id' => $run->user_id, 'template' => 'freeform', 'status' => 'pending',
            'attempts' => 0, 'provider_id' => $provider->id, 'sender' => $provider->sender,
            'receptor' => $receptor, 'message_text' => $message,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return 'queued';
    }
}
