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
 * Manage Dokploy projects — the top-level organizational unit.
 *
 * Projects contain environments, which in turn contain
 * applications, compose services, and databases.
 */
final readonly class ProjectTool
{
    public function __construct(
        private DokployClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'dokploy_project',
            description: 'Manage Dokploy projects — list all, get details, create, update, delete, search, or duplicate a project.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['list', 'get', 'create', 'update', 'delete', 'search', 'duplicate'],
                    required: true,
                ),
                new StringParameter(
                    'projectId',
                    'Project ID (required for get, update, delete).',
                    required: false,
                ),
                new StringParameter(
                    'name',
                    'Project name (required for create, duplicate).',
                    required: false,
                ),
                new StringParameter(
                    'description',
                    'Project description (optional for create, update).',
                    required: false,
                ),
                new StringParameter(
                    'query',
                    'Search query string (for search action).',
                    required: false,
                ),
                new StringParameter(
                    'sourceEnvironmentId',
                    'Source environment ID (required for duplicate).',
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
            'list' => $this->listProjects(),
            'get' => $this->getProject($args),
            'create' => $this->createProject($args),
            'update' => $this->updateProject($args),
            'delete' => $this->deleteProject($args),
            'search' => $this->searchProjects($args),
            'duplicate' => $this->duplicateProject($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    private function listProjects(): ToolResult
    {
        return $this->client->get('project.all')
            ->toToolResultWith('All projects:');
    }

    /** @param array<string, mixed> $args */
    private function getProject(array $args): ToolResult
    {
        $projectId = $this->requireString($args, 'projectId');
        if ($projectId === null) {
            return ToolResult::error('projectId is required for the "get" action.');
        }

        return $this->client->get('project.one', ['projectId' => $projectId])
            ->toToolResultWith('Project details:');
    }

    /** @param array<string, mixed> $args */
    private function createProject(array $args): ToolResult
    {
        $name = $this->requireString($args, 'name');
        if ($name === null) {
            return ToolResult::error('name is required for the "create" action.');
        }

        $body = ['name' => $name];

        $description = $this->optionalString($args, 'description');
        if ($description !== null) {
            $body['description'] = $description;
        }

        return $this->client->post('project.create', $body)
            ->toToolResultWith("Project '{$name}' created.");
    }

    /** @param array<string, mixed> $args */
    private function updateProject(array $args): ToolResult
    {
        $projectId = $this->requireString($args, 'projectId');
        if ($projectId === null) {
            return ToolResult::error('projectId is required for the "update" action.');
        }

        $body = ['projectId' => $projectId];

        $name = $this->optionalString($args, 'name');
        if ($name !== null) {
            $body['name'] = $name;
        }

        $description = $this->optionalString($args, 'description');
        if ($description !== null) {
            $body['description'] = $description;
        }

        return $this->client->post('project.update', $body)
            ->toToolResultWith('Project updated.');
    }

    /** @param array<string, mixed> $args */
    private function deleteProject(array $args): ToolResult
    {
        $projectId = $this->requireString($args, 'projectId');
        if ($projectId === null) {
            return ToolResult::error('projectId is required for the "delete" action.');
        }

        return $this->client->post('project.remove', ['projectId' => $projectId])
            ->toToolResultWith('Project deleted.');
    }

    /** @param array<string, mixed> $args */
    private function searchProjects(array $args): ToolResult
    {
        $query = [];

        $q = $this->optionalString($args, 'query');
        if ($q !== null) {
            $query['q'] = $q;
        }

        return $this->client->get('project.search', $query)
            ->toToolResultWith('Project search results:');
    }

    /** @param array<string, mixed> $args */
    private function duplicateProject(array $args): ToolResult
    {
        $sourceEnvironmentId = $this->requireString($args, 'sourceEnvironmentId');
        if ($sourceEnvironmentId === null) {
            return ToolResult::error('sourceEnvironmentId is required for the "duplicate" action.');
        }

        $name = $this->requireString($args, 'name');
        if ($name === null) {
            return ToolResult::error('name is required for the "duplicate" action.');
        }

        $body = [
            'sourceEnvironmentId' => $sourceEnvironmentId,
            'name' => $name,
        ];

        $description = $this->optionalString($args, 'description');
        if ($description !== null) {
            $body['description'] = $description;
        }

        return $this->client->post('project.duplicate', $body)
            ->toToolResultWith("Project duplicated as '{$name}'.");
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
