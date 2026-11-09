<?php

namespace OpenJourney\LaravelJourneyBuilder\Tests;

use OpenJourney\LaravelJourneyBuilder\Services\ApiGateway;
use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use Orchestra\Testbench\TestCase;

final class ApiEndpointConfigTest extends TestCase
{
    public function test_public_https_dns_hosts_no_longer_require_a_configured_allowlist(): void
    {
        $this->assertTrue(ApiGateway::isAllowedUrl('https://api.some-public-service.com/v1/notify'));
        $this->assertFalse(ApiGateway::isAllowedUrl('http://api.some-public-service.com/v1/notify'));
        $this->assertFalse(ApiGateway::isAllowedUrl('https://127.0.0.1/admin'));
        $this->assertFalse(ApiGateway::isAllowedUrl('https://localhost/admin'));
        $this->assertFalse(ApiGateway::isAllowedUrl('https://user:pass@api.some-public-service.com/'));
        $this->assertFalse(ApiGateway::isAllowedUrl('https://api.some-public-service.com:8443/'));
    }

    public function test_new_api_nodes_can_have_independent_encrypted_secret_modes(): void
    {
        $flow = Definition::blank();
        $flow['nodes'][] = [
            'id' => 'api_a', 'type' => 'api', 'x' => 450, 'y' => 200,
            'config' => ['url'=>'https://api.one-service.com/v1','method'=>'POST','body'=>'{}','token_source'=>'secret'],
        ];
        $flow['nodes'][] = [
            'id' => 'api_b', 'type' => 'api', 'x' => 700, 'y' => 200,
            'config' => ['url'=>'https://api.two-service.com/v1','method'=>'POST','body'=>'{}','token_source'=>'secret'],
        ];
        // Draft validation accepts independent nodes and does not require any ENV token.
        $this->assertIsArray(Definition::validate($flow, false));
    }
}
