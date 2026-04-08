<?php

declare(strict_types=1);

use CarmeloSantana\PHPAgents\Enum\ToolResultStatus;
use CarmeloSantana\CoquiToolkitDokploy\DokployToolkit;
use CarmeloSantana\CoquiToolkitDokploy\Runtime\DokployClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// -- Helpers ------------------------------------------------------------------

function mockDokployToolkit(MockHttpClient $mockHttp): DokployToolkit
{
    $client = new DokployClient(baseUrl: 'https://dokploy.test', apiToken: 'test-key', httpClient: $mockHttp);
    return new DokployToolkit($client);
}

function findTool(DokployToolkit $toolkit, string $name): \CarmeloSantana\PHPAgents\Contract\ToolInterface
{
    foreach ($toolkit->tools() as $tool) {
        if ($tool->name() === $name) {
            return $tool;
        }
    }
    throw new \RuntimeException("Tool '{$name}' not found");
}

// -- Project Tool -------------------------------------------------------------

test('dokploy_project list returns project data', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode([
            ['projectId' => 'p1', 'name' => 'web-app'],
            ['projectId' => 'p2', 'name' => 'api-server'],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
    ]);

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_project');
    $result = $tool->execute(['action' => 'list']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('web-app');
    expect($result->content)->toContain('api-server');
});

test('dokploy_project get requires projectId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_project');
    $result = $tool->execute(['action' => 'get']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('projectId');
});

test('dokploy_project create requires name', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_project');
    $result = $tool->execute(['action' => 'create']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('name');
});

test('dokploy_project create sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url, 'options' => $options];
        return new MockResponse(json_encode(['projectId' => 'p-new', 'name' => 'my-project']), [
            'http_code' => 201,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_project');
    $result = $tool->execute(['action' => 'create', 'name' => 'my-project']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['method'])->toBe('POST');
    expect($captured['url'])->toContain('project.create');
});

test('dokploy_project unknown action returns error', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_project');
    $result = $tool->execute(['action' => 'nonexistent']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('Unknown action');
});

// -- Application Tool ---------------------------------------------------------

test('dokploy_app get requires applicationId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_app');
    $result = $tool->execute(['action' => 'get']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('applicationId');
});

test('dokploy_app create requires name and environmentId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_app');

    $result = $tool->execute(['action' => 'create', 'name' => 'my-app']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('environmentId');

    $result = $tool->execute(['action' => 'create', 'environmentId' => 'env-1']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('name');
});

test('dokploy_app deploy sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['status' => 'deploying']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_app');
    $result = $tool->execute(['action' => 'deploy', 'applicationId' => 'app-1']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['method'])->toBe('POST');
    expect($captured['url'])->toContain('application.deploy');
});

test('dokploy_app build_type requires both applicationId and buildType', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_app');

    $result = $tool->execute(['action' => 'build_type', 'applicationId' => 'app-1']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('buildType');
});

test('dokploy_app monitoring requires appName', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_app');

    $result = $tool->execute(['action' => 'monitoring']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('appName');
});

// -- Compose Tool -------------------------------------------------------------

test('dokploy_compose get requires composeId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_compose');
    $result = $tool->execute(['action' => 'get']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('composeId');
});

test('dokploy_compose create requires name and environmentId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_compose');

    $result = $tool->execute(['action' => 'create', 'name' => 'my-stack']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('environmentId');
});

test('dokploy_compose templates lists available templates', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode([
            ['id' => 't1', 'name' => 'WordPress'],
            ['id' => 't2', 'name' => 'Ghost'],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
    ]);

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_compose');
    $result = $tool->execute(['action' => 'templates']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('WordPress');
    expect($result->content)->toContain('Ghost');
});

test('dokploy_compose deploy_template requires templateId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_compose');
    $result = $tool->execute(['action' => 'deploy_template']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('templateId');
});

test('dokploy_compose import requires composeId and composeFile', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_compose');

    $result = $tool->execute(['action' => 'import', 'composeId' => 'c-1']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('composeFile');
});

test('dokploy_compose deploy sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['status' => 'deploying']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_compose');
    $result = $tool->execute(['action' => 'deploy', 'composeId' => 'c-1']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['method'])->toBe('POST');
    expect($captured['url'])->toContain('compose.deploy');
});

