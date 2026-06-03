# PENA ERP

PENA ERP is an enterprise ERP foundation built with CodeIgniter 4, CodeIgniter Shield, MySQL/MariaDB, and a Skote-compatible admin layout structure.

## Current Stage

This repository was empty at initial audit time, so the first implementation created the ERP foundation:

- CodeIgniter 4 appstarter `v4.7.3`
- CodeIgniter Shield `v1.3.0`
- ERP role and permission matrix
- Multi-company and multi-site database foundation
- Migration and seeder baseline from the Excel data dictionary
- Dynamic sidebar model and service
- Skote assets extracted from `resources.zip` into `public/assets/skote`
- Initial dashboard, setup, and AI document upload pages
- CRUD foundation for company, site, department, warehouse, UoM, customer, supplier, and item
- Active company/site switcher in the Skote topbar
- Vendor-neutral OCR/AI service contracts
- Documentation under `docs/`

The current continuation branch adds formal repository audit documentation, route permission mapping, a reusable tenant scope helper, route-level Shield permission enforcement, and a local health-check command so development can continue safely without regenerating the project.

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
git checkout feature/audit-architecture-foundation
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

Create the database:

```sql
CREATE DATABASE pena_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Run migrations and seeders:

```bash
php spark migrate --all
php spark db:seed PenaErpSeeder
```

Run the seeder again after menu or baseline master updates; it refreshes `menu_items` without deleting transactional data.

Run local readiness check:

```bash
php spark pena:health
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

- All ERP routes remain protected by Shield `session` authentication.
- Sensitive route groups now also use the custom `permission` filter.
- Sidebar visibility is not treated as security; route-level permissions enforce access even if a URL is typed manually.
- `TenantBootstrapFilter` still initializes active company/site context for protected ERP areas.
- `App\Services\Support\TenantScope` is available for repositories, models, services, and controllers that need consistent `company_id` and `site_id` scoping.

## Implemented Core Areas

- Login, register, logout, and Shield auth route foundation
- Group/permission matrix in `app/Config/AuthGroups.php`
- Dashboard route and Skote-compatible layout foundation
- Dynamic sidebar through menu records and permission checks
- Company/site tenant context and topbar switcher foundation
- Setup/master CRUD foundation
- User and role admin foundation
- Sales Order and Purchase Order starter routes retained as existing foundation
- AI document routes retained as existing foundation, but no new OCR scope is added in this continuation
- Audit documentation and route permission map
- Local health check command: `php spark pena:health`

## Not Yet Implemented in This Core Continuation

- Inventory operational flow
- Purchasing receiving and vendor invoice flow
- Sales delivery order and sales invoice flow
- Accounting journal/posting flow
- POS
- Production
- OCR queue/worker hardening

These are intentionally deferred until the core system is runnable, permission-protected, and tenant-safe.

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
- [Route Permission Map](docs/12-route-permission-map.md)
