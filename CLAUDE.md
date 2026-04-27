# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What is ITFlow

ITFlow is a free, open-source IT documentation, ticketing, and accounting platform for small MSPs. It consolidates client documentation (assets, contacts, domains, passwords), ticketing, and billing (quotes, invoices, accounting) into one system.

## Development Setup

ITFlow is a traditional PHP monolith with no build step. It requires:
- PHP-enabled web server (Apache with mod_php or nginx with PHP-FPM)
- MySQL/MariaDB database
- `config.php` at the project root (created during setup wizard)

**Installation:** Use the install script on Ubuntu/Debian:
```bash
wget -O itflow_install.sh https://github.com/itflow-org/itflow-install-script/raw/main/itflow_install.sh
bash itflow_install.sh
```

**Database schema:** `db.sql` at project root (132 tables).

**Composer dependencies** (email parsing only):
```bash
cd plugins && composer install
```

## Linting

PHPLint runs automatically on pull requests via GitHub Actions (`.github/workflows/php-lint.yml`). To run locally:
```bash
vendor/bin/phplint .
```

There is no test suite for the core application.

## Architecture

### Routing

File-based routing — URLs map directly to PHP files. Entry point is `index.php`, which redirects based on login status and role:
- Agents/staff → `/agent/{start_page}`
- Clients → `/client/`
- Not logged in → `/login.php`
- Setup incomplete → `/setup/`

### Directory Structure

| Path | Purpose |
|------|---------|
| `/agent/` | Main app UI for technicians (clients, tickets, invoices, assets, docs, passwords, etc.) |
| `/admin/` | System administration (users, roles, settings, audit logs, backups) |
| `/client/` | Client self-service portal (quotes, invoices, tickets) |
| `/guest/` | Unauthenticated pages (client login, portal) |
| `/api/v1/` | REST API endpoints |
| `/cron/` | Background jobs (certificate refresh, domain refresh, mail queue, ticket email parser) |
| `/includes/` | Shared auth checks, DB connection, session management, header/footer templates |
| `/post/` | Form POST handlers |
| `/plugins/` | Bundled third-party libraries (Stripe, PHPMailer, TinyMCE, TCPDF, etc.) |

### Key Files

- **`functions.php`** — ~90KB global utility library (~200+ functions). Includes security helpers, encryption, role checks, email queueing, domain/SSL lookups, formatting, and PDF generation.
- **`config.php`** — Static config (database credentials, timezone, HTTPS enforcement, setup flag). Not committed to git.
- **`includes/db.php`** — MySQLi connection (`$mysqli` global).
- **`includes/auth_check.php`** / **`includes/check_login.php`** — Session validation and role-based redirects.

### Database Access

No ORM — raw MySQLi queries with procedural code. Pattern used throughout:
- Sanitize inputs via `sanitizeInput()` (wraps `mysqli_real_escape_string` + `htmlspecialchars`)
- Use `SQL_CALC_FOUND_ROWS` for paginated queries
- `$mysqli` is a global connection object

### Authentication

Session-based with PHP sessions. Two user types:
- **Type 1** — Agent/Staff (access `/agent/` and `/admin/`)
- **Type 2** — Client (access `/client/` portal)

Features: TOTP MFA, "remember me" tokens, IP-based lockout (15 failures in 10 min).

Role permissions are enforced via `enforceUserPermission('module_name')` (e.g., `enforceUserPermission('module_financial')`).

### Page Request Flow

1. Page includes `/agent/includes/inc_all.php` (loads config, functions, auth, session, renders header/nav)
2. Page runs SQL query with filters (search, sort, pagination)
3. Renders HTML — DataTables handles client-side pagination/sorting
4. Modals loaded via AJAX from `/agent/modals/{resource}/{action}.php`
5. Forms POST to inline handlers or `/admin/post/` handlers

### API

RESTful endpoints under `/api/v1/{resource}/{action}.php`. Authentication via API key header. Each endpoint includes `validate_api_key.php`, a method enforcer (`require_post_method.php` or `require_get_method.php`), a model file (parses + sanitizes POST/GET), and an output file (JSON response).

### Security Patterns

- CSRF tokens validated via `validateCSRFToken()` — all state-changing forms must include a CSRF token
- Credential encryption uses AES-256 with per-user keys (`encryptCredentialEntry()` / `decryptCredentialEntry()`)
- All actions logged via `logAction()`
- Email sent asynchronously — `addToMailQueue()` writes to queue table, cron processes it

### Frontend

Bundled libraries in `/css/` and `/js/` — AdminLTE 3, Bootstrap 4, jQuery, DataTables, Select2, TinyMCE, FullCalendar, Chart.js, Stripe.js, TCPDF, Moment.js. No npm/Node build process.

### Settings

Two-tier configuration:
- **Static** (`config.php`): DB credentials, HTTPS enforcement, setup state, cron enable flag
- **Dynamic** (`settings` DB table): company name/logo, SMTP config, invoice settings, feature toggles, AI providers

