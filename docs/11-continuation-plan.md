# PENA ERP Continuation Plan

Date: 2026-06-03

This document defines the safe continuation plan after repository audit. The goal is to continue the existing CodeIgniter 4 ERP foundation without regenerating the project.

## 1. Development Principle

The repository already contains a valid ERP foundation. Development must follow these principles:

- Continue from existing CodeIgniter 4 structure.
- Keep existing routes, controllers, models, migrations, and views unless there is a clear technical reason to refactor.
- Add new features through migration-based and service-based increments.
- Avoid large rewrites.
- Protect tenant data with company/site scoping.
- Protect routes with Shield permissions.
- Keep controllers thin.
- Put business logic in services.
- Use audit logs for important changes.

## 2. Target Architecture

```text
app/
  Config/
    AuthGroups.php
    Routes.php
  Controllers/
    Admin/
    Setup/
    Sales/
    Purchase/
    Inventory/
    Finance/
    Ai/
  Models/
  Services/
    TenantContext.php
    MenuService.php
    AuditLogService.php
    Support/
    Sales/
    Purchase/
    Inventory/
    Finance/
    Ai/
  Database/
    Migrations/
    Seeds/
  Views/
    layouts/
    partials/
    dashboard/
    setup/
    sales/
    purchase/
    inventory/
    finance/
    ai/
```

## 3. Service Layer Pattern

Controllers should only handle:

- Request validation.
- Calling service methods.
- Redirecting or rendering views.
- Flash messages.

Services should handle:

- Tenant validation.
- Business rules.
- Transaction number generation.
- Database transactions.
- Posting logic.
- Audit log creation.
- Document conversion logic.

## 4. Repository and Query Pattern

For now, CodeIgniter models can act as table repositories. Dedicated repository classes should only be introduced when a module has complex query logic.

Recommended pattern:

```php
$model = new SalesOrderModel();
(new TenantScope())->applyToModel($model);
$orders = $model->findAll();
```

For query builder:

```php
$builder = db_connect()->table('sales_orders');
(new TenantScope())->applyToBuilder($builder, 'sales_orders');
$rows = $builder->get()->getResultArray();
```

## 5. Tenant Isolation Rules

Tenant isolation is mandatory for all operational data.

### Global Tables

No `company_id` or `site_id` required:

- countries
- provinces
- cities
- postal_codes
- currencies, if used globally

### Company Tables

Require `company_id`:

- customers
- suppliers
- items
- uoms
- taxes
- payment terms
- chart of accounts

### Site Tables

Require `company_id` and `site_id`:

- departments
- warehouses
- locations
- sales orders
- purchase orders
- delivery orders
- goods receipts
- inventory movements
- invoices when site-specific

## 6. Permission Enforcement Rules

Sidebar visibility is not security. Each sensitive route and action must also check permissions.

Recommended mapping:

| Area | View Permission | Manage/Create Permission |
|---|---|---|
| Dashboard | `dashboard.view` | - |
| Users | `users.view` | `users.manage` |
| Setup Master | `setup.master.view` | `setup.master.manage` |
| Customer | `sales.customer.view` | `sales.customer.manage` |
| Sales Order | `sales.order.view` | `sales.order.create` |
| Supplier | `purchase.supplier.view` | `purchase.supplier.manage` |
| Purchase Order | `purchase.po.view` | `purchase.po.create` |
| Item | `inventory.item.view` | `inventory.item.manage` |
| Inventory Stock | `inventory.stock.view` | `inventory.movement.post` |
| Finance | `finance.gl.view` | `finance.gl.post` |
| AI Upload | `ai.document.upload` | - |
| AI Review | `ai.document.review` | - |
| AI Convert | `ai.document.convert` | - |
| Audit Logs | `audit.logs.view` | - |

## 7. Database Continuation Plan

### Phase A - Missing Setup Foundation

Add or verify:

- payment_terms
- numbering_sequences
- approval_workflows
- approval_steps
- approval_histories

### Phase B - Sales Completion

Add or verify:

- customer_orders
- customer_order_lines
- delivery_orders
- delivery_order_lines
- sales_invoices
- sales_invoice_lines

### Phase C - Purchase Completion

Add or verify:

- purchase_requests
- purchase_request_lines
- goods_receipts
- goods_receipt_lines
- vendor_invoices
- vendor_invoice_lines

### Phase D - Inventory Posting

Add or verify:

- warehouse_stocks
- stock_movements
- stock_movement_lines
- stock_adjustments
- stock_adjustment_lines

### Phase E - Finance Basic

Add or verify:

- chart_of_accounts
- journal_entries
- journal_entry_lines
- ar_transactions
- ap_transactions
- payments
- payment_allocations

### Phase F - AI/OCR Hardening

Add or verify:

- document_review_fields
- document_field_mappings
- document_type_mappings
- document_duplicate_checks
- document_provider_logs

## 8. Module Continuation Plan

### Step 1 - Hardening Foundation

- Add repository audit documentation.
- Add continuation plan.
- Add tenant scope helper.
- Add route permission checklist.

### Step 2 - Auth and Permission

- Add permission checks in routes/controllers.
- Ensure all admin actions use Shield permission names.
- Ensure menu permission and route permission are aligned.

### Step 3 - Sales Flow

Implement:

```text
Customer Order -> Sales Order -> Delivery Order -> Sales Invoice
```

Start with database and minimal CRUD before posting logic.

### Step 4 - Purchase Flow

Implement:

```text
Purchase Order -> Goods Receipt -> Vendor Invoice
```

Goods Receipt should post inventory movement.

### Step 5 - Inventory Flow

Implement stock ledger first, then balance table.

Rules:

- Never update stock without stock movement history.
- Use database transaction for every posting.
- Reference source module and source ID.

### Step 6 - Finance Basic

Implement finance in controlled scope:

- Invoice status.
- Posting status.
- Journal draft/post.
- AR/AP transaction records.
- Payment and allocation.

### Step 7 - AI/OCR Production Hardening

- Add queue-ready interface.
- Add provider adapters.
- Add extraction mapping by document type.
- Add review fields table.
- Add conversion validation.
- Add duplicate checking.

## 9. Testing Strategy

Minimum testing checklist for every phase:

- `composer install`
- `php spark migrate --all`
- `php spark db:seed PenaErpSeeder`
- `php spark serve`
- Login as default admin.
- Switch company/site.
- Confirm sidebar permissions.
- Create master data.
- Create transaction draft.
- Confirm tenant scoping.
- Confirm audit log entry.
- Confirm unauthorized users cannot access protected routes.

## 10. Git Strategy

Use small branches:

```bash
git checkout -b feature/audit-architecture-foundation
git checkout -b feature/tenant-permission-hardening
git checkout -b feature/sales-flow-foundation
git checkout -b feature/purchase-inventory-foundation
git checkout -b feature/ai-ocr-hardening
```

Suggested commit messages:

- `add repository audit documentation`
- `add erp continuation plan`
- `add tenant scope helper`
- `harden route permission checks`
- `add sales delivery invoice foundation`
- `add purchase receipt vendor invoice foundation`
- `add inventory stock posting foundation`
- `add ai ocr review field mapping`

## 11. Immediate Next Technical Tasks

1. Add `App\Services\Support\TenantScope`.
2. Refactor repeated tenant query filtering gradually.
3. Add documentation for route permission mapping.
4. Add missing database blueprint details for operational flows.
5. Continue with small migrations for payment terms, numbering sequences, approval workflow, and document review fields.
