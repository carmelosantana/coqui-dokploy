# Dokploy Toolkit for Coqui

A Coqui toolkit that provides full API access to [Dokploy](https://dokploy.com), the self-hosted PaaS platform. Manage projects, applications, Docker Compose services, backups, deployments, containers, and domains through natural language.

## Installation

```bash
composer require coquibot/coqui-toolkit-dokploy
```

The toolkit is auto-discovered by Coqui — no configuration needed beyond credentials.

## Credentials

Two credentials are required:

| Key | Description |
|-----|-------------|
| `DOKPLOY_API_TOKEN` | API token from your Dokploy dashboard (Settings → API) |
| `DOKPLOY_BASE_URL` | Base URL of your Dokploy instance (e.g. `https://dokploy.example.com`) |

Set them via the Coqui `credentials` tool or add to your workspace `.env`:

```env
DOKPLOY_API_TOKEN=your-api-token-here
DOKPLOY_BASE_URL=https://dokploy.example.com
```

## Tools

| Tool | Actions | Purpose |
|------|---------|---------|
| `dokploy_project` | list, get, create, update, delete, search, duplicate | Project management |
| `dokploy_app` | get, create, update, delete, deploy, redeploy, start, stop, search, monitoring, env, build_type | Single-container application lifecycle |
| `dokploy_compose` | get, create, update, delete, deploy, redeploy, start, stop, search, templates, deploy_template, services, import | Multi-container Docker Compose stacks |
| `dokploy_backup` | get, create, update, delete, run, list_files | Backup policies and manual backup triggers |
| `dokploy_deployment` | list, list_by_compose, list_by_server, centralized, queue, kill, remove | Deployment history and queue management |
| `dokploy_docker` | containers, restart, config, containers_by_app | Docker container inspection and control |
| `dokploy_domain` | get, create, update, delete, by_app, by_compose, generate, validate | Domain and TLS certificate management |

## Example Workflows

### Deploy a new application

```
1. dokploy_project(action: "list")                              → find your project
2. dokploy_app(action: "create", name: "my-api", environmentId: "...")
3. dokploy_app(action: "build_type", applicationId: "...", buildType: "dockerfile")
4. dokploy_app(action: "env", applicationId: "...", env: "PORT=3000\nNODE_ENV=production")
5. dokploy_domain(action: "create", host: "api.example.com", applicationId: "...", https: true, certificateType: "letsencrypt")
6. dokploy_app(action: "deploy", applicationId: "...")
```

### Deploy from a template

```
1. dokploy_compose(action: "templates")                          → browse templates
2. dokploy_compose(action: "deploy_template", templateId: "...")  → one-click deploy
```

### Import a docker-compose file

```
1. dokploy_compose(action: "create", name: "my-stack", environmentId: "...")
2. dokploy_compose(action: "import", composeId: "...", composeFile: "<yaml content>")
3. dokploy_compose(action: "deploy", composeId: "...")
```

### Set up automated backups

```
1. dokploy_backup(action: "create", databaseType: "postgres", destinationId: "...", schedule: "0 3 * * *")
2. dokploy_backup(action: "run", backupId: "...", databaseType: "postgres")
```

### Troubleshoot a service

```
1. dokploy_docker(action: "containers_by_app", appName: "my-api")
2. dokploy_app(action: "monitoring", appName: "my-api")
3. dokploy_docker(action: "restart", containerId: "...")
```

## Gated Operations

Destructive operations require user confirmation (unless `--auto-approve` is enabled):

| Tool | Gated Actions |
|------|---------------|
| `dokploy_project` | delete |
| `dokploy_app` | delete, deploy, redeploy, stop |
| `dokploy_compose` | delete, deploy, redeploy, stop |
| `dokploy_backup` | delete, run |
| `dokploy_deployment` | kill, remove |
| `dokploy_docker` | restart |
| `dokploy_domain` | delete |

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Static analysis
composer analyse
```

## Resource Hierarchy

```
Project
└── Environment
    ├── Application (single container)
    └── Compose (multi-container stack)
        └── Services
```

Every application and compose service belongs to a project environment. Use `dokploy_project(action: "get", projectId: "...")` to discover environment IDs.

## Requirements

- PHP 8.4+
- Dokploy instance with API access enabled

## License

MIT
