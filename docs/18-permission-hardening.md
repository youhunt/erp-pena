# ERP Route Permission Hardening

Tanggal update: 2026-06-20

Dokumen ini mencatat hardening permission untuk ERP core routes. Tujuannya memastikan URL langsung tidak bisa dipakai untuk bypass sidebar/menu visibility.

---

## 1. Prinsip Security

Sidebar visibility bukan security. Setiap URL transaksi penting tetap harus dijaga oleh permission guard.

Rule umum:

| Area | View Permission | Action/Post Permission |
|---|---|---|
| Dashboard | `dashboard.view` | - |
| Setup/Master | `setup.master.view` | `setup.master.manage` |
| Sales Order / Delivery | `sales.order.view` | `sales.order.create` / `sales.order.approve` |
| Purchase Order / Receipt | `purchase.po.view` | `purchase.po.create` / `purchase.po.approve` |
| AR Invoice / Receipt | `finance.ar.view` | `finance.ar.manage` |
| AP Invoice / Payment | `finance.ap.view` | `finance.ap.manage` |
| Inventory Stock | `inventory.stock.view` | `inventory.movement.post` |
| GL | `finance.gl.view` | `finance.gl.post` |
| Cash/Bank | `cashbank.view` | `cashbank.manage` |
| AI/OCR | `ai.document.upload/review/convert` | Based on action |
| Audit Logs | `audit.logs.view` | - |
| System Import | `setup.master.view` | `setup.master.manage` |

---

## 2. Route Permission Changes

Files updated:

```text
app/Filters/PermissionGuardFilter.php
app/Config/Filters.php
```

### Sales Routes

| Route Pattern | Permission |
|---|---|
| `sales/orders` GET | `sales.order.view` |
| `sales/orders/new` | `sales.order.create` |
| `sales/orders` POST | `sales.order.create` |
| `sales/orders/{id}/submit` | `sales.order.create` |
| `sales/orders/{id}/approve` | `sales.order.approve` |
| `sales/orders/{id}/reserve` | `sales.order.create` |
| `sales/orders/{id}/cancel` | `sales.order.create` |
| `sales/orders/{id}/allocate` | `sales.order.create` |
| `sales/orders/{id}/deliver` | `sales.order.create` |
| `sales/deliveries/{id}/reverse` | `sales.order.create` |
| `sales/deliveries/{id}/invoice` GET | `finance.ar.view` |
| `sales/deliveries/{id}/invoice` POST | `finance.ar.manage` |

### Purchase Routes

| Route Pattern | Permission |
|---|---|
| `purchase/orders` GET | `purchase.po.view` |
| `purchase/orders/new` | `purchase.po.create` |
| `purchase/orders` POST | `purchase.po.create` |
| `purchase/orders/{id}/submit` | `purchase.po.create` |
| `purchase/orders/{id}/approve` | `purchase.po.approve` |
| `purchase/orders/{id}/close` | `purchase.po.create` |
| `purchase/orders/{id}/cancel` | `purchase.po.create` |
| `purchase/orders/{id}/receive` | `purchase.po.create` |
| `purchase/receipts/{id}/reverse` | `purchase.po.create` |
| `purchase/receipts/{id}/invoice` GET | `finance.ap.view` |
| `purchase/receipts/{id}/invoice` POST | `finance.ap.manage` |

### Inventory Routes

| Route Pattern | Permission |
|---|---|
| Stock balance/card/alerts GET | `inventory.stock.view` |
| Adjustment, transfer new, stock opname, in-out | `inventory.movement.post` |
| Any POST under `inventory/*` | `inventory.movement.post` |

### System Routes

System routes are now included in both tenant and permission filters.

| Route Pattern | Permission |
|---|---|
| `system/development-status` | `dashboard.view` |
| `system/data-import` GET | `setup.master.view` |
| `system/data-import` import/commit/POST | `setup.master.manage` |
| `system/excel-transfer` GET | `setup.master.view` |
| `system/excel-transfer` import/commit/POST | `setup.master.manage` |
| Other `system/*` | `users.manage` |

---

## 3. Filters Applied

`system/*` is now added to:

- Tenant bootstrap filter
- Permission guard filter

This ensures active company/site context and permission check apply to system import/status routes.

---

## 4. UAT Checklist

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Login viewer and open `sales/orders` | Can view list | NOT TESTED |
| 2 | Login viewer and POST submit SO URL directly | 404/denied | NOT TESTED |
| 3 | Login sales and approve SO URL directly | 404/denied unless has approve permission | NOT TESTED |
| 4 | Login purchase and approve PO URL directly | 404/denied unless has approve permission | NOT TESTED |
| 5 | Login sales and create AR invoice from DO | 404/denied unless has AR manage | NOT TESTED |
| 6 | Login purchase and create AP invoice from receipt | 404/denied unless has AP manage | NOT TESTED |
| 7 | Login viewer and open system import commit URL | 404/denied | NOT TESTED |
| 8 | Login inventory and open stock card | Can view stock card | NOT TESTED |
| 9 | Login inventory and POST stock adjustment | Allowed if has `inventory.movement.post` | NOT TESTED |
| 10 | Superadmin access all routes | Allowed | NOT TESTED |

---

## 5. Known Notes

- Current app throws 404 for denied permission to avoid exposing route existence.
- Some cross-module actions intentionally require finance permission, e.g. creating invoice from delivery/receipt.
- Permission still needs real role UAT with actual non-admin users.
