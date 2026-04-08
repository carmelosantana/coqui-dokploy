<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitDokploy;

use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CarmeloSantana\CoquiToolkitDokploy\Runtime\DokployClient;
use CarmeloSantana\CoquiToolkitDokploy\Tool\ApplicationTool;
use CarmeloSantana\CoquiToolkitDokploy\Tool\BackupTool;
use CarmeloSantana\CoquiToolkitDokploy\Tool\ComposeTool;
use CarmeloSantana\CoquiToolkitDokploy\Tool\DeploymentTool;
use CarmeloSantana\CoquiToolkitDokploy\Tool\DockerTool;
use CarmeloSantana\CoquiToolkitDokploy\Tool\DomainTool;
use CarmeloSantana\CoquiToolkitDokploy\Tool\ProjectTool;

/**
 * Dokploy PaaS management toolkit for Coqui.
 *
 * Provides comprehensive Dokploy API access: projects, applications,
 * docker compose services, backups, deployments, Docker containers,
 * and domain management.
 */
final class DokployToolkit implements ToolkitInterface
{
    private readonly DokployClient $client;

    public function __construct(
        ?DokployClient $client = null,
    ) {
        $this->client = $client ?? DokployClient::fromEnv();
    }

    /**
     * @return array<\CarmeloSantana\PHPAgents\Contract\ToolInterface>
     */
    public function tools(): array
    {
        return [
            (new ProjectTool($this->client))->build(),
            (new ApplicationTool($this->client))->build(),
            (new ComposeTool($this->client))->build(),
            (new BackupTool($this->client))->build(),
            (new DeploymentTool($this->client))->build(),
            (new DockerTool($this->client))->build(),
            (new DomainTool($this->client))->build(),
        ];
    }

    public function guidelines(): string
    {
        return <<<'GUIDELINES'
        <DOKPLOY-GUIDELINES>
        ## Dokploy PaaS Toolkit

        You have full access to the Dokploy self-hosted PaaS API through the following tools:

        ### Tool Overview
        | Tool | Purpose |
        |------|---------|
        | **dokploy_project** | List/get/create/update/delete/search/duplicate projects |
        | **dokploy_app** | Manage single-container applications — CRUD, deploy, start/stop, env vars, build type |
        | **dokploy_compose** | Manage multi-container compose services — CRUD, deploy, templates, import compose files |
        | **dokploy_backup** | Manage service backup policies for web server and compose services, trigger manual backups, list backup files. Database backups are handled by `dokploy_db_backup` in the database toolkit. |
        | **dokploy_deployment** | View deployment history, manage the queue, kill or remove deployments |
        | **dokploy_docker** | Inspect Docker containers, restart containers, view daemon config |
        | **dokploy_domain** | Manage domains and TLS — CRUD, generate domains, validate DNS |

        ### Resource Hierarchy

        ```
        Project
        └── Environment
            ├── Application (single container)
            └── Compose (multi-container stack)
                └── Services
        ```

        Every application and compose service belongs to a project environment. You need the `environmentId` when creating new apps or compose services.

        ### Common Workflows

        **Deploy a new application:**
        1. `dokploy_project(action: "list")` — find or create a project
        2. `dokploy_app(action: "create", name: "my-api", environmentId: "...")`
        3. `dokploy_app(action: "build_type", applicationId: "...", buildType: "dockerfile")`
        4. `dokploy_app(action: "env", applicationId: "...", env: "PORT=3000\nNODE_ENV=production")`
        5. `dokploy_domain(action: "create", host: "api.example.com", applicationId: "...", https: true, certificateType: "letsencrypt")`
        6. `dokploy_app(action: "deploy", applicationId: "...")`

        **Deploy from a template:**
        1. `dokploy_compose(action: "templates")` — browse available templates
        2. `dokploy_compose(action: "deploy_template", templateId: "...")` — one-click deploy

        **Import a docker-compose file:**
        1. `dokploy_compose(action: "create", name: "my-stack", environmentId: "...")`
        2. `dokploy_compose(action: "import", composeId: "...", composeFile: "version: '3'\nservices:\n  web:\n    image: nginx")` — file is base64-encoded automatically
        3. `dokploy_compose(action: "deploy", composeId: "...")`

        **Set up automated backups (web-server / compose):**
        1. `dokploy_backup(action: "create", databaseType: "web-server", destinationId: "...", schedule: "0 3 * * *")`
        2. `dokploy_backup(action: "run", backupId: "...", databaseType: "web-server")` — trigger manually
        > For database backups (postgres, mysql, mariadb, mongo), use `dokploy_db_backup` from the database toolkit.

        **Restore from backup:**
        1. `dokploy_backup(action: "list_files", backupId: "...")` — see available backup files
        2. Share the backup file details with the user for restore guidance

        **Monitor deployments:**
        1. `dokploy_deployment(action: "centralized")` — view all recent deployments
        2. `dokploy_deployment(action: "list", applicationId: "...")` — filter by app
        3. `dokploy_deployment(action: "queue")` — check queued deployments

        **Troubleshoot a service:**
        1. `dokploy_docker(action: "containers_by_app", appName: "my-api")` — find containers
        2. `dokploy_app(action: "monitoring", appName: "my-api")` — check resource usage
        3. `dokploy_docker(action: "restart", containerId: "...")` — restart if needed

        ### Important Notes

        - **Project IDs** are required for most project operations. Use `dokploy_project(action: "list")` to discover them.
        - **Environment IDs** come from project details. Use `dokploy_project(action: "get", projectId: "...")`.
        - The compose `import` action automatically base64-encodes the file content — pass the raw compose YAML.
        - Backup `run` requires both `backupId` and `databaseType` to select the correct backup strategy.
        - **Database management** (postgres, mysql, mariadb, mongo, redis) and database backups are in a separate toolkit — install `coquibot/coqui-toolkit-dokploy-database` for those capabilities.
        - Domain validation (`validate` action) checks DNS resolution — useful before enabling HTTPS.
        </DOKPLOY-GUIDELINES>
        GUIDELINES;
    }
}
