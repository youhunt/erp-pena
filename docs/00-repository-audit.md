# Repository Audit

Date: 2026-06-03
Repository: `https://github.com/youhunt/erp-pena.git`
Branch audited: `main`

## 1. Audit Summary

The repository already contains a CodeIgniter 4 based ERP foundation. It must be continued and improved, not regenerated from scratch.

Current foundation includes:

- CodeIgniter 4 application structure.
- CodeIgniter Shield authentication and authorization dependency.
- Skote-compatible layout structure.
- ERP dashboard and menu foundation.
- Dynamic sidebar backed by menu records and permission checks.
- Multi-company and multi-site tenant context.
- Setup/master CRUD foundation.
- Sales Order and Purchase Order route foundation.
- AI/OCR document upload, processing, review, and conversion foundation.
- Documentation under `docs/`.

## 2. Framework and Dependencies

`composer.json` confirms the following stack:

- PHP `^8.2`
- `codeigniter4/framework` `^4.7`
- `codeigniter4/shield` `^1.3`
- PHPUnit development dependency

This satisfies the required backend stack for PENA ERP.

## 3. Existing Application Structure

Important paths identified during audit:

| Path | Current Role |
|---|---|
| `app/Config/Routes.php` | Main route registration, Shield auth routes, protected ERP route groups |
| `app/Config/AuthGroups.php` | Shield groups, permissions, and role-permission matrix |
| `app/Controllers/Admin` | User and role administration foundation |
| `app/Controllers/Setup` | Generic setup/master data CRUD controllers |
| `app/Controllers/Sales` | Sales Order foundation |
| `app/Controllers/Purchase` | Purchase Order foundation |
| `app/Controllers/Ai` | AI/OCR document upload, process, review, and conversion controllers |
| `app/Services` | Tenant, menu, audit, OCR, extraction, sales/purchase business services |
| `app/Views/layouts` | Main and auth layout foundation |
| `app/Views/partials` | Header, topbar, sidebar, footer |
| `public/assets/skote` | Extracted Skote assets |
| `docs` | Existing documentation set |

## 4. Routes Audit

`app/Config/Routes.php` already contains:

- `/` home route.
- CodeIgniter Shield auth routes through `service('auth')->routes($routes)`.
- Session-protected ERP route group.
- Dashboard route.
- Tenant switch route.
- Admin user and role routes.
- Sales order routes.
- Purchase order routes.
- Setup master data routes.
- Audit log routes.
- AI/OCR diagnostic, sample, document upload, process, review, and conversion routes.

### Route Risk

Most ERP routes are currently protected by the `session` filter. This ensures login is required, but it does not guarantee route-level permission enforcement for every route.

### Recommendation

Add permission-aware route filters progressively, for example:

- `dashboard.view` for dashboard.
- `users.view` and `users.manage` for admin user routes.
- `setup.master.view` and `setup.master.manage` for setup CRUD.
- `sales.order.view` and `sales.order.create` for sales order.
- `purchase.po.view` and `purchase.po.create` for purchase order.
- `ai.document.upload`, `ai.document.review`, and `ai.document.convert` for AI/OCR routes.

## 5. Authentication and Authorization Audit

`app/Config/AuthGroups.php` already defines the required ERP roles:

- Super Admin
- Company Admin
- Finance
- Sales
- Purchase
- Inventory
- Production
- Viewer

The permission list already covers the major ERP areas:

- Dashboard
- Setup/master
- User management
- Sales
- Purchase
- Inventory
- Finance
- Production
- POS
- Planning
- Costing
- Cash bank
- Fixed asset
- AI document workflow
- Audit logs

### Auth Risk

The role-permission matrix is already strong, but route and controller checks must remain consistent. The application should not rely only on sidebar visibility to secure features.

## 6. Multi-Company and Multi-Site Audit

`App\Services\TenantContext` already handles:

- Active company ID.
- Active site ID.
- Switch company/site.
- Accessible companies.
- Accessible sites.
- User access check for company.
- User access check for site.
- Bootstrap default active tenant for a user.

### Tenant Risk

Tenant filtering exists, but some controllers manually repeat tenant filtering logic. Repetition increases the risk of missing tenant isolation in future modules.

### Recommendation

Introduce reusable tenant scoping helpers so controllers, services, and repositories can consistently apply company/site filters.

## 7. Master Data Audit

The generic setup/master controller already supports many important master resources:

