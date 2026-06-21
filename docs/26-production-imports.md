# Production Imports - BOM, Work Center, Routing, Work Order

Tanggal: 2026-06-21

## Scope

Patch ini menambahkan import Excel/CSV untuk module Production:

1. BOM
2. Work Center
3. Routing
4. Work Order

Semua template memiliki `site_code`. BOM, Routing, dan Work Order wajib memiliki `line_no`. Work Center tidak memakai `line_no`.

---

## Standard Flow

Flow import sekarang mengikuti standar PO/SO/Data Import:

```text
Download Template -> Upload File -> Preview & Validate -> Commit Import
```

Data belum masuk database saat upload. Data baru disimpan setelah preview valid dan user klik **Commit Import**.

---

## Routes

| Import | Upload Form | Template | Preview/Commit |
|---|---|---|---|
| BOM | `/production/imports/boms` | `/production/imports/boms/template` | `POST /production/imports/boms` |
| Work Center | `/production/imports/work-centers` | `/production/imports/work-centers/template` | `POST /production/imports/work-centers` |
| Routing | `/production/imports/routings` | `/production/imports/routings/template` | `POST /production/imports/routings` |
| Work Order | `/production/imports/work-orders` | `/production/imports/work-orders/template` | `POST /production/imports/work-orders` |

Catatan: endpoint POST yang sama dipakai untuk preview dan commit. Saat preview valid, sistem menyimpan data sementara di session dengan `commit_token`. Tombol Commit mengirim token tersebut.

---

## Files Added / Updated

| File | Purpose |
|---|---|
| `app/Controllers/Production/ProductionImportController.php` | Template, preview validation, commit import |
| `app/Views/production/imports/form.php` | Upload form dengan tombol Preview & Validate |
| `app/Views/production/imports/preview.php` | Preview hasil validasi dan tombol Commit Import |
| `app/Config/Routes.php` | Production import routes |
| `app/Views/production/boms/index.php` | Import button |
| `app/Views/production/work_centers/index.php` | Import button |
| `app/Views/production/routings/index.php` | Import button |
| `app/Views/production/work_orders/index.php` | Import button |

---

## Template Columns

### BOM

```text
site_code, department_code, warehouse_code, parent_item_code, bom_type, qty_batch, uom_code, ratio_percent, description, active_date, inactive_date, line_no, child_item_code, component_type, qty_used, line_uom_code, factor, line_description
```

### Work Center

```text
site_code, department_code, warehouse_code, work_center_code, description, machine_code, notes, speed, capacity_percent, cost_type, cost_amount, cost_uom, active_date, inactive_date
```

### Routing

```text
site_code, department_code, warehouse_code, item_code, description, line_no, routing_name, work_center_code, operation_type, hour_qty, hour_uom, std_speed, speed_uom, notes
```

### Work Order

```text
site_code, department_code, warehouse_code, work_center_code, wo_no, wo_date, parent_item_code, parent_item_name, wo_qty, uom_code, description, line_no, component_item_code, component_item_name, qty_used, line_uom_code, route_work_center_code, routing_name, hour_qty, route_uom
```

---

## Import Behavior

| Import | Key | Behavior |
|---|---|---|
| BOM | company + site_code + department + warehouse + parent item | Upsert header, replace BOM lines |
| Work Center | company + site_code + department + warehouse + work center | Upsert row |
| Routing | company + site_code + item_code | Upsert header, replace routing lines |
| Work Order | company + wo_no | Upsert only if existing WO is still draft, replace imported component/routing lines |

---

## Validation

- Active company is required.
- `site_code` must exist for active company.
- `line_no` is required for BOM, Routing, and Work Order.
- Duplicate `line_no` inside same BOM/Routing/Work Order is rejected.
- Work Order update is rejected if existing WO status is not `draft`.
- Missing item master is shown as warning; import can still continue with fallback item name/code.
- Commit button disabled when preview still has error.

---

## SQL

No new table or column is added by this patch. No SQL is required for this import feature, assuming Production tables already exist.

---

## UAT Checklist

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Open BOM list | Import button appears | NOT TESTED |
| 2 | Download BOM template | XLSX downloaded | NOT TESTED |
| 3 | Upload valid BOM file | Preview page appears with all rows valid | NOT TESTED |
| 4 | Commit valid BOM preview | BOM header and lines created/updated | NOT TESTED |
| 5 | Upload BOM without line_no | Preview shows error and Commit disabled | NOT TESTED |
| 6 | Open Work Center list | Import button appears | NOT TESTED |
| 7 | Download Work Center template | XLSX downloaded | NOT TESTED |
| 8 | Upload valid Work Center file | Preview page appears with all rows valid | NOT TESTED |
| 9 | Commit valid Work Center preview | Work Center created/updated | NOT TESTED |
| 10 | Open Routing list | Import button appears | NOT TESTED |
| 11 | Upload Routing without line_no | Preview shows error and Commit disabled | NOT TESTED |
| 12 | Commit valid Routing preview | Routing header and lines created/updated | NOT TESTED |
| 13 | Open Work Order list | Import button appears | NOT TESTED |
| 14 | Upload Work Order without line_no | Preview shows error and Commit disabled | NOT TESTED |
| 15 | Commit valid Work Order preview | WO draft and component/routing lines created/updated | NOT TESTED |
| 16 | Upload update for existing non-draft WO | Preview shows error and Commit disabled | NOT TESTED |

---

## Status

| Area | Status |
|---|---|
| BOM import | Added with preview/commit |
| Work Center import | Added with preview/commit |
| Routing import | Added with preview/commit |
| Work Order import | Added with preview/commit |
| Template download | Added |
| Upload form | Added |
| Preview validation | Added |
| Commit import | Added |
| SQL | Not required |
| UAT | Pending |
