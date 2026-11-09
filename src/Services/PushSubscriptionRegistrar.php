<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Called only by a trusted host-authenticated backend endpoint after browser opt-in. */
final class PushSubscriptionRegistrar
{
    public function save(int $providerId, string $userId, string $token): void
    {
        if ($providerId<1 || strlen($userId)<1 || strlen($userId)>100 || strlen($token)<12 || strlen($token)>2048)
            throw new \InvalidArgumentException('Invalid push subscription');
        $db=DB::connection(config('journey_builder.connection','sqlsrv'));
        if (!$db->table('jb_push_providers')->where('id',$providerId)->where('is_enabled',1)->whereNull('deleted_at')->exists())
            throw new \InvalidArgumentException('Push provider is unavailable');
        $digest=hash('sha256',$token);
        $db->transaction(function() use($db,$providerId,$userId,$token,$digest) {
            $q=$db->table('jb_push_subscriptions')->where('provider_id',$providerId)->where('token_digest',$digest);
            $id=$q->value('id');
            $values=['user_id'=>$userId,'token_secret'=>Crypt::encryptString($token),'is_enabled'=>true,'updated_at'=>now('UTC')];
            if ($id) $db->table('jb_push_subscriptions')->where('id',$id)->update($values);
            else $db->table('jb_push_subscriptions')->insert(['provider_id'=>$providerId,'token_digest'=>$digest,'created_at'=>now('UTC')]+$values);
        });
    }
}
