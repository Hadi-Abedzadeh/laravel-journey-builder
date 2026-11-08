<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use OpenJourney\LaravelJourneyBuilder\JourneyBuilderServiceProvider;
use OpenJourney\LaravelJourneyBuilder\Services\Engine;
use OpenJourney\LaravelJourneyBuilder\Services\KavenegarClient;
use OpenJourney\LaravelJourneyBuilder\Services\SmsDispatcher;
use Orchestra\Testbench\TestCase;

final class ManagedSmsTest extends TestCase
{
    protected function getPackageProviders($app): array { return [JourneyBuilderServiceProvider::class]; }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]);
        $app['config']->set('journey_builder.connection','sqlite');
        $app['config']->set('journey_builder.auto_schedule',false);
        $app['config']->set('journey_builder.sms.managed_enabled',true);
        $app['config']->set('journey_builder.retention.purge_on_exit',false);
    }

    private function migrate(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_100000_add_journey_builder_retention_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_180000_add_journey_sms_providers.php')->up();
        Schema::create('member_data', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('userId',100); $t->string('section',20);
            $t->string('key',50); $t->string('value',1000)->nullable();
            $t->string('sign',30)->nullable(); $t->dateTime('updated_at',3);
        });
    }

    public function test_new_sms_is_queued_then_batch_dispatched_and_resumed_without_restart(): void
    {
        $this->migrate(); $now=now('UTC')->subMinutes(2);
        $providerId=DB::table('jb_sms_providers')->insertGetId([
            'code'=>'sp_test', 'name'=>'کاوه‌نگار','driver'=>'kavenegar','sender'=>'10004346',
            'receptor_attribute'=>'phone','source_sign'=>null,
            'encrypted_api_key'=>Crypt::encryptString('TESTKEY'),
            'is_enabled'=>1,'is_default'=>1,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $nodes=[
            ['id'=>'start','type'=>'trigger','x'=>30,'y'=>30,'config'=>['section'=>'attributes','key'=>'foo','sign'=>'','value'=>'','operator'=>'exists']],
            ['id'=>'sms1','type'=>'sms','x'=>50,'y'=>100,'config'=>['delivery_mode'=>'managed','provider_id'=>$providerId,'message'=>'سلام {{firstName}}']],
            ['id'=>'exit1','type'=>'exit','x'=>100,'y'=>220,'config'=>[]],
        ];
        $edges=[['id'=>'1','source'=>'start','target'=>'sms1','port'=>'next'],['id'=>'2','source'=>'sms1','target'=>'exit1','port'=>'success'],['id'=>'3','source'=>'sms1','target'=>'exit1','port'=>'failure']];
        $flowId=DB::table('jb_flows')->insertGetId([
            'code'=>'jn_test','title'=>'Test SMS', 'status'=>'active', 'revision'=>1,
            'graph_json'=>json_encode(['nodes'=>$nodes,'edges'=>$edges]),
            'created_at'=>$now,'updated_at'=>$now,
        ]);
        foreach ([1,2,3] as $n) {
            $userId='member-'.$n;
            DB::table('member_data')->insert([
                ['userId'=>$userId,'section'=>'attributes','key'=>'phone','value'=>'0912000000'.$n,'updated_at'=>$now],
                ['userId'=>$userId,'section'=>'attributes','key'=>'firstName','value'=>'نام '.$n,'updated_at'=>$now],
            ]);
            DB::table('jb_runs')->insert([
                'flow_id'=>$flowId,'user_id'=>$userId,'node_id'=>'sms1','state'=>'ready','revision'=>1,
                'next_run_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        // The graph is queued, not sent inline; this also tests distinct message personalization.
        $this->assertSame(3,(new Engine())->tick(10)['processed']);
        $this->assertSame(3,DB::table('jb_sms_logs')->where('status','pending')->count());
        $this->assertSame(3,DB::table('jb_runs')->where('state','waiting')->count());
        Http::fake(['https://api.kavenegar.com/*' => function ($request) {
            $receptors=json_decode($request['receptor'],true);
            $messages=json_decode($request['message'],true);
            $entries=[];
            foreach ($receptors as $i=>$number) $entries[]=[
                'receptor'=>$number,'message'=>$messages[$i], 'sender'=>'10004346',
                'messageid'=>100+$i, 'status'=>1,
            ];
            // Out of order response must still match the proper recipient and text.
            return Http::response(['return'=>['status'=>200,'message'=>'OK'],'entries'=>array_reverse($entries)],200);
        }]);
        $result=(new SmsDispatcher())->dispatch(200);
        $this->assertSame(3,$result['claimed']);
        $this->assertSame(3,$result['sent']);
        Http::assertSentCount(1);
        $this->assertSame(3,DB::table('jb_sms_logs')->where('status','sent')->count());
        DB::table('jb_runs')->update(['next_run_at'=>now('UTC')->subMinute()]);
        $this->assertSame(3,(new Engine())->tick(10)['processed']);
        $this->assertSame(3,DB::table('jb_runs')->where('state','exited')->count());
        $this->assertSame(3,DB::table('jb_sms_logs')->where('attempts',1)->count());
    }

    public function test_dispatcher_splits_large_send_into_requests_of_no_more_than_200(): void
    {
        $this->migrate(); $now=now('UTC')->subMinute();
        $providerId=DB::table('jb_sms_providers')->insertGetId([
            'code'=>'sp_batch', 'name'=>'Batch panel','driver'=>'kavenegar','sender'=>'10004346',
            'receptor_attribute'=>'phone','source_sign'=>null,
            'encrypted_api_key'=>Crypt::encryptString('TESTKEY'),
            'is_enabled'=>1,'is_default'=>1,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $flowId=DB::table('jb_flows')->insertGetId([
            'code'=>'jn_chunk','title'=>'Chunk test','status'=>'active','revision'=>1,
            'graph_json'=>'{"nodes":[],"edges":[]}',
            'created_at'=>$now,'updated_at'=>$now,
        ]);
        $rows=[];
        for ($i=1;$i<=205;$i++) $rows[]=[
            'flow_id'=>$flowId,'run_id'=>$i,'node_id'=>'sms1','user_id'=>'u'.$i,
            'template'=>'freeform','status'=>'pending','attempts'=>0,
            'provider_id'=>$providerId,'sender'=>'10004346',
            'receptor'=>'0912'.str_pad((string)$i,7,'0',STR_PAD_LEFT),
            'message_text'=>'message-'.$i,'created_at'=>$now,'updated_at'=>$now,
        ];
        foreach (array_chunk($rows, 100) as $chunk) DB::table('jb_sms_logs')->insert($chunk);
        $counts=[];
        Http::fake(['https://api.kavenegar.com/*'=>function ($request) use (&$counts) {
            $recipients=json_decode($request['receptor'],true);
            $texts=json_decode($request['message'],true);
            $counts[]=count($recipients);
            $entries=[];
            foreach ($recipients as $i=>$number) $entries[]=[
                'receptor'=>$number,'message'=>$texts[$i],'sender'=>'10004346',
                'messageid'=>mt_rand(10000,99999),
            ];
            return Http::response(['return'=>['status'=>200,'message'=>'OK'],'entries'=>$entries],200);
        }]);
        $result=(new SmsDispatcher())->dispatch(1000);
        $this->assertSame(205,$result['claimed']);
        $this->assertSame(205,$result['sent']);
        Http::assertSentCount(2);
        $this->assertSame([200,5],$counts);
    }

    public function test_local_message_ids_are_stable_provider_namespaced_and_numeric(): void
    {
        $client=new KavenegarClient();
        $a=$client->localMessageId((object)['code'=>'a'],123);
        $b=$client->localMessageId((object)['code'=>'b'],123);
        $this->assertMatchesRegularExpression('/^[0-9]+$/',$a);
        $this->assertNotSame($a,$b);
        $this->assertSame($a,$client->localMessageId((object)['code'=>'a'],123));
    }
}
