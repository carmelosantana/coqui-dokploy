<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitDokploy\Runtime\DokployClient;
use CarmeloSantana\CoquiToolkitDokploy\Runtime\DokployResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('get request sends correct x-api-key header', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url, 'options' => $options];
        return new MockResponse(json_encode(['id' => 1, 'name' => 'test-project']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new DokployClient(baseUrl: 'https://dokploy.example.com', apiToken: 'test-key-123', httpClient: $mockClient);
    $result = $client->get('project.all');

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('GET');
    expect($captured['url'])->toContain('dokploy.example.com/api/project.all');

    $apiKeyHeader = '';
    foreach ($captured['options']['headers'] ?? [] as $header) {
        if (str_starts_with($header, 'x-api-key:')) {
            $apiKeyHeader = $header;
        }
    }
    expect($apiKeyHeader)->toContain('test-key-123');
});

test('post request sends JSON body', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url, 'options' => $options];
        return new MockResponse(json_encode(['id' => 'proj-42', 'name' => 'my-project']), [
            'http_code' => 201,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new DokployClient(baseUrl: 'https://dokploy.example.com', apiToken: 'test-key', httpClient: $mockClient);
    $result = $client->post('project.create', ['name' => 'my-project']);

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('POST');
    $body = $captured['options']['body'] ?? '';
    expect($body)->toContain('my-project');
});

test('delete request sends body', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url, 'options' => $options];
        return new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new DokployClient(baseUrl: 'https://dokploy.example.com', apiToken: 'test-key', httpClient: $mockClient);
    $result = $client->delete('project.remove', ['projectId' => 'proj-42']);

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('DELETE');
    $body = $captured['options']['body'] ?? '';
    expect($body)->toContain('proj-42');
});

test('missing token returns error result', function () {
    $mockClient = new MockHttpClient([]);
    $client = new DokployClient(baseUrl: 'https://dokploy.example.com', apiToken: '', httpClient: $mockClient);

    $result = $client->get('project.all');

    expect($result->success)->toBeFalse();
    expect($result->errorMessage())->toContain('DOKPLOY_API_TOKEN');
});

test('missing base url returns error result', function () {
    $mockClient = new MockHttpClient([]);
    $client = new DokployClient(baseUrl: '', apiToken: 'test-key', httpClient: $mockClient);

    $result = $client->get('project.all');

    expect($result->success)->toBeFalse();
    expect($result->errorMessage())->toContain('DOKPLOY_BASE_URL');
});

test('http error response returns structured error', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode(['message' => 'Not found']), [
            'http_code' => 404,
            'response_headers' => ['content-type' => 'application/json'],
        ]),
    ]);

    $client = new DokployClient(baseUrl: 'https://dokploy.example.com', apiToken: 'test-key', httpClient: $mockClient);
    $result = $client->get('project.one', ['projectId' => 'nonexistent']);

    expect($result->success)->toBeFalse();
    expect($result->statusCode)->toBe(404);
});

test('fromEnv creates client without errors', function () {
    $client = DokployClient::fromEnv();

    expect($client)->toBeInstanceOf(DokployClient::class);
});

test('patch request sends JSON body', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'options' => $options];
        return new MockResponse(json_encode(['success' => true]), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new DokployClient(baseUrl: 'https://dokploy.example.com', apiToken: 'test-key', httpClient: $mockClient);
    $result = $client->patch('project.update', ['projectId' => 'p1', 'name' => 'updated']);

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('PATCH');
    $body = $captured['options']['body'] ?? '';
    expect($body)->toContain('updated');
});

test('put request sends JSON body', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'options' => $options];
        return new MockResponse(json_encode(['success' => true]), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new DokployClient(baseUrl: 'https://dokploy.example.com', apiToken: 'test-key', httpClient: $mockClient);
    $result = $client->put('some.endpoint', ['key' => 'value']);

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('PUT');
    $body = $captured['options']['body'] ?? '';
    expect($body)->toContain('value');
});
