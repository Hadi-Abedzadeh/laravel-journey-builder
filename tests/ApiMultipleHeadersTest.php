<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use Orchestra\Testbench\TestCase;
use Illuminate\Validation\ValidationException;

final class ApiMultipleHeadersTest extends TestCase
{
    private function draftWithApi(array $config): array
    {
        $graph = Definition::blank();
        $graph['nodes'][] = [
            'id' => 'api_test', 'type' => 'api', 'x' => 390, 'y' => 180, 'config' => $config,
        ];
        return $graph;
    }

    public function test_api_node_accepts_several_plain_headers_and_basic_auth_mode(): void
    {
        $graph = $this->draftWithApi([
            'url' => 'https://api.example.org/notify',
            'method' => 'POST', 'token_source' => 'secret', 'token_header' => 'basic',
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-Campaign' => 'signup'],
            'body' => '{"Mobile":"{{phone}}","Subject":"Campaign","Body":"Hello"}',
        ]);
        $this->assertIsArray(Definition::validate($graph, false));
    }

    public function test_authorization_must_not_be_saved_in_graph_headers(): void
    {
        $graph = $this->draftWithApi([
            'url'=>'https://api.example.org/notify', 'method'=>'POST',
            'headers'=>['Authorization'=>'Basic sensitive'], 'body'=>'{}',
        ]);
        $this->expectException(ValidationException::class);
        Definition::validate($graph, false);
    }

    public function test_webengage_placeholders_are_rejected(): void
    {
        $graph = $this->draftWithApi([
            'url'=>'https://api.example.org/notify', 'method'=>'POST',
            'body'=>'{"Mobile":"{{user[\\"system\\"][\\"phone\\"]}}"}',
        ]);
        $this->expectException(ValidationException::class);
        Definition::validate($graph, false);
    }
}
