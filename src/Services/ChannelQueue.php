<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Per-(run,node,channel) durable outbox, with separate background dispatch. */
final class ChannelQueue
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection', 'sqlsrv'));
    }

    public function enqueueOrStatus(object $run, object $flow, array $cfg, string $channel): string
    {
        if (!in_array($channel,['email','webpush'],true)) throw new RuntimeException('Invalid delivery channel.');
        if (!Schema::connection(config('journey_builder.connection','sqlsrv'))->hasTable('jb_channel_dispatches')) {
            throw new RuntimeException('Apply Journey Builder email/webpush migration first.');
        }
        $db=$this->db();
        $row=TypedWhere::eq(TypedWhere::eq($db->table('jb_channel_dispatches')->where('run_id',$run->id),
            'channel',$channel,12),'node_id',$run->node_id,80)->first();
        if ($row && $row->status==='sending' && $row->locked_at &&
            \Carbon\CarbonImmutable::parse($row->locked_at,'UTC')->lessThan(now('UTC')->subMinutes(5))) {
            // Worker disappeared mid-request; never blindly replay a possibly accepted delivery.
            $db->table('jb_channel_dispatches')->where('id',$row->id)->where('status','sending')
                ->update(['status'=>'unknown','last_error'=>'Worker lease expired; remote outcome unknown',
                    'locked_at'=>null,'updated_at'=>now('UTC')]);
            $row->status='unknown';
        }
        if ($row) return match($row->status) {
            'sent'=>'sent','failed'=>'failed','unknown'=>'unknown',
            'pending','sending','retry_authorized'=>'queued',
            default=>'unknown',
        };
        if (!config('journey_builder.channels.'.$channel.'.enabled',false)) throw new RuntimeException('Channel is disabled on server.');
        $table=$channel==='email'?'jb_email_providers':'jb_push_providers';
        $q=$db->table($table)->where('is_enabled',1)->whereNull('deleted_at');
        $providerId=(int)($cfg['provider_id']??0);
        $provider=$providerId>0?$q->where('id',$providerId)->first():$q->where('is_default',1)->first();
        if (!$provider) throw new RuntimeException('No enabled/default provider for '.$channel);
        $renderer=app(SmsTemplateRenderer::class);
        if ($channel==='email') {
            $address=trim($renderer->attributeValue((string)$run->user_id, $cfg['recipient_attribute']??'email', $cfg['recipient_sign']??'' )??'');
            if (!filter_var($address,FILTER_VALIDATE_EMAIL) || strlen($address)>254) throw new RuntimeException('Invalid email address for this user.');
            $payload=[
                'subject'=>$renderer->render((string)$run->user_id,(string)($cfg['subject']??''),200),
                'body'=>$renderer->render((string)$run->user_id,(string)($cfg['body']??''),20000,($cfg['format']??'text')==='html'),
                'format'=>$cfg['format']??'text',
            ];
            $recipient=$address;
        } else {
            $payload=[
                'title'=>$renderer->render((string)$run->user_id,(string)($cfg['title']??''),50),
                'body'=>$renderer->render((string)$run->user_id,(string)($cfg['body']??''),150),
                'url'=>$cfg['url']??'', 'ttl'=>(int)($cfg['ttl']??24),
                'button_title'=>$cfg['button_title']??'', 'button_url'=>$cfg['button_url']??'',
            ];
            $recipient=null; // Push targets verified, opt-in tokens bound to userId, never a phone number.
            if (!TypedWhere::eq($db->table('jb_push_subscriptions')->where('provider_id',$provider->id)
                ->where('is_enabled',1),'user_id',$run->user_id,100)->exists()) {
                throw new RuntimeException('User has no registered Najva push subscription for this service.');
            }
        }
        $now=now('UTC');
        try {
            $db->table('jb_channel_dispatches')->insert([
                'flow_id'=>$flow->id,'run_id'=>$run->id,'node_id'=>$run->node_id,
                'user_id'=>$run->user_id,'channel'=>$channel,'provider_id'=>$provider->id,
                'recipient'=>$recipient,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                'status'=>'pending','attempts'=>0,'available_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (!preg_match('/\b(2601|2627)\b/',implode(' ',(array)($e->errorInfo??[])))) throw $e;
        }
        return 'queued';
    }
}