- Company
- Site / branch
- Department
- Warehouse
- Location
- Country
- Province
- City
- Postal code
- Currency
- UoM
- UoM conversion
- VAT
- WHT
- Item VAT
- Address master
- Customer
- Supplier
- Item
- Transaction code
- Prefix code

### Master Data Risk

A generic controller is efficient for early development, but enterprise modules need stronger domain-specific validation and service logic.

### Recommendation

Keep the generic controller for simple lookup data. Gradually extract complex masters into dedicated controllers/services:

- Customer
- Supplier
- Item
- Warehouse
- Tax
- Payment terms

## 8. Sales Module Audit

Sales Order foundation exists with:

- List page.
- Create page.
- Store action.
- Detail page.
- Service-based creation.
- Tenant filtering.

### Missing Sales Scope

The full requested flow is not complete yet:

```text
Customer Order -> Sales Order -> Delivery Order -> Sales Invoice
```

Currently, Sales Order is the most visible implemented transaction foundation.

## 9. Purchase Module Audit

Purchase routes exist for Purchase Order list/create/store/detail.

### Missing Purchase Scope

The full requested flow is not complete yet:

```text
Purchase Order -> Goods Receipt -> Vendor Invoice
```

Purchase receiving and vendor invoice need to be implemented in later stages.

## 10. Inventory Module Audit

Inventory foundation is present through item master and inventory movement table documentation, but operational screens and posting logic still need to be expanded.

Required next inventory scope:

- Warehouse stock balance.
- Inventory movement header/line posting.
- Stock adjustment.
- Goods receipt posting to stock.
- Delivery order posting to stock.
- Item transaction history.

## 11. Finance Module Audit

Finance permission and menu foundations exist. Database documentation mentions invoice foundation, but full accounting workflow is not complete.

Recommended first finance scope:

- Chart of account.
- Journal header/line.
- Sales invoice status.
- Vendor invoice status.
- AR/AP aging foundation.
- Payment header/line.
- Posting status.

## 12. AI/OCR Module Audit

AI/OCR module already has an advanced foundation:

- Upload document.
- Store secure file metadata.
- Process OCR.
- Store OCR result.
- Run AI/rule-based extraction.
- Store extraction JSON and line items.
- Review extraction.
- Save corrected review.
- Convert reviewed document to PO/SO.
- Store processing logs.
- Audit important events.

### AI/OCR Risk

The foundation is synchronous and still needs production hardening:

- Queue-based processing.
- Retry policy.
- Provider-specific adapters.
- Configurable document mapping.
- More granular review fields.
- Better duplicate detection workflow.
- Provider cost/error logging.

## 13. Skote Layout Audit

The layout is already CodeIgniter view based and includes:

- `app/Views/layouts/main.php`
- `app/Views/layouts/auth.php`
- `app/Views/partials/header.php`
- `app/Views/partials/topbar.php`
- `app/Views/partials/sidebar.php`
- `app/Views/partials/footer.php`

Sidebar already renders a dynamic menu tree from `MenuService`.

## 14. Technical Risks

| Risk | Severity | Recommendation |
|---|---:|---|
| Route protection mostly uses login session only | High | Add permission filters per route group |
| Tenant filtering repeated manually | High | Add reusable tenant scoping helper/service |
| Some controllers still parse business data directly | Medium | Move business rules into services |
| Generic master controller may grow too large | Medium | Extract complex masters gradually |
| AI/OCR processing still synchronous | Medium | Add queue/worker abstraction |
| Finance/accounting is still foundation only | Medium | Build finance in small controlled increments |
| Transaction numbering needs stronger standardization | Medium | Centralize document number service |

## 15. Recommended Continuation Order

1. Add audit and continuation plan documentation.
2. Add reusable tenant scope helper.
3. Add route permission enforcement pattern.
4. Add database blueprint for missing operational modules.
5. Add migrations for missing finance/inventory/approval tables.
6. Expand Sales Order into Delivery Order and Sales Invoice.
7. Expand Purchase Order into Goods Receipt and Vendor Invoice.
8. Add warehouse stock posting service.
9. Harden AI/OCR with queue, review fields, and mapping.
10. Add dashboard metrics from real tables.

## 16. Audit Conclusion

PENA ERP already has a correct enterprise foundation. The next development should continue from the current structure with small, safe, migration-based increments.

The repository should not be reset, regenerated, or replaced.
