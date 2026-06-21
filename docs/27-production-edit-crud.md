# Production Edit CRUD

Tanggal: 2026-06-21

## Scope

Patch ini menambahkan fitur edit untuk:

1. BOM
2. Work Center
3. Routing
4. Work Order

## Routes

| Module | Edit | Update |
|---|---|---|
| BOM | `GET /production/boms/{id}/edit` | `POST /production/boms/{id}` |
| Work Center | `GET /production/work-centers/{id}/edit` | `POST /production/work-centers/{id}` |
| Routing | `GET /production/routings/{id}/edit` | `POST /production/routings/{id}` |
| Work Order | `GET /production/work-orders/{id}/edit` | `POST /production/work-orders/{id}` |

## Files

- `app/Controllers/Production/ProductionEditController.php`
- `app/Config/Routes.php`
- `app/Views/production/boms/form.php`
- `app/Views/production/work_centers/form.php`
- `app/Views/production/routings/form.php`
- `app/Views/production/work_orders/form.php`
- `app/Views/production/boms/show.php`
- `app/Views/production/work_centers/show.php`
- `app/Views/production/routings/show.php`
- `app/Views/production/work_orders/show.php`

## Behavior

| Module | Behavior |
|---|---|
| BOM | Update header and replace component lines |
| Work Center | Update header and replace machine/cost lines |
| Routing | Update header and replace operation lines |
| Work Order | Only draft WO can be edited; update header and replace component/routing lines |

## Notes

- No SQL is required.
- Edit URLs are routed before generic detail routes to avoid route collision.
- Work Order edit is blocked if status is not `draft`.
- Update actions are POST and remain protected by production manage permission.

## UAT Checklist

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Open BOM detail | Edit button appears | NOT TESTED |
| 2 | Edit BOM header and component line | Data saved and detail updated | NOT TESTED |
| 3 | Open Work Center detail | Edit button appears | NOT TESTED |
| 4 | Edit Work Center machine/cost | Data saved and detail updated | NOT TESTED |
| 5 | Open Routing detail | Edit button appears | NOT TESTED |
| 6 | Edit Routing operation line | Data saved and detail updated | NOT TESTED |
| 7 | Open draft Work Order detail | Edit button appears | NOT TESTED |
| 8 | Edit draft Work Order header/component/routing | Data saved and detail updated | NOT TESTED |
| 9 | Open non-draft Work Order edit URL | Rejected | NOT TESTED |
