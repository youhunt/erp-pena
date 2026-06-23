# ERP PENA - FINISHED / FIXED CHECKLIST

Dokumen ini adalah patokan final untuk menutup batch bug:

1. Warehouse import `department_code` beda site lolos.
2. Warehouse save error karena `department_id` / struktur lama.
3. PO receipt posted tapi `qty_received` tidak update.
4. Closed PO perlu bisa di-activate lagi.
5. Data master lama perlu dicek supaya tidak lintas company/site/warehouse.

---

## Status code fix

Code fix sudah selesai di repo.

### File yang sudah diperbaiki

```text
app/Controllers/System/ExcelLiteTransferController.php
app/Models/WarehouseModel.php
app/Services/Purchase/PurchaseReceiptService.php
app/Services/Purchase/PurchaseOrderService.php
app/Views/purchase/orders/show.php
```

### Perilaku final yang diharapkan

```text
Warehouse import:
- department_code wajib ada
- department_code harus sesuai company_code + site_code
- department_code dari site lain wajib error preview

Warehouse manual save:
- department_id harus milik company/site warehouse yang sama

Purchase receipt:
- posting receipt update purchase_order_lines.qty_received
- posting receipt update qty_outstanding
- reverse receipt mengurangi qty_received lagi

Closed PO:
- tombol activate muncul untuk closed PO
- activate mengembalikan status sesuai qty receipt
```

---

## Urutan final di hosting

Jalankan:

```bash
git pull
php spark cache:clear
```

Kalau hosting pakai OPcache dan perubahan belum terbaca, restart PHP dari cPanel / MultiPHP Manager.

---

## SQL final yang wajib dijalankan

Jalankan berurutan di phpMyAdmin:

```text
database/hosting/2026-06-23_harden_department_site_scope.sql
database/hosting/2026-06-23_repair_warehouse_department_scope.sql
database/hosting/2026-06-23_repair_item_location_hierarchy.sql
database/hosting/2026-06-23_repair_po_received_quantities.sql
database/hosting/2026-06-23_master_data_integrity_audit.sql
```

---

## Syarat status FINISHED / FIXED

Batch ini boleh dianggap **FINISHED / FIXED** kalau hasil berikut terpenuhi:

### 1. Audit SQL

File:

```text
database/hosting/2026-06-23_master_data_integrity_audit.sql
```

Expected:

```text
Semua SELECT return zero rows / empty result set.
Tidak ada warning datetime #1292 setelah git pull terbaru.
```

### 2. Test import warehouse beda site

Test:

```text
company_code = PENA
site_code = JKT
department_code = kode department dari site lain
```

Expected:

```text
Masuk error preview.
Tidak masuk valid rows.
```

### 3. Test import warehouse valid

Test:

```text
company_code = PENA
site_code = JKT
department_code = department yang memang milik PENA/JKT
```

Expected:

```text
Import berhasil.
warehouse.department_id terisi department dari PENA/JKT.
```

### 4. Test PO receipt

Test:

```text
Buat/post receipt dari PO approved atau partial_received.
```

Expected:

```text
purchase_order_lines.qty_received naik.
purchase_order_lines.qty_outstanding turun.
PO status jadi partial_received atau received.
```

### 5. Test reverse receipt

Test:

```text
Reverse receipt yang sudah posted.
```

Expected:

```text
qty_received turun lagi.
qty_outstanding naik lagi.
PO status kembali sesuai kondisi qty.
```

### 6. Test closed PO activate

Test:

```text
Buka PO closed.
Klik Activate.
```

Expected:

```text
Tombol Activate muncul.
PO berubah ke received / partial_received / approved sesuai qty_received.
```

---

## Kalau belum fixed

Kalau salah satu test gagal, kirim salah satu dari ini:

```text
1. Screenshot error aplikasi
2. Hasil SELECT audit yang masih ada row
3. ID PO / PO number yang qty_received-nya masih salah
4. Row warehouse yang department_id-nya masih beda site
```

Tanpa salah satu data itu, posisi code dan SQL sudah dianggap selesai dari sisi repo.

---

## Final statement

Jika audit kosong dan 6 test di atas lolos, status batch ini adalah:

```text
FINISHED / FIXED
```
