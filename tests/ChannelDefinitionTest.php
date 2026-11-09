<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use PHPUnit\Framework\TestCase;

final class ChannelDefinitionTest extends TestCase
{
    private function base(string $type, array $config): array
    {
        return [
            'nodes'=>[
                ['id'=>'start','type'=>'trigger','x'=>10,'y'=>10,'config'=>['section'=>'attributes','sign'=>'','key'=>'phone','operator'=>'exists','value'=>'']],
                ['id'=>'action','type'=>$type,'x'=>10,'y'=>110,'config'=>$config],
                ['id'=>'finish','type'=>'exit','x'=>10,'y'=>210,'config'=>[]],
            ],
            'edges'=>[
                ['source'=>'start','port'=>'next','target'=>'action'],
                ['source'=>'action','port'=>'success','target'=>'finish'],
                ['source'=>'action','port'=>'failure','target'=>'finish'],
            ],
        ];
    }

    public function test_email_node_can_be_activated_when_fully_connected(): void
    {
        $graph=$this->base('email',[
            'provider_id'=>0,'recipient_attribute'=>'email','recipient_sign'=>'',
            'subject'=>'سلام {{firstName}}','body'=>'متن پیام','format'=>'text',
        ]);
        $result=Definition::validate($graph,true);
        self::assertSame('email',$result['nodes'][1]['type']);
    }

    public function test_najva_webpush_node_can_be_activated_when_fully_connected(): void
    {
        $graph=$this->base('webpush',[
            'provider_id'=>0,'title'=>'سلام','body'=>'پیام شما','url'=>'https://example.com','ttl'=>24,
        ]);
        $result=Definition::validate($graph,true);
        self::assertSame('webpush',$result['nodes'][1]['type']);
    }
}