The `settings` table has a single row (`company_id = 1`). To add a new setting:
1. `ALTER TABLE settings ADD COLUMN config_xxx ...` (run directly on DB — no migration runner)
2. Extract in `includes/load_global_settings.php` as `$config_xxx = $row['config_xxx'] ?? null;`
3. Create `admin/settings_xxx.php` (view) and `admin/post/settings_xxx.php` (handler)
4. The admin POST handler is auto-loaded by `admin/post.php` based on the referring page name — no registration needed
5. Add the nav link to `admin/includes/side_nav.php`

### Client Context (`inc_all_client.php`)

Pages under `/agent/` that show per-client data include `includes/inc_all_client.php` instead of `includes/inc_all.php`. This file queries the client row and extracts all `$client_*` variables — but `$row` is **overwritten many times** by subsequent badge-count queries. Never rely on `$row` after `inc_all_client.php` finishes. If you need a client field, extract it as a named variable inside `inc_all_client.php`.

### POST Handler Routing

- **Admin** (`admin/post.php`): dynamically loads `admin/post/{page_basename}.php` based on `HTTP_REFERER`. One file per settings page.
- **Agent** (`agent/post.php`): loads every file in `agent/post/*.php` on every request (files matching `*_model.php` are skipped — these are input-parsing helpers included by the main handler).

### Adding a New Agent Page for a Client

1. Create `agent/{page}.php`, start with `require_once "includes/inc_all_client.php";`
2. Add the nav link in `agent/includes/client_side_nav.php`
3. All `$client_*` variables and `$client_id` are available after the include

## Deployment (DBYTE Production)

Production runs on **AWS ECS Fargate** (cluster `itflow-prod`, container `itflow`) with **RDS MySQL** (`itflow-prod-mysql`, database `itflow`). Credentials are in AWS Secrets Manager under `itflow-prod/db-password` (profile `dbytesrl`, region `us-east-1`).

**The Docker image bakes in the PHP code.** Only `/var/www/html/uploads` and `/var/www/html/config-volume` are persisted via EFS. File changes pushed directly to the running container via `ecs execute-command` are lost on the next deploy/restart.

**ECR repository:** `686100069131.dkr.ecr.us-east-1.amazonaws.com/itflow-prod:latest`

**GitHub fork:** `https://github.com/bertuja/itflow` (remote alias: `dbyte`). The upstream is `itflow-org/itflow` (remote alias: `origin`). Always push DBYTE changes to `dbyte` remote, never to `origin`.

### Persistencia crítica — leer antes de cualquier deploy

`config.php` **no está en la imagen Docker** (excluido por `.dockerignore`). En cada arranque, `docker-entrypoint.sh` lo restaura desde el **EFS volume** (`/var/www/html/config-volume/config.php`). Esto garantiza que `installation_id`, las claves de cifrado de credenciales y el MFA sean siempre consistentes.

**Nunca borres `/var/www/html/config-volume/config.php` del EFS** — hacerlo rompe el MFA y todas las credenciales cifradas de todos los usuarios.

Si por algún motivo el EFS estuviera vacío, el entrypoint genera `config.php` desde las env vars de la task definition (`:4`), con `INSTALLATION_ID=a0f2802bffa2c8724a19d63c85c4d41d` fijo.

### Deploy completo (build → ECR → ECS)

```bash
cd "/Users/bertuja/Proyectos 2026/DBYTE/itflow"

# 1. Commit y push a GitHub
git add <archivos> && git commit -m "DBYTE: descripción"
git push dbyte master

# 2. Login ECR
aws ecr get-login-password --region us-east-1 --profile dbytesrl | \
  docker login --username AWS --password-stdin 686100069131.dkr.ecr.us-east-1.amazonaws.com

# 3. Build (mac M-series → linux/amd64 para Fargate)
docker build --platform linux/amd64 \
  -t 686100069131.dkr.ecr.us-east-1.amazonaws.com/itflow-prod:latest .

# 4. Push
docker push 686100069131.dkr.ecr.us-east-1.amazonaws.com/itflow-prod:latest

# 5. Force redeploy (task definition actual: itflow-prod:4)
aws ecs update-service --profile dbytesrl --region us-east-1 \
  --cluster itflow-prod --service itflow-prod --force-new-deployment
```

El servicio tiene `minimumHealthyPercent=100` — levanta el nuevo container antes de parar el viejo (zero downtime).

### Hotfix rápido (sin redeploy de imagen)

Para cambios urgentes directamente en el container en ejecución. Los cambios se pierden al próximo redeploy — siempre hacer el deploy completo después.

```bash
# Obtener task ARN actual
TASK=$(aws ecs list-tasks --profile dbytesrl --region us-east-1 \
  --cluster itflow-prod --query 'taskArns[0]' --output text)

# Subir un archivo (evita problemas de quoting con scripts largos)
B64=$(base64 -i /ruta/local/archivo.php)
aws ecs execute-command --profile dbytesrl --region us-east-1 \
  --cluster itflow-prod --task $TASK --container itflow --interactive \
  --command "sh -c 'echo ${B64} | base64 -d > /var/www/html/ruta/archivo.php && echo OK'"
```

