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
 * Manage Dokploy service backups — web server and compose service
 * backup policies, manual triggers, and file listing.
 *
 * Database-specific backups (postgres, mysql, mariadb, mongo) are
 * handled by the dokploy_db_backup tool in coqui-toolkit-dokploy-database.
 */
final readonly class BackupTool
{
    public function __construct(
        private DokployClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'dokploy_backup',
            description: 'Manage Dokploy service backups — web server and compose service backup policies, manual triggers, and file listing. For database backups, use dokploy_db_backup.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: [
                        'get', 'create', 'update', 'delete',
                        'run', 'list_files',
                    ],
                    required: true,
                ),
                new StringParameter(
                    'backupId',
                    'Backup policy ID (required for get, update, delete, run, list_files).',
                    required: false,
                ),
                new StringParameter(
                    'destinationId',
                    'Destination ID for backup storage (required for create).',
                    required: false,
                ),
                new EnumParameter(
                    'databaseType',
                    'Service type for the backup (required for create, run). Database types are handled by dokploy_db_backup.',
                    values: ['web-server', 'compose'],
                    required: false,
                ),
                new StringParameter(
                    'schedule',
                    'Cron expression for automated backups (e.g. "0 3 * * *" for daily at 3am).',
                    required: false,
                ),
                new StringParameter(
                    'prefix',
                    'Backup filename prefix.',
                    required: false,
                ),
                new StringParameter(
                    'database',
                    'Specific database name to back up.',
                    required: false,
                ),
                new StringParameter(
                    'composeId',
                    'Compose service ID to associate the backup with.',
                    required: false,
                ),
                new StringParameter(
                    'serverId',
                    'Server ID to associate the backup with.',
                    required: false,
                ),
                new StringParameter(
                    'enabled',
                    'Whether the backup schedule is enabled (true/false).',
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
            'get' => $this->getBackup($args),
            'create' => $this->createBackup($args),
            'update' => $this->updateBackup($args),
            'delete' => $this->deleteBackup($args),
            'run' => $this->runBackup($args),
            'list_files' => $this->listFiles($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /** @param array<string, mixed> $args */
    private function getBackup(array $args): ToolResult
    {
        $backupId = $this->requireString($args, 'backupId');
        if ($backupId === null) {
            return ToolResult::error('backupId is required for the "get" action.');
        }

        return $this->client->get('backup.one', ['backupId' => $backupId])
            ->toToolResultWith('Backup policy details:');
    }

    /** @param array<string, mixed> $args */
    private function createBackup(array $args): ToolResult
    {
        $destinationId = $this->requireString($args, 'destinationId');
        if ($destinationId === null) {
            return ToolResult::error('destinationId is required for the "create" action.');
        }

        $databaseType = $this->requireString($args, 'databaseType');
        if ($databaseType === null) {
            return ToolResult::error('databaseType is required for the "create" action.');
        }

        $body = [
            'destinationId' => $destinationId,
            'databaseType' => $databaseType,
        ];

        foreach (['schedule', 'prefix', 'database', 'composeId', 'serverId'] as $field) {
            $value = $this->optionalString($args, $field);
            if ($value !== null) {
                $body[$field] = $value;
            }
        }

        $enabled = $this->optionalString($args, 'enabled');
        if ($enabled !== null) {
            $body['enabled'] = ($enabled === 'true' || $enabled === '1');
        }

        return $this->client->post('backup.create', $body)
            ->toToolResultWith('Backup policy created.');
    }

    /** @param array<string, mixed> $args */
    private function updateBackup(array $args): ToolResult
    {
        $backupId = $this->requireString($args, 'backupId');
        if ($backupId === null) {
            return ToolResult::error('backupId is required for the "update" action.');
        }

        $body = ['backupId' => $backupId];

        foreach (['schedule', 'prefix', 'database', 'destinationId', 'databaseType'] as $field) {
            $value = $this->optionalString($args, $field);
            if ($value !== null) {
                $body[$field] = $value;
            }
        }

        $enabled = $this->optionalString($args, 'enabled');
        if ($enabled !== null) {
            $body['enabled'] = ($enabled === 'true' || $enabled === '1');
        }

        return $this->client->post('backup.update', $body)
            ->toToolResultWith('Backup policy updated.');
    }

    /** @param array<string, mixed> $args */
    private function deleteBackup(array $args): ToolResult
    {
        $backupId = $this->requireString($args, 'backupId');
        if ($backupId === null) {
            return ToolResult::error('backupId is required for the "delete" action.');
        }

        return $this->client->post('backup.remove', ['backupId' => $backupId])
            ->toToolResultWith('Backup policy deleted.');
    }

    /** @param array<string, mixed> $args */
    private function runBackup(array $args): ToolResult
    {
        $backupId = $this->requireString($args, 'backupId');
        if ($backupId === null) {
            return ToolResult::error('backupId is required for the "run" action.');
        }

        $databaseType = $this->requireString($args, 'databaseType');
        if ($databaseType === null) {
            return ToolResult::error('databaseType is required for the "run" action.');
        }

        // The Dokploy API has type-specific backup endpoints
        // Database types (postgres, mysql, mariadb, mongo) are handled by dokploy_db_backup
        $endpointMap = [
            'web-server' => 'backup.manualBackupWebServer',
            'compose' => 'backup.manualBackupCompose',
        ];

        $endpoint = $endpointMap[$databaseType] ?? null;
        if ($endpoint === null) {
            return ToolResult::error("Unknown database type for backup: {$databaseType}");
        }

        return $this->client->post($endpoint, ['backupId' => $backupId])
            ->toToolResultWith("Manual {$databaseType} backup triggered.");
    }

    /** @param array<string, mixed> $args */
    private function listFiles(array $args): ToolResult
    {
        $backupId = $this->requireString($args, 'backupId');
        if ($backupId === null) {
            return ToolResult::error('backupId is required for the "list_files" action.');
        }

        return $this->client->get('backup.listBackupFiles', ['backupId' => $backupId])
            ->toToolResultWith('Available backup files:');
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
