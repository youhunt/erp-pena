# Sales Order UAT Feedback Edit Patch

Tanggal: 2026-06-21

## Fixed

1. Sales Order detail sekarang memiliki tombol Edit untuk SO draft.
2. Route edit/update Sales Order ditambahkan.
3. Service layer menolak edit SO jika status bukan draft.
4. Service layer menolak edit SO jika line sudah reserved atau delivered.
5. Header Sales Order menampung Remarks, Discount Amt, Freight, dan Other Amount.
6. Detail Sales Order menampung Description, Disc %, Disc Amt, Freight, Special Charge, dan Other Amt.

## Files

- app/Models/SalesOrderModel.php
- app/Models/SalesOrderLineModel.php
- app/Services/Sales/SalesOrderService.php
- app/Controllers/Sales/SalesOrderController.php
- app/Views/sales/orders/form.php
- app/Views/sales/orders/show.php
- app/Config/Routes.php
- database/hosting/2026-06-21_update_sales_order_uat_feedback.sql
- app/Database/Migrations/2026-06-21-005300_AddSalesOrderUatFields.php

## Required SQL

Run after database backup:

```text
database/hosting/2026-06-21_update_sales_order_uat_feedback.sql
```

## UAT

| Test | Expected | Result |
|---|---|---|
| Open SO draft detail | Edit button appears | NOT TESTED |
| Open SO submitted/approved detail | Edit button hidden | NOT TESTED |
| Direct open edit URL for non-draft SO | Rejected | NOT TESTED |
| Edit SO draft with requested header fields | Saved | NOT TESTED |
| Edit SO draft with requested detail fields | Saved | NOT TESTED |
| Save SO line without item code | Rejected | NOT TESTED |
| Edit SO after reserved/delivered line | Rejected | NOT TESTED |

## Pending from same UAT feedback

1. Import BOM.
2. Import Work Center.
3. Import Routing.
4. Import Work Order.
