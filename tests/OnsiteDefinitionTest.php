<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use OpenJourney\LaravelJourneyBuilder\Services\OnsiteMongoService;
use Orchestra\Testbench\TestCase;

final class OnsiteDefinitionTest extends TestCase
{
    private const ID = '6a3287a52b0310e5280cf1d4';

    public function test_both_onsite_node_types_and_ports(): void
    {
        self::assertSame(['success', 'failure'], Definition::PORTS['onsite']);
        self::assertSame(['viewed', 'timeout'], Definition::PORTS['wait_onsite']);
        self::assertTrue(OnsiteMongoService::isValidOnsiteId(self::ID));
        self::assertFalse(OnsiteMongoService::isValidOnsiteId('Welcome Banner'));
    }

    public function test_draft_accepts_onsite_and_waiting_nodes(): void
    {
        self::assertCount(4, Definition::validate($this->graph(), false)['nodes']);
    }

    public function test_publish_accepts_mongo_configured(): void
    {
        config(['journey_builder.onsite.mongo.uri' => 'mongodb://localhost:27017']);
        self::assertCount(4, Definition::validate($this->graph(), true)['nodes']);
    }

    public function test_invalid_or_missing_ids_are_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $graph = $this->graph();
        $graph['nodes'][1]['config']['onsite_id'] = 'not-object-id';
        Definition::validate($graph, false);
    }

    public function test_old_name_is_supported_for_legacy_flows(): void
    {
        config(['journey_builder.onsite.mongo.uri' => 'mongodb://localhost:27017']);
        $graph = $this->graph();
        unset($graph['nodes'][1]['config']['onsite_id']);
        $graph['nodes'][1]['config']['onsite_name'] = 'Welcome Banner';
        self::assertCount(4, Definition::validate($graph, true)['nodes']);
    }

    private function graph(): array
    {
        return [
            'nodes' => [
                ['id' => 'start', 'type' => 'trigger', 'x' => 10, 'y' => 10,
                    'config' => ['section' => 'attributes', 'sign' => '', 'key' => 'phone', 'operator' => 'exists', 'value' => '']],
                ['id' => 'onsite', 'type' => 'onsite', 'x' => 10, 'y' => 140,
                    'config' => ['onsite_id' => self::ID, 'failure_policy' => 'hold']],
                ['id' => 'onsite_wait', 'type' => 'wait_onsite', 'x' => 10, 'y' => 260,
                    'config' => ['onsite_id' => self::ID, 'days' => 3]],
                ['id' => 'finish', 'type' => 'exit', 'x' => 10, 'y' => 410, 'config' => []],
            ],
            'edges' => [
                ['source' => 'start', 'port' => 'next', 'target' => 'onsite'],
                ['source' => 'onsite', 'port' => 'success', 'target' => 'onsite_wait'],
                ['source' => 'onsite', 'port' => 'failure', 'target' => 'finish'],
                ['source' => 'onsite_wait', 'port' => 'viewed', 'target' => 'finish'],
                ['source' => 'onsite_wait', 'port' => 'timeout', 'target' => 'finish'],
            ],
        ];
    }
}
