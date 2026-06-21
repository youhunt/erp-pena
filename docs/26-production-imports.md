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

## Routes

| Import | Upload Form | Template |
|---|---|---|
| BOM | `/production/imports/boms` | `/production/imports/boms/template` |
| Work Center | `/production/imports/work-centers` | `/production/imports/work-centers/template` |
| Routing | `/production/imports/routings` | `/production/imports/routings/template` |
| Work Order | `/production/imports/work-orders` | `/production/imports/work-orders/template` |

---

## Files Added / Updated

| File | Purpose |
|---|---|
| `app/Controllers/Production/ProductionImportController.php` | Import handler and template generator |
| `app/Views/production/imports/form.php` | Upload form |
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
site_code, department_code, warehouse_code, work_center_code, description, machine_code, notes, speed, capacity_percent, max_length, length_uom, max_width, width_uom, max_height, height_uom, max_volume, volume_uom, qty_labor, working_hour, cost_type, cost_amount, cost_uom, active_date, inactive_date
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

---

## SQL

No new table or column is added by this patch. No SQL is required for this import feature, assuming Production tables already exist.

---

## UAT Checklist

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Open BOM list | Import button appears | NOT TESTED |
| 2 | Download BOM template | XLSX downloaded | NOT TESTED |
| 3 | Import valid BOM file | BOM header and lines created/updated | NOT TESTED |
| 4 | Import BOM without line_no | Rejected | NOT TESTED |
| 5 | Open Work Center list | Import button appears | NOT TESTED |
| 6 | Download Work Center template | XLSX downloaded | NOT TESTED |
| 7 | Import valid Work Center file | Work Center created/updated | NOT TESTED |
| 8 | Open Routing list | Import button appears | NOT TESTED |
| 9 | Import Routing without line_no | Rejected | NOT TESTED |
| 10 | Import valid Routing file | Routing header and lines created/updated | NOT TESTED |
| 11 | Open Work Order list | Import button appears | NOT TESTED |
| 12 | Import Work Order without line_no | Rejected | NOT TESTED |
| 13 | Import valid Work Order file | WO draft and component/routing lines created/updated | NOT TESTED |
| 14 | Import update existing non-draft WO | Rejected | NOT TESTED |

---

## Status

| Area | Status |
|---|---|
| BOM import | Added |
| Work Center import | Added |
| Routing import | Added |
| Work Order import | Added |
| Template download | Added |
| Upload form | Added |
| SQL | Not required |
| UAT | Pending |
