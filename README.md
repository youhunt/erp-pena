# PENA ERP

PENA ERP is an enterprise ERP foundation built with CodeIgniter 4, CodeIgniter Shield, MySQL/MariaDB, and a Skote-compatible admin layout structure.

## Current Stage

This repository was empty at initial audit time, so the first implementation created the ERP foundation. The current codebase must be continued, not regenerated from scratch.

Implemented foundation includes:

- CodeIgniter 4 appstarter `v4.7.3`
- CodeIgniter Shield `v1.3.0`
- ERP role and permission matrix
- Multi-company and multi-site database foundation
- Tenant bootstrap and active company/site switcher
- Permission guard filter for protected ERP routes
- Setup/master generic CRUD foundation
- Sales, purchase, inventory, finance, AP/AR, production, and AI/OCR route foundations
- Dynamic sidebar model and service
- Skote assets extracted from `resources.zip` into `public/assets/skote`
- Vendor-neutral OCR/AI service contracts
- Documentation under `docs/`

The current continuation adds:

- Formal repository audit documentation
- Reusable tenant scope helper: `App\Services\Support\TenantScope`
- Enterprise document numbering service: `App\Services\Support\DocumentNumberService`
- Local readiness command: `php spark pena:health`
- Document number CLI helper: `php spark pena:docno`
- Tenant and permission hardening guide
- Updated continuation plan for staged development

Skote assets are stored in `resources.zip` and extracted into `public/assets/skote` for the current layout.

## Requirements

- PHP 8.2+
- Composer 2+
- MySQL 8+ or MariaDB 10.6+
- PHP extensions commonly required by CodeIgniter: `intl`, `mbstring`, `json`, `mysqlnd`, `curl`

## Quick Start

```bash
git clone https://github.com/youhunt/erp-pena.git
cd erp-pena
composer install
cp env .env
```

Edit `.env`:

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'

database.default.hostname = localhost
database.default.database = pena_erp
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3306

wilayah.baseUrl = 'https://api-wilayah.belajardisiniaja.com'
wilayah.apiToken = 'YOUR_WILAYAH_API_TOKEN'
```

Create database:

```sql
CREATE DATABASE pena_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Run migrations and seeders:

```bash
php spark migrate --all
php spark db:seed PenaErpSeeder
```

Run local readiness check:

```bash
php spark pena:health
```

Preview/generate document number:

```bash
php spark pena:docno SO --preview --company=1 --site=1 --prefix=SO --format="{PREFIX}/{YYYY}{MM}/{SEQ}" --reset-period=monthly --padding=5
php spark pena:docno SO --company=1 --site=1 --prefix=SO --format="{PREFIX}/{YYYY}{MM}/{SEQ}" --reset-period=monthly --padding=5
```

Start the app:

```bash
php spark serve
```

Open:

```text
http://localhost:8080
```

Default admin:

- Email: `admin@pena-erp.local`
- Password: `Admin123!`

Change the password immediately after first login.

## Core Security Notes

- ERP routes are protected by Shield session authentication.
- Tenant context is initialized by `TenantBootstrapFilter`.
- Setup/master tenant safety is guarded by `SetupMasterTenantGuardFilter`.
- Route-level permission is guarded by `PermissionGuardFilter`.
- Sidebar visibility is not treated as security; direct URL access must also pass permission checks.
- New service/controller code should use `App\Services\Support\TenantScope` for active `company_id` and `site_id` handling.
- New transactional modules should use `App\Services\Support\DocumentNumberService` for PO, SO, invoice, receipt, payment, and journal numbers.

## Development Rule

Before adding a new module or route:

1. Add or verify migration.
2. Add or verify seeder/menu entry.
3. Add route.
4. Add permission mapping in `PermissionGuardFilter` when the route is protected.
5. Use `TenantScope` for tenant-owned query/insert/update.
6. Use `DocumentNumberService` for transaction document numbers.
7. Add audit log for important changes.
8. Test with Super Admin and non-admin role.

## Documentation

- [Repository Audit](docs/00-repository-audit.md)
- [Overview](docs/01-overview.md)
- [Installation](docs/02-installation.md)
- [Architecture](docs/03-architecture.md)
- [Database Design](docs/04-database-design.md)
- [Module Workflow](docs/05-module-workflow.md)
- [AI/OCR Workflow](docs/06-ai-ocr-workflow.md)
- [Auth, Role, Permission](docs/07-auth-role-permission.md)
- [Development Guide](docs/08-development-guide.md)
- [Testing Checklist](docs/09-testing-checklist.md)
- [Roadmap](docs/10-roadmap.md)
- [Continuation Plan](docs/11-continuation-plan.md)
- [Development Priority Plan](docs/12-development-priority-plan.md)
- [Tenant and Permission Hardening](docs/13-tenant-permission-hardening.md)
- [Document Number Service](docs/14-document-number-service.md)
