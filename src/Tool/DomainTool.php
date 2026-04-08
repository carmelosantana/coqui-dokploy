<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitDokploy\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\BoolParameter;
use CarmeloSantana\CoquiToolkitDokploy\Runtime\DokployClient;

/**
 * Manage domains and TLS certificates for Dokploy applications
 * and compose services.
 */
final readonly class DomainTool
{
    public function __construct(
        private DokployClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'dokploy_domain',
            description: 'Manage domains for Dokploy applications and compose services — get, create, update, delete, list by app or compose, generate a domain, or validate DNS.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: [
                        'get', 'create', 'update', 'delete',
                        'by_app', 'by_compose', 'generate', 'validate',
                    ],
                    required: true,
                ),
                new StringParameter(
                    'domainId',
                    'Domain ID (required for get, update, delete).',
                    required: false,
                ),
                new StringParameter(
                    'applicationId',
                    'Application ID (required for by_app, optional for create).',
                    required: false,
                ),
                new StringParameter(
                    'composeId',
                    'Compose service ID (required for by_compose, optional for create).',
                    required: false,
                ),
                new StringParameter(
                    'host',
                    'Domain hostname (required for create, validate — e.g. "app.example.com").',
                    required: false,
                ),
                new StringParameter(
                    'path',
                    'URL path prefix (default: "/").',
                    required: false,
                ),
                new StringParameter(
                    'port',
                    'Target port number.',
                    required: false,
                ),
                new EnumParameter(
                    'certificateType',
                    'TLS certificate type.',
                    values: ['none', 'letsencrypt', 'custom'],
                    required: false,
                ),
                new BoolParameter(
                    'https',
                    'Enable HTTPS (default: false).',
                    required: false,
                ),
                new StringParameter(
                    'serviceName',
                    'Docker service name (for compose domains).',
                    required: false,
                ),
                new StringParameter(
                    'serverId',
                    'Server ID (for generate action).',
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
            'get' => $this->getDomain($args),
            'create' => $this->createDomain($args),
            'update' => $this->updateDomain($args),
            'delete' => $this->deleteDomain($args),
            'by_app' => $this->byApp($args),
            'by_compose' => $this->byCompose($args),
            'generate' => $this->generate($args),
            'validate' => $this->validate($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /** @param array<string, mixed> $args */
    private function getDomain(array $args): ToolResult
    {
        $domainId = $this->requireString($args, 'domainId');
        if ($domainId === null) {
            return ToolResult::error('domainId is required for the "get" action.');
        }

        return $this->client->get('domain.one', ['domainId' => $domainId])
            ->toToolResultWith('Domain details:');
    }

    /** @param array<string, mixed> $args */
    private function createDomain(array $args): ToolResult
    {
        $host = $this->requireString($args, 'host');
        if ($host === null) {
            return ToolResult::error('host is required for the "create" action.');
        }

        $body = ['host' => $host];

        foreach (['applicationId', 'composeId', 'path', 'port', 'certificateType', 'serviceName'] as $field) {
            $value = $this->optionalString($args, $field);
            if ($value !== null) {
                $body[$field] = $value;
            }
        }

        if (isset($args['port'])) {
            $body['port'] = (int) $args['port'];
        }

        if (isset($args['https'])) {
            $body['https'] = (bool) $args['https'];
        }

        return $this->client->post('domain.create', $body)
            ->toToolResultWith("Domain '{$host}' created.");
    }

    /** @param array<string, mixed> $args */
    private function updateDomain(array $args): ToolResult
    {
        $domainId = $this->requireString($args, 'domainId');
        if ($domainId === null) {
            return ToolResult::error('domainId is required for the "update" action.');
        }

        $body = ['domainId' => $domainId];

        foreach (['host', 'path', 'certificateType', 'serviceName'] as $field) {
            $value = $this->optionalString($args, $field);
            if ($value !== null) {
                $body[$field] = $value;
            }
        }

        if (isset($args['port'])) {
            $body['port'] = (int) $args['port'];
        }

        if (isset($args['https'])) {
            $body['https'] = (bool) $args['https'];
        }

        return $this->client->post('domain.update', $body)
            ->toToolResultWith('Domain updated.');
    }

    /** @param array<string, mixed> $args */
    private function deleteDomain(array $args): ToolResult
    {
        $domainId = $this->requireString($args, 'domainId');
        if ($domainId === null) {
            return ToolResult::error('domainId is required for the "delete" action.');
        }

        return $this->client->post('domain.delete', ['domainId' => $domainId])
            ->toToolResultWith('Domain deleted.');
    }

    /** @param array<string, mixed> $args */
    private function byApp(array $args): ToolResult
    {
        $appId = $this->requireString($args, 'applicationId');
        if ($appId === null) {
            return ToolResult::error('applicationId is required for the "by_app" action.');
        }

        return $this->client->get('domain.byApplicationId', ['applicationId' => $appId])
            ->toToolResultWith('Domains for application:');
    }

    /** @param array<string, mixed> $args */
    private function byCompose(array $args): ToolResult
    {
        $composeId = $this->requireString($args, 'composeId');
        if ($composeId === null) {
            return ToolResult::error('composeId is required for the "by_compose" action.');
        }

        return $this->client->get('domain.byComposeId', ['composeId' => $composeId])
            ->toToolResultWith('Domains for compose service:');
    }

    /** @param array<string, mixed> $args */
    private function generate(array $args): ToolResult
    {
        $body = [];

        $appId = $this->optionalString($args, 'applicationId');
        if ($appId !== null) {
            $body['applicationId'] = $appId;
        }

        $composeId = $this->optionalString($args, 'composeId');
        if ($composeId !== null) {
            $body['composeId'] = $composeId;
        }

        $serverId = $this->optionalString($args, 'serverId');
        if ($serverId !== null) {
            $body['serverId'] = $serverId;
        }

        return $this->client->post('domain.generateDomain', $body)
            ->toToolResultWith('Generated domain:');
    }

    /** @param array<string, mixed> $args */
    private function validate(array $args): ToolResult
    {
        $host = $this->requireString($args, 'host');
        if ($host === null) {
            return ToolResult::error('host is required for the "validate" action.');
        }

        return $this->client->post('domain.validateDomain', ['host' => $host])
            ->toToolResultWith("DNS validation for '{$host}':");
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
