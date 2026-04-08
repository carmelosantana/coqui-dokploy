<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitDokploy\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\PHPAgents\Tool\Parameter\BoolParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\CoquiToolkitDokploy\Runtime\DokployClient;

/**
 * Manage Dokploy applications — single-container deployments
 * built from Git, Docker images, or Dockerfiles.
 */
final readonly class ApplicationTool
{
    public function __construct(
        private DokployClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'dokploy_app',
            description: 'Manage Dokploy applications — get, create, update, delete, deploy, redeploy, start, stop, search, view monitoring, manage environment variables, or configure build type.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: [
                        'get', 'create', 'update', 'delete',
                        'deploy', 'redeploy', 'start', 'stop',
                        'search', 'monitoring', 'env', 'build_type',
                    ],
                    required: true,
                ),
                new StringParameter(
                    'applicationId',
                    'Application ID (required for most actions except create, search).',
                    required: false,
                ),
                new StringParameter(
                    'name',
                    'Application name (required for create).',
                    required: false,
                ),
                new StringParameter(
                    'environmentId',
                    'Environment ID (required for create).',
                    required: false,
                ),
                new StringParameter(
                    'description',
                    'Application description.',
                    required: false,
                ),
                new StringParameter(
                    'appName',
                    'Docker app name (for monitoring, search).',
                    required: false,
                ),
                new StringParameter(
                    'env',
                    'Environment variables as a string (for env action).',
                    required: false,
                ),
                new StringParameter(
                    'buildArgs',
                    'Build arguments string (for env action).',
                    required: false,
                ),
                new EnumParameter(
                    'buildType',
                    'Build type (for build_type action).',
                    values: ['dockerfile', 'heroku_buildpacks', 'paketo_buildpacks', 'nixpacks', 'static', 'railpack'],
                    required: false,
                ),
                new StringParameter(
                    'dockerfile',
                    'Dockerfile content or path (for build_type action with dockerfile type).',
                    required: false,
                ),
                new StringParameter(
                    'query',
                    'Search query string (for search action).',
                    required: false,
                ),
                new StringParameter(
                    'title',
                    'Deployment title (optional for deploy, redeploy).',
                    required: false,
                ),
                new StringParameter(
                    'serverId',
                    'Target server ID (optional for create).',
                    required: false,
                ),
                new BoolParameter(
                    'createEnvFile',
                    'Whether to create a .env file (for env action, default true).',
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
            'get' => $this->getApp($args),
            'create' => $this->createApp($args),
            'update' => $this->updateApp($args),
            'delete' => $this->deleteApp($args),
            'deploy' => $this->deployApp($args),
            'redeploy' => $this->redeployApp($args),
            'start' => $this->startApp($args),
            'stop' => $this->stopApp($args),
            'search' => $this->searchApps($args),
            'monitoring' => $this->monitoring($args),
            'env' => $this->saveEnv($args),
            'build_type' => $this->saveBuildType($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /** @param array<string, mixed> $args */
    private function getApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "get" action.');
        }

        return $this->client->get('application.one', ['applicationId' => $appId])
            ->toToolResultWith('Application details:');
    }

    /** @param array<string, mixed> $args */
    private function createApp(array $args): ToolResult
    {
        $name = $this->requireString($args, 'name');
        if ($name === null) {
            return ToolResult::error('name is required for the "create" action.');
        }

        $environmentId = $this->requireString($args, 'environmentId');
        if ($environmentId === null) {
            return ToolResult::error('environmentId is required for the "create" action.');
        }

        $body = [
            'name' => $name,
            'environmentId' => $environmentId,
        ];

        $appName = $this->optionalString($args, 'appName');
        if ($appName !== null) {
            $body['appName'] = $appName;
        }

        $description = $this->optionalString($args, 'description');
        if ($description !== null) {
            $body['description'] = $description;
        }

        $serverId = $this->optionalString($args, 'serverId');
        if ($serverId !== null) {
            $body['serverId'] = $serverId;
        }

        return $this->client->post('application.create', $body)
            ->toToolResultWith("Application '{$name}' created.");
    }

    /** @param array<string, mixed> $args */
    private function updateApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "update" action.');
        }

        $body = ['applicationId' => $appId];

        foreach (['name', 'description', 'appName'] as $field) {
            $value = $this->optionalString($args, $field);
            if ($value !== null) {
                $body[$field] = $value;
            }
        }

        return $this->client->post('application.update', $body)
            ->toToolResultWith('Application updated.');
    }

    /** @param array<string, mixed> $args */
    private function deleteApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "delete" action.');
        }

        return $this->client->post('application.delete', ['applicationId' => $appId])
            ->toToolResultWith('Application deleted.');
    }

    /** @param array<string, mixed> $args */
    private function deployApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "deploy" action.');
        }

        $body = ['applicationId' => $appId];

        $title = $this->optionalString($args, 'title');
        if ($title !== null) {
            $body['title'] = $title;
        }

        $description = $this->optionalString($args, 'description');
        if ($description !== null) {
            $body['description'] = $description;
        }

        return $this->client->post('application.deploy', $body)
            ->toToolResultWith('Application deployment initiated.');
    }

    /** @param array<string, mixed> $args */
    private function redeployApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "redeploy" action.');
        }

        $body = ['applicationId' => $appId];

        $title = $this->optionalString($args, 'title');
        if ($title !== null) {
            $body['title'] = $title;
        }

        return $this->client->post('application.redeploy', $body)
            ->toToolResultWith('Application redeployment initiated.');
    }

    /** @param array<string, mixed> $args */
    private function startApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "start" action.');
        }

        return $this->client->post('application.start', ['applicationId' => $appId])
            ->toToolResultWith('Application started.');
    }

    /** @param array<string, mixed> $args */
    private function stopApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "stop" action.');
        }

        return $this->client->post('application.stop', ['applicationId' => $appId])
            ->toToolResultWith('Application stopped.');
    }

    /** @param array<string, mixed> $args */
    private function searchApps(array $args): ToolResult
    {
        $query = [];

        $q = $this->optionalString($args, 'query');
        if ($q !== null) {
            $query['q'] = $q;
        }

        $name = $this->optionalString($args, 'name');
        if ($name !== null) {
            $query['name'] = $name;
        }

        $appName = $this->optionalString($args, 'appName');
        if ($appName !== null) {
            $query['appName'] = $appName;
        }

        return $this->client->get('application.search', $query)
            ->toToolResultWith('Application search results:');
    }

    /** @param array<string, mixed> $args */
    private function monitoring(array $args): ToolResult
    {
        $appName = $this->requireString($args, 'appName');
        if ($appName === null) {
            return ToolResult::error('appName is required for the "monitoring" action.');
        }

        return $this->client->get('application.readAppMonitoring', ['appName' => $appName])
            ->toToolResultWith("Monitoring data for '{$appName}':");
    }

    /** @param array<string, mixed> $args */
    private function saveEnv(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "env" action.');
        }

        $body = [
            'applicationId' => $appId,
            'env' => $args['env'] ?? null,
            'buildArgs' => $args['buildArgs'] ?? null,
            'buildSecrets' => null,
            'createEnvFile' => (bool) ($args['createEnvFile'] ?? true),
        ];

        return $this->client->post('application.saveEnvironment', $body)
            ->toToolResultWith('Environment variables saved.');
    }

    /** @param array<string, mixed> $args */
    private function saveBuildType(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "build_type" action.');
        }

        $buildType = $this->requireString($args, 'buildType');
        if ($buildType === null) {
            return ToolResult::error('buildType is required for the "build_type" action.');
        }

        $body = [
            'applicationId' => $appId,
            'buildType' => $buildType,
            'dockerfile' => $args['dockerfile'] ?? null,
            'dockerContextPath' => null,
            'dockerBuildStage' => null,
            'herokuVersion' => null,
            'railpackVersion' => null,
        ];

        return $this->client->post('application.saveBuildType', $body)
            ->toToolResultWith("Build type set to '{$buildType}'.");
    }

    /** @param array<string, mixed> $args */
    private function requireString(array $args, string $key): ?string
    {
        $value = trim((string) ($args[$key] ?? ''));
        return $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $args */
    private function optionalString(array $args, string $key): ?string
    {
        if (!isset($args[$key])) {
            return null;
        }
        $value = trim((string) $args[$key]);
        return $value !== '' ? $value : null;
    }
}
