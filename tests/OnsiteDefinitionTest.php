<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use Orchestra\Testbench\TestCase;

final class OnsiteDefinitionTest extends TestCase
{
    public function test_onsite_ports_and_draft_configuration(): void
    {
        self::assertSame(['success', 'failure'], Definition::PORTS['onsite']);
        $graph = $this->graph();
        self::assertSame('onsite', Definition::validate($graph, false)['nodes'][1]['type']);
    }

    public function test_onsite_can_be_activated_when_configured_and_connected(): void
    {
        config(['journey_builder.onsite.enabled'=>true]);
        self::assertSame('onsite', Definition::validate($this->graph(),true)['nodes'][1]['type']);
    }

    private function graph(): array
    {
        return [
            'nodes'=>[
                ['id'=>'start','type'=>'trigger','x'=>10,'y'=>10,'config'=>['section'=>'attributes','sign'=>'','key'=>'phone','operator'=>'exists','value'=>'']],
                ['id'=>'onsite','type'=>'onsite','x'=>10,'y'=>140,'config'=>['onsite_name'=>'Welcome Banner','failure_policy'=>'hold']],
                ['id'=>'finish','type'=>'exit','x'=>10,'y'=>310,'config'=>[]],
            ],
            'edges'=>[
                ['source'=>'start','port'=>'next','target'=>'onsite'],
                ['source'=>'onsite','port'=>'success','target'=>'finish'],
                ['source'=>'onsite','port'=>'failure','target'=>'finish'],
            ],
        ];
    }
}
