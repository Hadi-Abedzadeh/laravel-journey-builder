<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use OpenJourney\LaravelJourneyBuilder\JourneyBuilderServiceProvider;
use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use OpenJourney\LaravelJourneyBuilder\Services\EventRecorder;
use OpenJourney\LaravelJourneyBuilder\Services\KavenegarClient;
use OpenJourney\LaravelJourneyBuilder\Services\SmsReceiptPoller;
use Orchestra\Testbench\TestCase;

final class EventHistoryAndDeliveryTest extends TestCase
{
    protected function getPackageProviders($app): array { return [JourneyBuilderServiceProvider::class]; }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default','sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]);
        $app['config']->set('journey_builder.connection','sqlite');
        $app['config']->set('journey_builder.auto_schedule',false);
        $app['config']->set('journey_builder.sms.receipt_poll_enabled',true);
        $app['config']->set('journey_builder.retention.purge_on_exit',false);
        $app['config']->set('journey_builder.event_history.enabled',true);
    }

    private function migrate(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_07_200000_create_journey_builder_package_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_100000_add_journey_builder_retention_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_180000_add_journey_sms_providers.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_18_140000_add_event_history_and_sms_receipts.php')->up();
    }

    public function test_event_only_trigger_accepts_named_event_without_key(): void
    {
        $graph = [
            'nodes'=>[
                ['id'=>'start','type'=>'trigger','x'=>30,'y'=>30,'config'=>[
                    'section'=>'events','name'=>'Reg_Started','sign'=>'membershipRegistration',
                    'key'=>'','value'=>'','operator'=>'exists',
                ]],
                ['id'=>'exit','type'=>'exit','x'=>30,'y'=>150,'config'=>[]],
            ],
            'edges'=>[['id'=>'a','source'=>'start','port'=>'next','target'=>'exit']],
        ];
        self::assertSame($graph,Definition::validate($graph));
    }

    public function test_event_recorder_detects_only_new_logical_events_and_matches_fields(): void
    {
        $this->migrate();
        $recorder = new EventRecorder();
        $first = $recorder->record('u1','Reg_Done',['RegType'=>'RegDone'],'membershipRegistration');
        self::assertFalse($recorder->seenSince('u1', ['event'=>'Reg_Done','sign'=>'membershipRegistration'], now('UTC')->subMinutes(5)->toDateTimeString(), $first));
        $second = $recorder->record('u1','Reg_Done',['RegType'=>'RegDone'],'membershipRegistration');
        self::assertTrue($recorder->seenSince('u1', ['event'=>'Reg_Done','sign'=>'membershipRegistration'], now('UTC')->subMinutes(5)->toDateTimeString(), $first));
        self::assertTrue($recorder->seenSince('u1', ['event'=>'Reg_Done','key'=>'RegType','operator'=>'equals','value'=>'RegDone'], now('UTC')->subMinutes(5)->toDateTimeString(), $first));
        self::assertFalse($recorder->seenSince('u1', ['event'=>'Reg_Done','key'=>'NationalCode','operator'=>'is_empty'], now('UTC')->subMinutes(5)->toDateTimeString(), $first));
        self::assertTrue($second > $first);
    }

    public function test_sms_status_polling_uses_message_id_and_does_not_resend(): void
    {
        $this->migrate();
        $now = now('UTC')->subMinutes(2);
        $providerId = DB::table('jb_sms_providers')->insertGetId([
            'code'=>'smstest','name'=>'SMS','driver'=>'kavenegar','sender'=>'30002626',
            'receptor_attribute'=>'phone','source_sign'=>null,
            'encrypted_api_key'=>Crypt::encryptString('TESTKEY'),
            'is_enabled'=>1,'is_default'=>1,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $flowId = DB::table('jb_flows')->insertGetId([
            'code'=>'flow_receipt','title'=>'Receipt test','status'=>'active','revision'=>1,
            'graph_json'=>'{"nodes":[],"edges":[]}', 'created_at'=>$now,'updated_at'=>$now,
        ]);
        $runId = DB::table('jb_runs')->insertGetId([
            'flow_id'=>$flowId,'user_id'=>'u1','node_id'=>'sms1','state'=>'waiting','revision'=>1,
            'next_run_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
        ]);
        DB::table('jb_sms_logs')->insert([
            'flow_id'=>$flowId,'run_id'=>$runId,'node_id'=>'sms1','user_id'=>'u1','template'=>'freeform',
            'status'=>'sent','attempts'=>1,'provider_id'=>$providerId,'sender'=>'30002626',
            'receptor'=>'09123456789','message_text'=>'تست','provider_message_id'=>8792343,
            'sent_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
        ]);
        Http::fake(['https://api.kavenegar.com/*' => Http::response([
            'return'=>['status'=>200, 'message'=>'OK'],
            'entries'=>[['messageid'=>8792343,'status'=>10,'statustext'=>'رسیده به گیرنده']],
        ],200)]);
        $counts=(new SmsReceiptPoller())->poll(10);
        self::assertSame(1,$counts['checked']);
        self::assertSame(1,$counts['delivered']);
        self::assertSame('delivered',DB::table('jb_sms_logs')->first()->delivery_status);
        Http::assertSentCount(1); // Only a status query, no second send.
        Http::assertSent(fn($request) => str_contains($request->url(), '/sms/status.json'));
    }

    public function test_sms_delivery_code_mapping(): void
    {
        self::assertSame('queued',SmsReceiptPoller::classify(1));
        self::assertSame('submitted',SmsReceiptPoller::classify(4));
        self::assertSame('delivered',SmsReceiptPoller::classify(10));
        self::assertSame('undelivered',SmsReceiptPoller::classify(11));
        self::assertSame('unknown',SmsReceiptPoller::classify(100));
    }
}
