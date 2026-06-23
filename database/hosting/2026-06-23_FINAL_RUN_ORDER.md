# Final Run Order - Master Data Hierarchy & Purchase Receipt Fixes

Jalankan setelah `git pull` di hosting.

```bash
git pull
php spark cache:clear
```

Kalau hosting memakai OPcache/PHP-FPM dan perubahan belum terbaca, restart PHP process dari cPanel / MultiPHP Manager.

---

## 1. Backup database dulu

Wajib backup database sebelum menjalankan SQL repair.

---

## 2. Jalankan SQL struktur dan scope master data

Urutan ini memastikan tabel lama punya kolom scope yang dibutuhkan.

```text
database/hosting/2026-06-23_harden_department_site_scope.sql
```

Fungsi:

- memastikan `departments.company_id` ada
- memastikan `departments.site_id` ada
- memastikan `warehouses.department_id` ada
- membuat index strict lookup `company_id + site_id + code`
- membuat department `GENERAL` per company/site jika dibutuhkan

---

## 3. Repair warehouse yang department-nya salah site

```text
database/hosting/2026-06-23_repair_warehouse_department_scope.sql
```

Fungsi:

- cek warehouse yang `department_id`-nya beda company/site
- remap ke department dengan code yang sama di site warehouse tersebut jika ada
- kalau tidak ada, pindahkan ke department `GENERAL` di site warehouse tersebut

---

## 4. Repair item-location hierarchy

```text
database/hosting/2026-06-23_repair_item_location_hierarchy.sql
```

Fungsi:

- backfill `item_locations.warehouse_id` dari `locations.warehouse_id`
- backfill `company_id/site_id` dari warehouse
- perbaiki item-location yang warehouse/location-nya tidak match
- backfill `item_code` dari item master

---

## 5. Repair PO received qty lama

```text
database/hosting/2026-06-23_repair_po_received_quantities.sql
```

Fungsi:

- hitung ulang `purchase_order_lines.qty_received`
- hitung ulang `purchase_order_lines.qty_outstanding`
- update `line_status`
- update status PO menjadi `partial_received` / `received`

---

## 6. Audit final master data

```text
database/hosting/2026-06-23_master_data_integrity_audit.sql
```

Expected result:

```text
Semua SELECT return 0 row
```

Kalau masih ada row, berarti masih ada data master lintas company/site/warehouse yang perlu diperbaiki manual.

---

## 7. Test wajib setelah patch

### Warehouse import

Test case gagal:

```text
company_code = PENA
site_code = JKT
department_code = kode department yang ada di site lain
```

Expected:

```text
Masuk error preview, bukan valid rows.
```

### Warehouse manual form

Pilih department dari site lain.

Expected:

```text
Save ditolak: Selected department does not belong to selected site.
```

### Purchase receipt

Posting receipt dari PO approved / partial_received.

Expected:

```text
purchase_order_lines.qty_received naik
purchase_order_lines.qty_outstanding turun
PO status berubah partial_received / received
```

### Reverse receipt

Reverse receipt yang sudah posted.

Expected:

```text
qty_received turun lagi
qty_outstanding naik lagi
PO status kembali partial_received / approved sesuai qty
```

---

## Patch code terkait

```text
app/Controllers/System/ExcelLiteTransferController.php
app/Models/WarehouseModel.php
app/Services/Purchase/PurchaseReceiptService.php
```

## Patch SQL terkait

```text
database/hosting/2026-06-23_harden_department_site_scope.sql
database/hosting/2026-06-23_repair_warehouse_department_scope.sql
database/hosting/2026-06-23_repair_item_location_hierarchy.sql
database/hosting/2026-06-23_repair_po_received_quantities.sql
database/hosting/2026-06-23_master_data_integrity_audit.sql
```
