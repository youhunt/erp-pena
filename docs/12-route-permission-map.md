# Route Permission Map

Date: 2026-06-03

This document maps current and planned PENA ERP routes to CodeIgniter Shield permissions.

## 1. Purpose

The dynamic sidebar controls menu visibility, but sidebar visibility is not enough for security. Every protected route must also be guarded by authentication and permission checks.

Current routes are already inside a `session` protected group. This document defines the next hardening step: adding permission checks progressively without breaking existing functionality.

## 2. Permission Strategy

Use three layers:

1. `session` filter for authentication.
2. Shield permission checks for route/controller access.
3. Tenant access checks for company/site data isolation.

## 3. Current Route Map

| Route Pattern | Controller | Required Permission |
|---|---|---|
| `dashboard` | `DashboardController::index` | `dashboard.view` |
| `tenant/switch` | `TenantController::switch` | authenticated user with company/site access |
| `modules/(:segment)` | `ModulePlaceholderController::show` | related module view permission |
| `audit-logs` | `AuditLogController::index` | `audit.logs.view` |
| `audit-logs/(:num)` | `AuditLogController::show` | `audit.logs.view` |
| `admin/users` | `Admin\UserController::index` | `users.view` |
| `admin/users/new` | `Admin\UserController::create` | `users.manage` |
| `admin/users` POST | `Admin\UserController::store` | `users.manage` |
| `admin/users/(:num)/edit` | `Admin\UserController::edit` | `users.manage` |
| `admin/users/(:num)` POST | `Admin\UserController::update` | `users.manage` |
| `admin/users/(:num)/toggle` | `Admin\UserController::toggle` | `users.manage` |
| `admin/roles` | `Admin\RoleController::index` | `users.view` |
| `setup/*` GET index | `Setup\MasterDataController::index` | resource view permission |
| `setup/*/new` | `Setup\MasterDataController::create` | resource manage permission |
| `setup/*` POST store | `Setup\MasterDataController::store` | resource manage permission |
| `setup/*/edit` | `Setup\MasterDataController::edit` | resource manage permission |
| `setup/*` POST update | `Setup\MasterDataController::update` | resource manage permission |
| `setup/*/delete` POST | `Setup\MasterDataController::delete` | resource manage permission |
| `sales/orders` | `Sales\SalesOrderController::index` | `sales.order.view` |
| `sales/orders/new` | `Sales\SalesOrderController::create` | `sales.order.create` |
| `sales/orders` POST | `Sales\SalesOrderController::store` | `sales.order.create` |
| `sales/orders/(:num)` | `Sales\SalesOrderController::show` | `sales.order.view` |
| `purchase/orders` | `Purchase\PurchaseOrderController::index` | `purchase.po.view` |
| `purchase/orders/new` | `Purchase\PurchaseOrderController::create` | `purchase.po.create` |
| `purchase/orders` POST | `Purchase\PurchaseOrderController::store` | `purchase.po.create` |
| `purchase/orders/(:num)` | `Purchase\PurchaseOrderController::show` | `purchase.po.view` |
| `ai-documents` | `Ai\DocumentController::index` | `ai.document.review` or `ai.document.upload` |
| `ai-documents/upload` | `Ai\DocumentController::upload` | `ai.document.upload` |
| `ai-documents/upload` POST | `Ai\DocumentController::store` | `ai.document.upload` |
| `ai-documents/(:num)` | `Ai\DocumentController::show` | `ai.document.review` |
| `ai-documents/(:num)/process` POST | `Ai\DocumentController::process` | `ai.document.review` |
| `ai-documents/(:num)/review` | `Ai\DocumentController::review` | `ai.document.review` |
| `ai-documents/(:num)/review` POST | `Ai\DocumentController::saveReview` | `ai.document.review` |
| `ai-documents/(:num)/convert-po` POST | `Ai\DocumentController::convertToPo` | `ai.document.convert` |
| `ai-documents/(:num)/convert-so` POST | `Ai\DocumentController::convertToSo` | `ai.document.convert` |
| `ai-ocr/diagnostics` | `Ai\OcrDiagnosticsController::index` | `ai.document.review` |
| `ai-ocr/samples/*` | `Ai\SampleDocumentController` | `ai.document.review` |

## 4. Setup Resource Permission Map

| Resource | View Permission | Manage Permission |
|---|---|---|
| transaction-codes | `setup.master.view` | `setup.master.manage` |
| prefix-codes | `setup.master.view` | `setup.master.manage` |
| companies | `company.view` when added, fallback `setup.master.view` | `company.update` when added, fallback `setup.master.manage` |
| sites | `company.view` when added, fallback `setup.master.view` | `company.update` when added, fallback `setup.master.manage` |
| departments | `setup.master.view` | `setup.master.manage` |
| warehouses | `setup.master.view` | `setup.master.manage` |
| locations | `setup.master.view` | `setup.master.manage` |
| countries | `setup.master.view` | `setup.master.manage` |
| provinces | `setup.master.view` | `setup.master.manage` |
| cities | `setup.master.view` | `setup.master.manage` |
| postal-codes | `setup.master.view` | `setup.master.manage` |
| currencies | `setup.master.view` | `setup.master.manage` |
| uoms | `setup.master.view` | `setup.master.manage` |
| uom-conversions | `setup.master.view` | `setup.master.manage` |
| vat | `setup.master.view` | `setup.master.manage` |
| wht | `setup.master.view` | `setup.master.manage` |
| item-vat | `setup.master.view` | `setup.master.manage` |
| address-master | `setup.master.view` | `setup.master.manage` |
| customers | `sales.customer.view` | `sales.customer.manage` |
| suppliers | `purchase.supplier.view` | `purchase.supplier.manage` |
| items | `inventory.item.view` | `inventory.item.manage` |

## 5. Recommended Implementation Pattern

### Route Group Example

```php
$routes->group('sales', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('orders', 'Sales\SalesOrderController::index', ['filter' => 'permission:sales.order.view']);
    $routes->get('orders/new', 'Sales\SalesOrderController::create', ['filter' => 'permission:sales.order.create']);
    $routes->post('orders', 'Sales\SalesOrderController::store', ['filter' => 'permission:sales.order.create']);
    $routes->get('orders/(:num)', 'Sales\SalesOrderController::show/$1', ['filter' => 'permission:sales.order.view']);
});
```

### Controller Guard Example

Use controller-level checks for generic controllers whose permission depends on the resource name.

```php
if (! auth()->user()->can($permission)) {
    return redirect()->to('/dashboard')->with('error', 'You do not have permission to access this page.');
}
```

## 6. Implementation Priority

1. Add permission checks to Admin routes.
2. Add permission checks to Sales and Purchase routes.
3. Add controller-level permission checks to generic Setup resources.
4. Add permission checks to AI/OCR routes.
5. Add permission checks to Audit logs.
6. Add permission checks to future Inventory and Finance modules.

## 7. Testing Checklist

- Login as Super Admin: all routes should work.
- Login as Viewer: create/update/delete routes should be blocked.
- Login as Sales: sales routes should work, purchase manage routes should be blocked.
- Login as Purchase: purchase routes should work, sales create route should be blocked unless granted.
- Login as Inventory: item and stock routes should work, finance posting should be blocked.
- Confirm hidden menu does not imply security; direct URL access must also be blocked.

## 8. Notes

If the project does not yet register a custom `permission` filter alias, add it in `app/Config/Filters.php` according to CodeIgniter Shield authorization filter support or a small project-specific filter wrapper.