El execute-command solo funciona en tasks iniciados con `enableExecuteCommand=true`. El servicio ya lo tiene configurado (task def `:4`). Tasks lanzados manualmente con `run-task` sin el flag no lo soportan.

### Diagnóstico de errores 500

```bash
# Ver errores PHP en tiempo real
aws logs filter-log-events --profile dbytesrl --region us-east-1 \
  --log-group-name "/ecs/itflow-prod" \
  --filter-pattern "PHP" --limit 20 \
  --query 'events[*].message' --output text

# Ver requests 500 recientes
aws logs filter-log-events --profile dbytesrl --region us-east-1 \
  --log-group-name "/ecs/itflow-prod" \
  --filter-pattern "500" --limit 10 \
  --query 'events[*].message' --output text
```

### Schema migrations

No hay migration runner. Para agregar columnas:
```php
// MySQL en RDS no soporta IF NOT EXISTS en ALTER TABLE — verificar manualmente
$r = $mysqli->query("SELECT COUNT(*) as c FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tabla' AND COLUMN_NAME='columna'");
if ($r->fetch_assoc()['c'] == 0) {
    $mysqli->query("ALTER TABLE tabla ADD COLUMN columna ...");
}
```

### Bugs conocidos y reglas de codificación

- **`logAction()` y comillas simples:** nunca envolver variables con `'...'` en el mensaje de logAction — las comillas se insertan literal en SQL y rompen la query. Usar `: ` o concatenación sin comillas.
- **Form actions en modales:** usar `post.php` (sin `../`). Los form actions se resuelven relativos a la URL del browser, no del archivo modal. Desde `agent/project_details.php`, `post.php` resuelve a `agent/post.php` correctamente.
- **Consultas de usuarios:** usar `user_role_id > 1` (no `user_role`) y `user_status = 1` al listar agentes.

## DBYTE Customizations

This fork adds DBYTE-specific features on top of upstream ITFlow. All additions are backward-compatible — upstream upgrades should be applied on top without conflict, but check the files listed below for merge conflicts.

### Zoho Desk Integration

**Files:** `agent/zoho_tickets.php`, `agent/modals/zoho/ticket_detail.php`, `admin/settings_zoho.php`, `admin/post/settings_zoho.php`, `agent/includes/client_side_nav.php`, `agent/includes/inc_all_client.php`, `agent/client_overview.php`, `admin/includes/side_nav.php`, `agent/modals/client/client_edit.php`, `agent/post/client.php`, `agent/post/client_model.php`, `includes/load_global_settings.php`, `functions.php`

**DB columns added:**
- `settings`: `config_zoho_client_id`, `config_zoho_client_secret`, `config_zoho_refresh_token`, `config_zoho_org_id`, `config_zoho_access_token`, `config_zoho_access_token_expires_at`
- `clients`: `client_zoho_account_id`

**How it works:**
- Credentials stored in `settings` table; access token cached with expiry
- `getZohoAccessToken($mysqli)` in `functions.php` handles refresh automatically
- Each client links to a Zoho account via `client_zoho_account_id`
- **Zoho API constraint:** use `/api/v1/accounts/{id}/tickets` — the `/tickets` endpoint rejects `accountId`, `status`, `createdTimeRange` as query params
- Ticket detail modal fetches `/tickets/{id}` + `/tickets/{id}/threads` + `/tickets/{id}/comments`
- Comment author is in `commenter.name` + `commentedTime` (not `author`/`createdTime`)
- Content cleanup: `html_entity_decode()` + strip `zsu[@user:XXXXXXX]zsu` patterns
- Overview widget shows up to 5 open Zoho tickets per client

**Configure:** Admin → Settings → Zoho Desk → link per-client via client edit modal field "Zoho Account ID"

---

### Project Tasks (Kanban)

**Files:** `agent/project_details.php`, `agent/post/project_task.php`, `agent/modals/project/project_task_add.php`, `agent/modals/project/project_task_edit.php`

**DB table added:** `project_tasks`

```sql
project_task_id, project_task_name, project_task_description,
project_task_status ENUM('todo','in_progress','done'),
project_task_priority ENUM('low','medium','high','urgent'),
project_task_due DATE, project_task_assigned_to INT,
project_task_order INT, project_task_project_id INT,
project_task_created_by INT, project_task_created_at DATETIME,
project_task_completed_at DATETIME
```

**How it works:**
- Kanban board with 3 fixed columns rendered in `project_details.php` above the tickets table
- Drag & drop via `SortableJS` (already bundled at `plugins/SortableJS/Sortable.min.js`)
- Move saves via AJAX POST to `post.php?move_project_task=1`
- Progress bar in project header reflects `project_tasks` completion
- "New" button in project header now includes "Tarea" option
- **Users query:** use `user_role_id > 1` (not `user_role`) and `user_status = 1`
