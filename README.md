# PENA ERP

PENA ERP is an enterprise ERP foundation built with CodeIgniter 4, CodeIgniter Shield, MySQL/MariaDB, and a Skote-compatible admin layout structure.

## Current Stage

This repository was empty at audit time, so the first implementation creates the ERP foundation only:

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

Skote assets are stored in `resources.zip` and extracted into `public/assets/skote` for the current layout.

## Requirements

- PHP 8.2+
- Composer 2+
- MySQL 8+ or MariaDB 10.6+
- PHP extensions commonly required by CodeIgniter: `intl`, `mbstring`, `json`, `mysqlnd`, `curl`

## Quick Start

```bash
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

Install dependencies:

```bash
composer install
```

Run migrations and seeders:

```bash
php spark migrate --all
php spark db:seed PenaErpSeeder
```

Run the seeder again after menu or baseline master updates; it refreshes `menu_items` without deleting transactional data.

Start the app:

```bash
php spark serve
```

Default admin:

- Email: `admin@pena-erp.local`
- Password: `Admin123!`

Change the password immediately after first login.

## Documentation

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
