<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Durable outbox dispatcher. Concurrency-safe row claims, SMTP connection reuse,
 * per-account rate limits. Ambiguous SMTP/HTTP outcomes are NEVER auto-replayed.
 */
final class ChannelDispatcher
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection','sqlsrv'));
    }

    public function dispatch(string $channel, int $batch=200): array
    {
        if (!in_array($channel,['email','webpush'],true)) throw new \InvalidArgumentException('Unknown channel.');
        if (!config('journey_builder.channels.'.$channel.'.enabled',false)) return ['processed'=>0,'disabled'=>true];
        $db=$this->db(); $stats=['processed'=>0,'sent'=>0,'failed'=>0,'unknown'=>0,'rate_limited'=>0];
        $smtp=[];
        try {
            for ($i=0; $i<min(1000,max(1,$batch)); $i++) {
                $claim=$this->claim($channel);
                if (!$claim) break;
                [$job,$provider]=$claim;
                $outcome=['status'=>'unknown','message'=>'Delivery status could not be confirmed.'];
                try {
                    $payload=json_decode((string)$job->payload,true,512,JSON_THROW_ON_ERROR);
                    $outcome=$channel==='email'
                        ? $this->sendEmail($job,$provider,$payload,$smtp)
                        : $this->sendPush($job,$provider,$payload);
                } catch (\Throwable $e) {
                    // A timeout may happen after the remote accepted an email/push.
                    $outcome=['status'=>'unknown','message'=>'Unconfirmed transport outcome: '.get_class($e)];
                    report($e);
                }
                $db->table('jb_channel_dispatches')->where('id',$job->id)
                    ->where('status','sending')->update([
                        'status'=>$outcome['status'], 'last_error'=>$outcome['status']==='sent'?null:mb_substr($outcome['message']??'',0,1900),
                        'provider_response'=>mb_substr($outcome['message']??'',0,1900),
                        'sent_at'=>$outcome['status']==='sent'?now('UTC'):null,
                        'locked_at'=>null, 'updated_at'=>now('UTC'),
                    ]);
                $stats['processed']++;
                $stats[$outcome['status']]++;
            }
        } finally {
            foreach ($smtp as $transport) { try {$transport->stop();} catch (\Throwable) {} }
        }
        return $stats;
    }

    private function claim(string $channel): ?array
    {
        $db=$this->db(); $table=$channel==='email'?'jb_email_providers':'jb_push_providers';
        return $db->transaction(function() use($db,$table,$channel) {
            // Lock the account row before checking its quota; prevents parallel workers
            // from exceeding account rate if they use the same SQL Server database.
            $providers=$db->table($table)->where('is_enabled',1)->whereNull('deleted_at')
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($providers as $provider) {
                if ($channel==='email') {
                    $limit=max(1,min(10000,(int)$provider->max_per_minute));
                    $inWindow=TypedWhere::eq($db->table('jb_channel_dispatches')->where('provider_id',$provider->id),
                        'channel','email',12)->where('attempts','>',0)
                        ->where('updated_at','>=',now('UTC')->subMinute())->count();
                    if ($inWindow >= $limit) continue;
                }
                $job=TypedWhere::oneOf(TypedWhere::eq(
                    $db->table('jb_channel_dispatches as c')->join('jb_flows as f','f.id','=','c.flow_id')
                    ->whereNull('f.deleted_at')
                    ->where('c.provider_id',$provider->id), 'c.channel',$channel,12),
                    'c.status',['pending','retry_authorized'],20)
                    ->whereRaw('[f].[status] = CAST(? AS varchar(20))',['active'])
                    ->where('c.available_at','<=',now('UTC'))
                    ->orderBy('c.id')->lockForUpdate()->select('c.*')->first();
                if (!$job) continue;
                $claimed=$db->table('jb_channel_dispatches')->where('id',$job->id)
                    ->where('status',$job->status)->update([
                        'status'=>'sending','attempts'=>(int)$job->attempts+1,
                        'locked_at'=>now('UTC'),'updated_at'=>now('UTC'),
                    ]);
                if ($claimed!==1) continue;
                return [$job,$provider];
            }
            return null;
        },3);
    }

    private function sendEmail(object $job, object $provider, array $payload, array &$transports): array
    {
        $key=(int)$provider->id;
        if (!isset($transports[$key])) {
            // STARTTLS (ports 587/2525), or implicit TLS (465). Never plaintext SMTP.
            $implicit=$provider->smtp_security==='smtps';
            $transport=new EsmtpTransport((string)$provider->smtp_host,(int)$provider->smtp_port,$implicit);
            $transport->setUsername((string)$provider->smtp_username);
            $transport->setPassword(Crypt::decryptString($provider->smtp_secret));
            if (!$implicit) $transport->setRequireTls(true);
            $transports[$key]=$transport;
        }
        $mail=(new Email())
            ->from(new Address((string)$provider->from_email,(string)$provider->from_name))
            ->to((string)$job->recipient)
            ->subject((string)$payload['subject']);
        if (($payload['format']??'text')==='html') $mail->html((string)$payload['body']);
        else $mail->text((string)$payload['body']);
        $transports[$key]->send($mail);
        // SMTP accepted, NOT proof of inbox delivery.
        return ['status'=>'sent','message'=>'SMTP accepted message'];
    }

    private function sendPush(object $job, object $provider, array $payload): array
    {
        $db=$this->db();
        $rows=TypedWhere::eq($db->table('jb_push_subscriptions')->where('provider_id',$provider->id)
            ->where('is_enabled',1),'user_id',$job->user_id,100)->get();
        if ($rows->isEmpty()) return ['status'=>'failed','message'=>'No active Najva subscriptions'];
        $parts=[
            ['name'=>'website_id','contents'=>(string)$provider->website_id],
            ['name'=>'ttl','contents'=>(string)($payload['ttl']??24)],
            ['name'=>'message.title','contents'=>(string)$payload['title']],
            ['name'=>'message.body','contents'=>(string)$payload['body']],
        ];
        if (!empty($payload['url'])) $parts[]=['name'=>'message.notification_click.click_url','contents'=>$payload['url']];
        if (!empty($payload['button_title'])) $parts[]=['name'=>'message.button_1.title','contents'=>$payload['button_title']];
        if (!empty($payload['button_url'])) $parts[]=['name'=>'message.button_1.click_url','contents'=>$payload['button_url']];
        $tokens=[];
        foreach ($rows as $row) {
            $token=Crypt::decryptString($row->token_secret);
            $tokens[]=$token;
            $parts[]=['name'=>'tokens[]','contents'=>$token];
        }
        $headers=['Accept'=>'application/json'];
        $key=Crypt::decryptString($provider->api_secret);
        $headers+=match($provider->auth_mode) {
            'x-api-key'=>['X-API-Key'=>$key],
            'api-key'=>['Api-Key'=>$key],
            default=>['Authorization'=>'Bearer '.$key],
        };
        // Fixed destination, no redirects: not a general-purpose URL fetcher.
        $response=(new Client(['timeout'=>20,'connect_timeout'=>5,'allow_redirects'=>false,'http_errors'=>false]))
            ->post('https://push.najva.com/v1/send/token/', ['headers'=>$headers,'multipart'=>$parts]);
        $http=$response->getStatusCode();
        if ($http < 200 || $http >= 300) return ['status'=>'failed','message'=>'Najva HTTP '.$http];
        $json=json_decode((string)$response->getBody(),true);
        if (!is_array($json) || !is_array($json['Entries']['tokens']??null))
            return ['status'=>'unknown','message'=>'Najva returned no per-token delivery results'];
        $results=$json['Entries']['tokens'];
        $byToken=[];
        foreach ($results as $item) if (is_array($item) && isset($item['token'],$item['status'])) $byToken[(string)$item['token']]=$item['status'];
        $statuses=[];
        foreach ($tokens as $token) $statuses[]=$byToken[$token]??'MissingResult';
        if (count(array_filter($statuses,fn($s)=>in_array(strtolower((string)$s),['scheduled','sent','success','accepted','delivered'],true)))===count($tokens))
            return ['status'=>'sent','message'=>'Najva accepted '.count($tokens).' subscribed tokens'];
        if (count($tokens)===1 && in_array(strtolower((string)$statuses[0]),['invalidtoken','failed','rejected'],true))
            return ['status'=>'failed','message'=>'Najva rejected recipient token'];
        // Mixed result: never auto retry, because some tokens might already have received it.
        return ['status'=>'unknown','message'=>'Mixed/unconfirmed Najva token outcomes; reconcile before retry'];
    }
}
