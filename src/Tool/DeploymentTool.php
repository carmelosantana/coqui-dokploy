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
 * View and manage Dokploy deployments — list by application,
 * compose service, or server; inspect the centralized feed;
 * and manage the deployment queue.
 */
final readonly class DeploymentTool
{
    public function __construct(
        private DokployClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'dokploy_deployment',
            description: 'View and manage Dokploy deployments — list by application, compose, or server; view centralized feed; inspect the queue; kill running processes; or remove old deployments.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: [
                        'list', 'list_by_compose', 'list_by_server',
                        'centralized', 'queue', 'kill', 'remove',
                    ],
                    required: true,
                ),
                new StringParameter(
                    'applicationId',
                    'Application ID (required for list action).',
                    required: false,
                ),
                new StringParameter(
                    'composeId',
                    'Compose service ID (required for list_by_compose action).',
                    required: false,
                ),
                new StringParameter(
                    'serverId',
                    'Server ID (required for list_by_server action).',
                    required: false,
                ),
                new StringParameter(
                    'deploymentId',
                    'Deployment ID (required for kill, remove actions).',
                    required: false,
                ),
                new StringParameter(
                    'page',
                    'Page number for paginated results (default: 1).',
                    required: false,
                ),
                new StringParameter(
                    'limit',
                    'Number of results per page (default: 20).',
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
            'list' => $this->listByApp($args),
            'list_by_compose' => $this->listByCompose($args),
            'list_by_server' => $this->listByServer($args),
            'centralized' => $this->centralized($args),
            'queue' => $this->queue(),
            'kill' => $this->kill($args),
            'remove' => $this->remove($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /** @param array<string, mixed> $args */
    private function listByApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "list" action.');
        }

        $query = ['applicationId' => $appId];
        $this->addPagination($args, $query);

        return $this->client->get('deployment.all', $query)
            ->toToolResultWith('Deployments for application:');
    }

    /** @param array<string, mixed> $args */
    private function listByCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "list_by_compose" action.');
        }

        $query = ['composeId' => $composeId];
        $this->addPagination($args, $query);

        return $this->client->get('deployment.allByCompose', $query)
            ->toToolResultWith('Deployments for compose service:');
    }

    /** @param array<string, mixed> $args */
    private function listByServer(array $args): ToolResult
    {
        $serverId = $this->requireString($args, 'serverId');
        if ($serverId === null) {
            return ToolResult::error('serverId is required for the "list_by_server" action.');
        }

        $query = ['serverId' => $serverId];
        $this->addPagination($args, $query);

        return $this->client->get('deployment.allByServer', $query)
            ->toToolResultWith('Deployments for server:');
    }

    /** @param array<string, mixed> $args */
    private function centralized(array $args): ToolResult
    {
        $query = [];
        $this->addPagination($args, $query);

        return $this->client->get('deployment.allCentralized', $query)
            ->toToolResultWith('Centralized deployment feed:');
    }

    private function queue(): ToolResult
    {
        return $this->client->get('deployment.queueList')
            ->toToolResultWith('Deployment queue:');
    }

    /** @param array<string, mixed> $args */
    private function kill(array $args): ToolResult
    {
        $deploymentId = $this->requireString($args, 'deploymentId');
        if ($deploymentId === null) {
            return ToolResult::error('deploymentId is required for the "kill" action.');
        }

        return $this->client->post('deployment.killProcess', ['deploymentId' => $deploymentId])
            ->toToolResultWith('Deployment process killed.');
    }

    /** @param array<string, mixed> $args */
    private function remove(array $args): ToolResult
    {
        $deploymentId = $this->requireString($args, 'deploymentId');
        if ($deploymentId === null) {
            return ToolResult::error('deploymentId is required for the "remove" action.');
        }

        return $this->client->post('deployment.removeDeployment', ['deploymentId' => $deploymentId])
            ->toToolResultWith('Deployment removed.');
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, string> $query
     */
    private function addPagination(array $args, array &$query): void
    {
        $page = $this->optionalString($args, 'page');
        if ($page !== null) {
            $query['page'] = $page;
        }

        $limit = $this->optionalString($args, 'limit');
        if ($limit !== null) {
            $query['limit'] = $limit;
        }
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
