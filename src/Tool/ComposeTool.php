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
 * Manage Dokploy Compose services — multi-container stacks
 * defined via docker-compose or Docker Swarm templates.
 */
final readonly class ComposeTool
{
    public function __construct(
        private DokployClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'dokploy_compose',
            description: 'Manage Dokploy Compose services — get, create, update, delete, deploy, redeploy, start, stop, search, list templates, deploy a template, load services, or import a compose file.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: [
                        'get', 'create', 'update', 'delete',
                        'deploy', 'redeploy', 'start', 'stop',
                        'search', 'templates', 'deploy_template',
                        'services', 'import',
                    ],
                    required: true,
                ),
                new StringParameter(
                    'composeId',
                    'Compose service ID (required for most actions except create, search, templates).',
                    required: false,
                ),
                new StringParameter(
                    'name',
                    'Compose service name (required for create).',
                    required: false,
                ),
                new StringParameter(
                    'environmentId',
                    'Environment ID (required for create).',
                    required: false,
                ),
                new StringParameter(
                    'description',
                    'Service description.',
                    required: false,
                ),
                new StringParameter(
                    'appName',
                    'Docker compose app name.',
                    required: false,
                ),
                new EnumParameter(
                    'composeType',
                    'Stack type (for create/update).',
                    values: ['docker-compose', 'stack'],
                    required: false,
                ),
                new StringParameter(
                    'composeFile',
                    'Docker compose file content (for import action).',
                    required: false,
                ),
                new StringParameter(
                    'templateId',
                    'Template ID to deploy (for deploy_template action). Use "templates" action to list available templates.',
                    required: false,
                ),
                new StringParameter(
                    'serverId',
                    'Target server ID (optional for create, deploy_template).',
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
                    'serviceName',
                    'Specific service name to load (for services action).',
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
            'get' => $this->getCompose($args),
            'create' => $this->createCompose($args),
            'update' => $this->updateCompose($args),
            'delete' => $this->deleteCompose($args),
            'deploy' => $this->deployCompose($args),
            'redeploy' => $this->redeployCompose($args),
            'start' => $this->startCompose($args),
            'stop' => $this->stopCompose($args),
            'search' => $this->searchCompose($args),
            'templates' => $this->listTemplates(),
            'deploy_template' => $this->deployTemplate($args),
            'services' => $this->loadServices($args),
            'import' => $this->importCompose($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /** @param array<string, mixed> $args */
    private function getCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "get" action.');
        }

        return $this->client->get('compose.one', ['composeId' => $composeId])
            ->toToolResultWith('Compose service details:');
    }

    /** @param array<string, mixed> $args */
    private function createCompose(array $args): ToolResult
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

        foreach (['description', 'appName', 'composeType', 'serverId'] as $field) {
            $value = $this->optionalString($args, $field);
            if ($value !== null) {
                $body[$field] = $value;
            }
        }

        return $this->client->post('compose.create', $body)
            ->toToolResultWith("Compose service '{$name}' created.");
    }

    /** @param array<string, mixed> $args */
    private function updateCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "update" action.');
        }

        $body = ['composeId' => $composeId];

        foreach (['name', 'description', 'appName', 'composeType', 'composeFile'] as $field) {
            $value = $this->optionalString($args, $field);
            if ($value !== null) {
                $body[$field] = $value;
            }
        }

        return $this->client->post('compose.update', $body)
            ->toToolResultWith('Compose service updated.');
    }

    /** @param array<string, mixed> $args */
    private function deleteCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "delete" action.');
        }

        return $this->client->post('compose.delete', ['composeId' => $composeId])
            ->toToolResultWith('Compose service deleted.');
    }

    /** @param array<string, mixed> $args */
    private function deployCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "deploy" action.');
        }

        $body = ['composeId' => $composeId];

        $title = $this->optionalString($args, 'title');
        if ($title !== null) {
            $body['title'] = $title;
        }

        $description = $this->optionalString($args, 'description');
        if ($description !== null) {
            $body['description'] = $description;
        }

        return $this->client->post('compose.deploy', $body)
            ->toToolResultWith('Compose deployment initiated.');
    }

    /** @param array<string, mixed> $args */
    private function redeployCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "redeploy" action.');
        }

        $body = ['composeId' => $composeId];

        $title = $this->optionalString($args, 'title');
        if ($title !== null) {
            $body['title'] = $title;
        }

        return $this->client->post('compose.redeploy', $body)
            ->toToolResultWith('Compose redeployment initiated.');
    }

    /** @param array<string, mixed> $args */
    private function startCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "start" action.');
        }

        return $this->client->post('compose.start', ['composeId' => $composeId])
            ->toToolResultWith('Compose service started.');
    }

    /** @param array<string, mixed> $args */
    private function stopCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "stop" action.');
        }

        return $this->client->post('compose.stop', ['composeId' => $composeId])
            ->toToolResultWith('Compose service stopped.');
    }

    /** @param array<string, mixed> $args */
    private function searchCompose(array $args): ToolResult
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

        return $this->client->get('compose.search', $query)
            ->toToolResultWith('Compose search results:');
    }

    private function listTemplates(): ToolResult
    {
        return $this->client->get('compose.templates')
            ->toToolResultWith('Available Compose templates:');
    }

    /** @param array<string, mixed> $args */
    private function deployTemplate(array $args): ToolResult
    {
        $templateId = $this->requireString($args, 'templateId');
        if ($templateId === null) {
            return ToolResult::error('templateId is required for the "deploy_template" action. Use "templates" action to list available templates.');
        }

        $body = ['id' => $templateId];

        $serverId = $this->optionalString($args, 'serverId');
        if ($serverId !== null) {
            $body['serverId'] = $serverId;
        }

        return $this->client->post('compose.deployTemplate', $body)
            ->toToolResultWith('Template deployment initiated.');
    }

    /** @param array<string, mixed> $args */
    private function loadServices(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "services" action.');
        }

        $query = ['composeId' => $composeId];

        $serviceName = $this->optionalString($args, 'serviceName');
        if ($serviceName !== null) {
            $query['serviceName'] = $serviceName;
        }

        return $this->client->get('compose.loadServices', $query)
            ->toToolResultWith('Compose services:');
    }

    /** @param array<string, mixed> $args */
    private function importCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "import" action.');
        }

        $composeFile = $this->requireString($args, 'composeFile');
        if ($composeFile === null) {
            return ToolResult::error('composeFile is required for the "import" action. Provide the compose file content.');
        }

        return $this->client->post('compose.import', [
            'composeId' => $composeId,
            'composeFile' => base64_encode($composeFile),
        ])->toToolResultWith('Compose file imported.');
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
