# PENA ERP

PENA ERP is an enterprise ERP foundation built with CodeIgniter 4, CodeIgniter Shield, MySQL/MariaDB, and a Skote-compatible admin layout structure.

## Current Stage

This repository was empty at initial audit time, so the first implementation created the ERP foundation. The current codebase must be continued, not regenerated from scratch.

Implemented foundation includes:

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

The current continuation adds:

- Reusable tenant scope helper: `App\Services\Support\TenantScope`
- Enterprise document numbering service: `App\Services\Support\DocumentNumberService`
- Local readiness command: `php spark pena:health`
- Document number CLI helper: `php spark pena:docno`
- Automatic SO/PO/PR/DO/SI/PI/ARR/APP numbering when document number is left blank
- SO/PO import fixes for site lookup, PO+site key, and PO line discount/tax fields
- Relaxed PO import header charges validation: freight, other amount, special charge, VAT, and WHT can differ per row and are summed per PO+Site
- SO and PO form master auto-fill fixes for Select2/customer/supplier/item mapping
- Clear SO cancel vs back actions and `Reopen as Draft` for cancelled SO
- Hardened Purchase Receipt posting with stock-in and PO received/outstanding recalculation
- Hardened Sales Delivery posting with stock-out and SO delivered/outstanding recalculation
- Automatic AR/AP invoice numbering and clearer invoice posting forms
- Automatic AR receipt/AP payment numbering and clearer settlement forms
- Stock Card value audit: value in/out and running value
- GL Entries validation summary and trial balance summary
- Route permission hardening for transaction URLs and system routes
- Core master data normalization for customer, supplier, item, warehouse, and location aliases
- Core master data code guard and physical unique indexes for core master data
- ERP core transaction status guard documentation and UAT checklist
- Purchase Receipt and Sales Delivery reversal GL reference tracking
- PO activation and UAT fixes for PO VAT/WHT code screen
- Sales Order edit/update and requested commercial fields
- Production imports for BOM, Work Center, Routing, and Work Order
- Production edit for BOM, Work Center, Routing, and draft Work Order
- ERP Core UAT Flow Board at `/system/development-status`
- Development journey/status documentation and formal core UAT checklist
- ERP Core UAT manual test scenarios with sample input data and PASS/FAIL checklist

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

## Hosting Update Notes

For cPanel/phpMyAdmin hosting, run required SQL files under `database/hosting/` after pulling the latest source code.

Minimum SQL for the latest core flow:

```text
database/hosting/2026-06-20_update_document_number_and_po_line_tax.sql
database/hosting/2026-06-20_update_purchase_receipt_core.sql
database/hosting/2026-06-20_update_sales_delivery_core.sql
database/hosting/2026-06-20_normalize_core_master_data.sql
database/hosting/2026-06-20_update_receipt_delivery_reversal_gl.sql
database/hosting/2026-06-21_update_po_uat_feedback.sql
database/hosting/2026-06-21_update_sales_order_uat_feedback.sql
database/hosting/2026-06-21_update_system_menu_development_status.sql
```

Run this only after duplicate audit returns zero rows:

```text
database/hosting/2026-06-20_add_unique_core_master_indexes.sql
```

Optional audit SQL:

```text
database/hosting/2026-06-20_audit_core_master_codes.sql
```

Always back up the database before running SQL scripts.

## Core Security Notes

- ERP routes are protected by Shield session authentication.
- New service/controller code should use `App\Services\Support\TenantScope` for active `company_id` and `site_id` handling.
- New transactional modules should use `App\Services\Support\DocumentNumberService` for PO, SO, invoice, receipt, payment, and journal numbers.
- Sidebar visibility is not treated as security; direct URL access must also pass permission checks.
- Transaction button visibility is only UX; status guard must remain enforced in service layer.

## Development Rule

Before adding a new module or route:

1. Add or verify migration.
2. Add or verify seeder/menu entry.
3. Add route.
4. Add permission mapping when the route is protected.
5. Use `TenantScope` for tenant-owned query/insert/update.
6. Use `DocumentNumberService` for transaction document numbers.
7. Add audit log for important changes.
8. Test with Super Admin and non-admin role.
9. Update `docs/15-development-journey-status.md`, `docs/16-core-uat-status-checklist.md`, and `docs/28-erp-core-continuation.md` when development status changes.

## Testing Notes

Order import fixes from runtime feedback:

- Site lookup no longer queries `sites.site` unless the column exists.
- PO duplicate/import grouping uses `PO No + Site`.
- PO line discount/tax columns are supported: `line_discount_percent`, `line_discount_amount`, `line_vat_amount`, `line_wht_amount`.
- Legacy import headers `discount_percent` and `discount_amount` are treated as PO line discount fields.
- `freight_amount`, `other_amount`, `special_charge_amount`, `vat_amount`, and `wht_amount` are summed per PO+Site and no longer forced to be identical across rows.
- Transaction status guard is documented in `docs/21-transaction-status-guard.md`; UAT scenarios are listed in `docs/16-core-uat-status-checklist.md`.
- PO UAT feedback is documented in `docs/24-uat-feedback-po-fixes.md`.
- Sales Order edit UAT feedback is documented in `docs/25-uat-feedback-sales-order-edit.md`.
- Production import UAT feedback is documented in `docs/26-production-imports.md`.
- Production edit UAT is documented in `docs/27-production-edit-crud.md`.
- ERP Core continuation plan is documented in `docs/28-erp-core-continuation.md`.
- ERP Core manual UAT scenarios are documented in `docs/29-erp-core-uat-test-scenarios.md`.

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
- [Development Priority Plan](docs/12-development-priority-plan.md)
- [Document Number Service](docs/14-document-number-service.md)
- [Development Journey & Status](docs/15-development-journey-status.md)
- [Core UAT Status Checklist](docs/16-core-uat-status-checklist.md)
- [Core Settlement Hardening](docs/17-core-settlement-hardening.md)
- [Route Permission Hardening](docs/18-permission-hardening.md)
- [Core Master Data Hardening](docs/19-master-data-hardening.md)
- [Core Master Data Code Guard](docs/20-master-data-code-guard.md)
- [Transaction Status Guard](docs/21-transaction-status-guard.md)
- [Receipt and Delivery Reversal GL](docs/22-receipt-delivery-reversal-gl.md)
- [ERP Core Continuation Plan](docs/28-erp-core-continuation.md)
- [ERP Core UAT Test Scenarios](docs/29-erp-core-uat-test-scenarios.md)
