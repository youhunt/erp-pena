# Core Master Data Code Guard

Tanggal update: 2026-06-20

Dokumen ini mencatat guard tambahan pada master data inti agar kode master tidak dobel dalam company/site yang sama.

Master yang dijaga:

- Customer
- Supplier
- Item
- Warehouse
- Location

---

## 1. Tujuan

Transaksi ERP seperti SO, PO, Receipt, Delivery, Invoice, Payment, Stock Card, dan GL membutuhkan master data yang stabil.

Jika kode customer/supplier/item/warehouse/location dobel dalam tenant yang sama, lookup transaksi bisa ambigu dan auto-fill bisa mengambil data yang salah.

---

## 2. Files Updated

| File | Guard |
|---|---|
| `app/Models/CustomerModel.php` | Customer code unique per company/site |
| `app/Models/SupplierModel.php` | Supplier code unique per company/site |
| `app/Models/ItemModel.php` | Item code unique per company/site |
| `app/Models/WarehouseModel.php` | Warehouse code unique per company/site |
| `app/Models/LocationModel.php` | Location code unique per company/site/warehouse |
| `database/hosting/2026-06-20_audit_core_master_codes.sql` | Audit existing repeated codes |

---

## 3. Guard Rule

| Master | Scope |
|---|---|
| Customer | `company_id + site_id + code` |
| Supplier | `company_id + site_id + code` |
| Item | `company_id + site_id + code` |
| Warehouse | `company_id + site_id + code` |
| Location | `company_id + site_id + warehouse_id + code` |

Deleted rows are ignored by the guard.

---

## 4. Existing Data Audit

Before strict UAT, run this SQL on hosting:

```text
database/hosting/2026-06-20_audit_core_master_codes.sql
```

This SQL only reports repeated codes and does not change data.

If any rows appear, decide which row is the valid master and soft-delete/merge the others before continuing UAT.

---

## 5. UAT Checklist

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Create customer with same code in same company/site | Rejected | NOT TESTED |
| 2 | Create supplier with same code in same company/site | Rejected | NOT TESTED |
| 3 | Create item with same code in same company/site | Rejected | NOT TESTED |
| 4 | Create warehouse with same code in same company/site | Rejected | NOT TESTED |
| 5 | Create location with same code in same warehouse | Rejected | NOT TESTED |
| 6 | Create same item code in different site if business allows | Allowed by site scope | NOT TESTED |
| 7 | Run audit SQL | Repeated code list visible if any | NOT TESTED |
| 8 | Edit existing master without changing code | Should not reject itself | NOT TESTED |

---

## 6. Status

| Area | Status | Notes |
|---|---|---|
| Customer code guard | Patched | Model callback |
| Supplier code guard | Patched | Model callback |
| Item code guard | Patched | Model callback |
| Warehouse code guard | Patched | Model callback |
| Location code guard | Patched | Model callback |
| Existing data audit SQL | Added | Manual review required |
| Database unique index | Pending | Add later after data is clean |
