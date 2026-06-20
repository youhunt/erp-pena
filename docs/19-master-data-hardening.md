# Core Master Data Hardening

Tanggal update: 2026-06-20

Dokumen ini mencatat hardening master data inti yang dipakai oleh transaksi ERP.

Target master data:

- Customer
- Supplier
- Item
- Warehouse
- Location

Tujuan utama: transaksi SO/PO/Receipt/Delivery/Invoice tidak mudah gagal karena field legacy dan field standar tidak sinkron.

---

## 1. Problem yang Dicegah

| Problem | Dampak | Solusi |
|---|---|---|
| Customer hanya punya `customer/customern`, tetapi transaksi membaca `code/name` | SO customer name kosong atau lookup gagal | Model callback sync `customer/customern` ke `code/name` |
| Supplier hanya punya `supplier/supplierna`, tetapi transaksi membaca `code/name` | PO supplier name kosong atau lookup gagal | Model callback sync `supplier/supplierna` ke `code/name` |
| Item hanya punya `item_code/item_name`, tetapi transaksi membaca `code/name` | SO/PO item auto-fill gagal | Model callback sync `item_code/item_name` ke `code/name` |
| UoM item kosong sebagian | Receipt/delivery line UoM kosong | `purchaseuom` dan `sellinguom` fallback dari `stockuom` |
| Price item tidak konsisten | Sales/purchase price auto-fill kosong | Price fallback antar `item_price`, `purchasep`, `sellingprice` |
| Code berisi huruf kecil/spasi | Duplicate atau lookup tidak stabil | Code di-trim dan uppercase |
| Active flag beda (`active` vs `is_active`) | Data tidak muncul di dropdown | Active flag disinkronkan |
| Data lama sudah terlanjur tidak sinkron | Callback hanya berlaku untuk data baru/update | Disediakan SQL backfill hosting |

---

## 2. Files Updated

| File | Purpose |
|---|---|
| `app/Models/CustomerModel.php` | Normalize customer aliases before insert/update |
| `app/Models/SupplierModel.php` | Normalize supplier aliases before insert/update |
| `app/Models/ItemModel.php` | Normalize item code/name/UoM/price aliases before insert/update |
| `app/Models/WarehouseModel.php` | Normalize warehouse code/name/active before insert/update |
| `app/Models/LocationModel.php` | Normalize location code/name/active before insert/update |
| `database/hosting/2026-06-20_normalize_core_master_data.sql` | Backfill existing hosting data |

---

## 3. Normalization Rules

### Customer

| Field | Rule |
|---|---|
| `customer` | Trim and uppercase; fallback from `code` |
| `code` | Trim and uppercase; fallback from `customer` |
| `customern` | Trim; fallback from `name` |
| `name` | Trim; fallback from `customern` |
| `terms_code` | Fallback from `terms` |
| `tax_number` | Fallback from `taxnumber` |
| `address` | Fallback from `officeaddre` |
| `phone` | Fallback from `officephon` |
| `is_active` | Fallback from `active`, default 1 |

### Supplier

| Field | Rule |
|---|---|
| `supplier` | Trim and uppercase; fallback from `code` |
| `code` | Trim and uppercase; fallback from `supplier` |
| `supplierna` | Trim; fallback from `name` |
| `name` | Trim; fallback from `supplierna` |
| `terms_code` | Fallback from `terms` |
| `tax_number` | Fallback from `taxnumber` |
| `address` | Fallback from `officeaddre` |
| `phone` | Fallback from `officephon` |
| `is_active` | Fallback from `active`, default 1 |

### Item

| Field | Rule |
|---|---|
| `item_code` | Trim and uppercase; fallback from `code/item_coded` |
| `code` | Trim and uppercase; fallback from `item_code/item_coded` |
| `item_name` | Trim; fallback from `name/item_named` |
| `name` | Trim; fallback from `item_name/item_named` |
| `stockuom` | Trim and uppercase |
| `purchaseuom` | Fallback from `stockuom` |
| `sellinguom` | Fallback from `stockuom` |
| `item_price` | Fallback from `sellingprice` or `purchasep` |
| `purchasep` | Fallback from `item_price` |
| `sellingprice` | Fallback from `item_price` |
| `is_active` | Fallback from `active`, default 1 |

### Warehouse / Location

| Field | Rule |
|---|---|
| `code` | Trim and uppercase |
| `name` | Trim |
| `is_active` | Default 1 |

---

## 4. Hosting SQL

Run this file after backup database:

```text
database/hosting/2026-06-20_normalize_core_master_data.sql
```

This SQL normalizes existing rows and prints verification counts for missing code/name/uom.

---

## 5. UAT Checklist

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Create customer with legacy fields | `customer`, `customern`, `code`, `name` all filled | NOT TESTED |
| 2 | Create supplier with legacy fields | `supplier`, `supplierna`, `code`, `name` all filled | NOT TESTED |
| 3 | Create item with item_code/item_name | `code`, `name`, `item_code`, `item_name` all filled | NOT TESTED |
| 4 | Create item with stockuom only | purchaseuom and sellinguom fallback from stockuom | NOT TESTED |
| 5 | Create item with item_price only | purchase/selling price fallback correctly | NOT TESTED |
| 6 | Create warehouse lowercase code | Saved code becomes uppercase | NOT TESTED |
| 7 | Create location lowercase code | Saved code becomes uppercase | NOT TESTED |
| 8 | Run hosting SQL | Verification missing code/name/uom counts reduce to 0 or known exceptions | NOT TESTED |
| 9 | Create SO after normalization | Customer/item auto-fill still works | NOT TESTED |
| 10 | Create PO after normalization | Supplier/item auto-fill still works | NOT TESTED |

---

## 6. Status

| Area | Status | Notes |
|---|---|---|
| Customer normalization | Patched | Model callback + SQL backfill |
| Supplier normalization | Patched | Model callback + SQL backfill |
| Item normalization | Patched | Model callback + SQL backfill |
| Warehouse normalization | Patched | Model callback + SQL backfill |
| Location normalization | Patched | Model callback + SQL backfill |
| Duplicate detection | Pending | To be hardened after UAT data pattern is clear |
| Automated tests | Pending | Manual UAT first |