// -- Backup Tool --------------------------------------------------------------

test('dokploy_backup get requires backupId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_backup');
    $result = $tool->execute(['action' => 'get']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('backupId');
});

test('dokploy_backup create requires destinationId and databaseType', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_backup');

    $result = $tool->execute(['action' => 'create', 'destinationId' => 'd-1']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('databaseType');

    $result = $tool->execute(['action' => 'create', 'databaseType' => 'postgres']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('destinationId');
});

test('dokploy_backup run requires backupId and databaseType', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_backup');

    $result = $tool->execute(['action' => 'run', 'backupId' => 'b-1']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('databaseType');
});

test('dokploy_backup run uses type-specific endpoint', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['status' => 'running']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_backup');
    $result = $tool->execute(['action' => 'run', 'backupId' => 'b-1', 'databaseType' => 'postgres']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['url'])->toContain('backup.manualBackupPostgres');
});

test('dokploy_backup list_files returns file data', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode([
            ['filename' => 'backup-2024-01-01.sql.gz', 'size' => 1024],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
    ]);

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_backup');
    $result = $tool->execute(['action' => 'list_files', 'backupId' => 'b-1']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('backup-2024-01-01');
});

// -- Deployment Tool ----------------------------------------------------------

test('dokploy_deployment list requires applicationId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_deployment');
    $result = $tool->execute(['action' => 'list']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('applicationId');
});

test('dokploy_deployment kill requires deploymentId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_deployment');
    $result = $tool->execute(['action' => 'kill']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('deploymentId');
});

test('dokploy_deployment centralized returns deployment feed', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode([
            ['id' => 'd1', 'status' => 'done', 'applicationId' => 'app-1'],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
    ]);

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_deployment');
    $result = $tool->execute(['action' => 'centralized']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('app-1');
});

test('dokploy_deployment kill sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['status' => 'killed']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_deployment');
    $result = $tool->execute(['action' => 'kill', 'deploymentId' => 'd-1']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['method'])->toBe('POST');
    expect($captured['url'])->toContain('deployment.killProcess');
});

// -- Docker Tool --------------------------------------------------------------

test('dokploy_docker containers requires serverId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_docker');
    $result = $tool->execute(['action' => 'containers']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('serverId');
});

test('dokploy_docker restart requires containerId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_docker');
    $result = $tool->execute(['action' => 'restart']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('containerId');
});

test('dokploy_docker containers_by_app requires appName', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_docker');
    $result = $tool->execute(['action' => 'containers_by_app']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('appName');
});

test('dokploy_docker restart sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['status' => 'restarted']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_docker');
    $result = $tool->execute(['action' => 'restart', 'containerId' => 'abc123']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['method'])->toBe('POST');
    expect($captured['url'])->toContain('docker.restartContainer');
});

// -- Domain Tool --------------------------------------------------------------

test('dokploy_domain get requires domainId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_domain');
    $result = $tool->execute(['action' => 'get']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('domainId');
});

test('dokploy_domain create requires host', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_domain');
    $result = $tool->execute(['action' => 'create']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('host');
});

test('dokploy_domain by_app requires applicationId', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_domain');
    $result = $tool->execute(['action' => 'by_app']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('applicationId');
});

test('dokploy_domain validate requires host', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_domain');
    $result = $tool->execute(['action' => 'validate']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('host');
});

test('dokploy_domain create sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['domainId' => 'dom-1', 'host' => 'app.example.com']), [
            'http_code' => 201,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_domain');
    $result = $tool->execute(['action' => 'create', 'host' => 'app.example.com', 'applicationId' => 'app-1']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['method'])->toBe('POST');
    expect($captured['url'])->toContain('domain.create');
});

test('dokploy_domain generate sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['domain' => 'app-1.dokploy.test']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockDokployToolkit($mockClient);
    $tool = findTool($toolkit, 'dokploy_domain');
    $result = $tool->execute(['action' => 'generate', 'applicationId' => 'app-1']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['method'])->toBe('POST');
    expect($captured['url'])->toContain('domain.generateDomain');
});
