<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitDokploy\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\CoquiToolkitDokploy\Runtime\DokployClient;

/**
 * Inspect Docker state on Dokploy servers — list containers,
 * restart specific containers, and view Docker daemon configuration.
 */
final readonly class DockerTool
{
    public function __construct(
        private DokployClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'dokploy_docker',
            description: 'Inspect Docker state on Dokploy servers — list containers, restart containers, view Docker daemon config, or find containers by app name.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['containers', 'restart', 'config', 'containers_by_app'],
                    required: true,
                ),
                new StringParameter(
                    'serverId',
                    'Server ID (required for containers and config actions).',
                    required: false,
                ),
                new StringParameter(
                    'containerId',
                    'Docker container ID (required for restart action).',
                    required: false,
                ),
                new StringParameter(
                    'appName',
                    'Application name to match containers against (required for containers_by_app action).',
                    required: false,
                ),
            ],
            callback: fn(array $args) => $this->execute($args),
        );
    }

    /** @param array<string, mixed> $args */
    private function execute(array $args): ToolResult
    {
        $action = trim((string) ($args['action'] ?? ''));

        return match ($action) {
            'containers' => $this->listContainers($args),
            'restart' => $this->restartContainer($args),
            'config' => $this->getConfig($args),
            'containers_by_app' => $this->containersByApp($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /** @param array<string, mixed> $args */
    private function listContainers(array $args): ToolResult
    {
        $serverId = $this->requireString($args, 'serverId');
        if ($serverId === null) {
            return ToolResult::error('serverId is required for the "containers" action.');
        }

        return $this->client->get('docker.getContainers', ['serverId' => $serverId])
            ->toToolResultWith('Docker containers:');
    }

    /** @param array<string, mixed> $args */
    private function restartContainer(array $args): ToolResult
    {
        $containerId = $this->requireString($args, 'containerId');
        if ($containerId === null) {
            return ToolResult::error('containerId is required for the "restart" action.');
        }

        return $this->client->post('docker.restartContainer', ['containerId' => $containerId])
            ->toToolResultWith('Container restarted.');
    }

    /** @param array<string, mixed> $args */
    private function getConfig(array $args): ToolResult
    {
        $serverId = $this->requireString($args, 'serverId');
        if ($serverId === null) {
            return ToolResult::error('serverId is required for the "config" action.');
        }

        return $this->client->get('docker.getConfig', ['serverId' => $serverId])
            ->toToolResultWith('Docker daemon configuration:');
    }

    /** @param array<string, mixed> $args */
    private function containersByApp(array $args): ToolResult
    {
        $appName = $this->requireString($args, 'appName');
        if ($appName === null) {
            return ToolResult::error('appName is required for the "containers_by_app" action.');
        }

        return $this->client->get('docker.getContainersByAppNameMatch', ['appName' => $appName])
            ->toToolResultWith("Containers matching '{$appName}':");
    }

    /** @param array<string, mixed> $args */
    private function requireString(array $args, string $key): ?string
    {
        $value = trim((string) ($args[$key] ?? ''));
        return $value !== '' ? $value : null;
    }
}
