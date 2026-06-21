# PO UAT Feedback Fixes

Tanggal: 2026-06-21

## Fixed

1. PO cancelled sekarang bisa diaktifkan kembali ke draft melalui tombol Activate.
2. PO edit sekarang menjaga item code lama agar tidak blank ketika dropdown item tidak menemukan master.
3. PO header sekarang memakai VAT Code dan WHT Code.
4. VAT Amt dan WHT Amt header dihapus dari screen input PO.

## Files

- app/Services/Purchase/PurchaseOrderService.php
- app/Controllers/Purchase/PurchaseOrderController.php
- app/Config/Routes.php
- app/Filters/PermissionGuardFilter.php
- app/Models/PurchaseOrderModel.php
- app/Views/purchase/orders/form.php
- app/Views/purchase/orders/show.php
- database/hosting/2026-06-21_update_po_uat_feedback.sql
- app/Database/Migrations/2026-06-21-004700_AddPoVatWhtCodes.php

## Required SQL

Run after database backup:

```text
database/hosting/2026-06-21_update_po_uat_feedback.sql
```

## Pending from same UAT feedback

1. Sales Order edit button.
2. Sales Order header: Remarks, Discount Amt, Freight, Other Amount.
3. Sales Order detail: Disc %, Disc Amt, Freight, Special Charge, Other Amt, Description.
4. Import BOM.
5. Import Work Center.
6. Import Routing.
7. Import Work Order.

## UAT

| Test | Expected | Result |
|---|---|---|
| Activate cancelled PO | PO returns to draft | NOT TESTED |
| Activate non-cancelled PO | Rejected | NOT TESTED |
| Edit draft PO with existing item | Item code remains visible and saved | NOT TESTED |
| Edit PO when item is not in dropdown | Existing code remains as fallback | NOT TESTED |
| PO header screen | VAT Code and WHT Code visible | NOT TESTED |
| PO header screen | VAT Amt and WHT Amt inputs hidden | NOT TESTED |
