# ERP Core E2E Test Scenario: Master Data → PO → Receipt → Stock → GL

Dokumen ini dipakai untuk mengetes alur inti ERP PENA dari setup master sampai jurnal GL terbentuk.

## Urutan Testing yang Benar

1. Setup Company / Site
2. Setup COA
3. Setup GL Posting Profile
4. Setup UOM
5. Setup Department
6. Setup Warehouse + Location
7. Setup Item Master
8. Setup Supplier Master
9. Create PO baru pakai item valid dan supplier valid
10. Submit PO
11. Approve PO
12. Receive PO
13. Cek Stock Balance
14. Cek GL Entry

> Catatan penting: Department harus dibuat sebelum Warehouse. Warehouse harus dibuat sebelum Location. UOM, Warehouse, dan Location harus tersedia sebelum Item Master. Supplier Master harus tersedia sebelum Create PO karena header PO membutuhkan supplier/vendor.

## Data Testing Standar

| Master | Field | Value |
|---|---|---|
| Company | Code | TST |
| Company | Name | Test Company ERP |
| Company | Base Currency | IDR |
| Site | Code | TST01 |
| Site | Name | Test Site 01 |
| COA | Cash Bank | 1100 - Cash and Bank |
| COA | Inventory | 1300 - Inventory |
| COA | AP | 2100 - Accounts Payable |
| COA | GRNI | 2300 - Goods Received Not Invoiced |
| UOM | Stock UoM | PCS - Pieces |
| Department | Code | DPT-E2E |
| Department | Name | Department E2E |
| Warehouse | Code | WH-E2E |
| Warehouse | Name | Warehouse E2E |
| Location | Code | LOC-E2E |
| Location | Name | Location E2E |
| Item | Item Code | ITEM-E2E-001 |
| Item | Item Name | Item E2E Test 001 |
| Item | Stock UoM | PCS |
| Item | Purchase UoM | PCS |
| Supplier | Code | SUP-E2E |
| Supplier | Name | Supplier E2E Test |
| PO Qty | Qty | 10 PCS |
| PO Price | Unit Price | 10000 |
| Expected Stock Value | Amount | 100000 |

## Precondition

### Local / Development

```bash
git pull
php spark migrate
php spark db:seed CoreFinanceSeeder
php spark cache:clear
```

### Hosting / phpMyAdmin

1. Pilih database ERP dari sidebar phpMyAdmin.
2. Jalankan:

```text
database/sql/00_RUN_THIS_ON_HOSTING.sql
```

### Health Check Awal

Buka:

```text
/system/core-health
```

Expected: halaman tidak error, tabel core terdeteksi, document numbering ready, cash bank entry columns ready, dan GL posting profile defaults ready.

---

# E2E-001: Setup Company / Site

## Langkah

1. Buka `Setup > Master Data > Companies`.
2. Buat company:
   - Code: `TST`
   - Name: `Test Company ERP`
   - Base Currency: pilih `IDR`
   - Active: Yes
3. Buka `Setup > Master Data > Sites`.
4. Buat site:
   - Company: `TST`
   - Code: `TST01`
   - Name: `Test Site 01`
   - Active: Yes
5. Pastikan header tenant aktif memilih company `TST` dan site `TST01`.

## Expected Result

Company dan site tersimpan, site terhubung ke company, dan base currency company adalah `IDR`.

## Query Verifikasi

```sql
SELECT id, code, name, base_currency, is_active
FROM companies
WHERE code = 'TST';

SELECT s.id, s.code, s.name, s.company_id, s.is_active
FROM sites s
JOIN companies c ON c.id = s.company_id
WHERE c.code = 'TST'
  AND s.code = 'TST01';
```

---

# E2E-002: Setup COA

## Cara Utama

Buka:

```text
GL > GL Utilities
```

Klik:

```text
Initialize Defaults
```

Fungsi ini boleh dipakai untuk membuat default `GL Book`, `Chart of Account`, dan `Posting Profile`.

## Expected Result

Akun wajib berikut tersedia dan active:

| Account No | Account Name |
|---|---|
| 1100 | Cash and Bank |
| 1300 | Inventory |
| 2100 | Accounts Payable |
| 2300 | Goods Received Not Invoiced |

## Query Verifikasi

```sql
SELECT account_no, account_name, is_active
FROM chart_accounts
WHERE account_no IN ('1100','1300','2100','2300')
ORDER BY account_no;
```

---

# E2E-003: Setup GL Posting Profile

## Langkah

Buka:

```text
GL > Posting Profile
```

Pastikan mapping berikut ada:

| Module | Posting Key | Account No |
|---|---|---|
| ap | inventory | 1300 |
| ap | grni | 2300 |
| ap | payable | 2100 |
| cashbank | cash_bank | 1100 |

Jika belum ada, jalankan:

```bash
php spark db:seed CoreFinanceSeeder
```

## Query Verifikasi

```sql
SELECT gp.company_id, gp.module_code, gp.posting_key, gp.account_no, ca.account_name, gp.is_active
FROM gl_posting_profiles gp
LEFT JOIN chart_accounts ca ON ca.account_no = gp.account_no
WHERE gp.module_code IN ('ap','cashbank')
  AND gp.posting_key IN ('inventory','grni','payable','cash_bank')
ORDER BY gp.module_code, gp.posting_key;
```

---

# E2E-004: Setup UOM

## Langkah

Buka:

```text
Setup > Master Data > Units of Measure
```

Pastikan minimal UOM berikut ada untuk company `TST`:

| Code | Name |
|---|---|
| PCS | Pieces |
| KG | Kilogram |
| MTR | Meter |

Jika kosong, jalankan:

```bash
php spark db:seed CoreFinanceSeeder
php spark cache:clear
```

## Query Verifikasi

```sql
SELECT u.company_id, c.code AS company_code, u.code, u.name, u.is_active
FROM uoms u
JOIN companies c ON c.id = u.company_id
WHERE c.code = 'TST'
ORDER BY u.code;
```

---

# E2E-005: Setup Department

## Langkah

Buka:

```text
Setup > Master Data > Departments
```

Buat department:

| Field | Value |
|---|---|
| Code | DPT-E2E |
| Name | Department E2E |
| Company | TST |
| Site | TST01 jika field tersedia |
| Active | Yes |

## Expected Result

Department tersimpan dan muncul di lookup saat membuat Warehouse.

## Query Verifikasi

```sql
SELECT d.id, d.code, d.name, d.company_id, d.site_id, d.is_active
FROM departments d
JOIN companies c ON c.id = d.company_id
WHERE c.code = 'TST'
  AND d.code = 'DPT-E2E';
```

Jika kolom `site_id` tidak ada:

```sql
SELECT d.id, d.code, d.name, d.company_id, d.is_active
FROM departments d
JOIN companies c ON c.id = d.company_id
WHERE c.code = 'TST'
  AND d.code = 'DPT-E2E';
```

---

# E2E-006: Setup Warehouse + Location

## Langkah Warehouse

Buka:

```text
Setup > Master Data > Warehouses
```

Buat warehouse:

| Field | Value |
|---|---|
| Code | WH-E2E |
| Name | Warehouse E2E |
| Company | TST |
| Site | TST01 |
| Department | DPT-E2E |
| Active | Yes |

## Langkah Location

Buka:

```text
Setup > Master Data > Locations
```

Buat location:

| Field | Value |
|---|---|
| Code | LOC-E2E |
| Name | Location E2E |
| Warehouse | WH-E2E |
| Company | TST |
| Site | TST01 |
| Active | Yes |

## Query Verifikasi

```sql
SELECT w.id, w.code, w.name, w.company_id, w.site_id, w.department_id, d.code AS department_code, w.is_active
FROM warehouses w
LEFT JOIN departments d ON d.id = w.department_id
WHERE w.code = 'WH-E2E';

SELECT l.id, l.code, l.name, l.warehouse_id, w.code AS warehouse_code, l.is_active
FROM locations l
JOIN warehouses w ON w.id = l.warehouse_id
WHERE w.code = 'WH-E2E'
  AND l.code = 'LOC-E2E';
```

---

# E2E-007: Setup Item Master

## Langkah

Buka:

```text
Setup > Master Data > Items
```

Buat item:

| Field | Value |
|---|---|
| Item Code | ITEM-E2E-001 |
| Item Name | Item E2E Test 001 |
| Item Type | Purchased / Raw Material / Inventory Item |
| Stock UoM | PCS |
| Purchase UoM | PCS |
| Warehouse | WH-E2E jika field tersedia |
| Company | TST |
| Site | TST01 jika field tersedia |
| Active | Yes |

## Expected Result

Item tersimpan, `item_code` tidak kosong, `stockuom` terisi `PCS`, dan item active.

## Query Verifikasi

```sql
SELECT id, company_id, site_id, item_code, item_name, item_type, stockuom, is_active
FROM items
WHERE item_code = 'ITEM-E2E-001';
```

---

# E2E-008: Setup Supplier Master

## Kenapa Supplier sebelum PO?

Header Purchase Order membutuhkan Supplier/Vendor. Jadi Supplier Master harus tersedia sebelum membuat PO.

## Langkah

Buka:

```text
Setup > Master Data > Suppliers
```

Buat supplier:

| Field | Value |
|---|---|
| Supplier Code / Supplier | SUP-E2E |
| Supplier Name | Supplier E2E Test |
| Company | TST |
| Site | TST01 jika field tersedia |
| Active | Yes |

Isi field lain seperti alamat, kota, kontak, telepon, dan tax number jika required oleh form.

## Expected Result

- Supplier tersimpan.
- Supplier muncul di lookup saat Create PO.
- PO tidak perlu mengetik supplier manual.

## Query Verifikasi

```sql
SELECT id, company_id, site_id, supplier, supplierna, is_active
FROM suppliers
WHERE supplier = 'SUP-E2E';
```

Jika kolom `site_id` tidak ada:

```sql
SELECT id, company_id, supplier, supplierna, is_active
FROM suppliers
WHERE supplier = 'SUP-E2E';
```

---

# E2E-009: Create PO Baru Pakai Item dan Supplier Valid

## Langkah

Buka:

```text
Purchase > Purchase Orders
```

Klik New / Create.

Header:

| Field | Value |
|---|---|
| Supplier | SUP-E2E - Supplier E2E Test |
| Currency | IDR |
| Site | TST01 |
| PO Date | tanggal hari ini |
| Delivery Date | tanggal hari ini atau besok |

Line:

| Field | Value |
|---|---|
| Item Code | ITEM-E2E-001 |
| Description | Item E2E Test 001 |
| Qty | 10 |
| UOM | PCS |
| Unit Price | 10000 |
| Line Total | 100000 |

Simpan sebagai draft.

## Expected Result

- PO tersimpan sebagai `draft`.
- PO number terbentuk.
- Header menyimpan `supplier_code` / `supplier` = `SUP-E2E`.
- Line menyimpan `item_code`, bukan hanya `item_name`.
- `qty_ordered = 10`, `qty_received = 0`, `qty_outstanding = 10`.

## Query Verifikasi

Ganti `PO_NO_HASIL_TEST` dengan nomor PO yang terbentuk.

```sql
SELECT id, po_no, supplier, supplier_code, supplier_name, document_status, status, total_amount
FROM purchase_orders
WHERE po_no = 'PO_NO_HASIL_TEST';

SELECT pol.item_code, pol.item_name, pol.qty_ordered, pol.qty_received, pol.qty_outstanding, pol.unit_price, pol.line_total
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE po.po_no = 'PO_NO_HASIL_TEST';
```

---

# E2E-010: Submit PO

1. Buka detail PO hasil test.
2. Klik Submit.
3. Confirm.

Expected: status berubah dari `draft` ke `submitted`.

```sql
SELECT po_no, document_status, status, submitted_at, submitted_by
FROM purchase_orders
WHERE po_no = 'PO_NO_HASIL_TEST';
```

---

# E2E-011: Approve PO

1. Buka detail PO.
2. Klik Approve.
3. Confirm.

Expected: status berubah dari `submitted` ke `approved` dan tombol Receive muncul.

```sql
SELECT po_no, document_status, status, approved_at, approved_by
FROM purchase_orders
WHERE po_no = 'PO_NO_HASIL_TEST';
```

---

# E2E-012: Receive PO

1. Buka detail PO.
2. Klik Receive.
3. Pilih warehouse `WH-E2E`.
4. Pilih location `LOC-E2E`.
5. Receive qty `10`.
6. Unit cost `10000`.
7. Klik Post / Submit Receipt.

Expected:

- Receipt posted.
- PO line `qty_received = 10`.
- PO line `qty_outstanding = 0`.
- Stock movement terbentuk.
- Stock balance bertambah 10.
- GL Entry terbentuk: Debit Inventory 100000 dan Credit GRNI 100000.

```sql
SELECT pr.id, pr.receipt_no, pr.purchase_order_id, pr.status, pr.receipt_date
FROM purchase_receipts pr
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE po.po_no = 'PO_NO_HASIL_TEST'
ORDER BY pr.id DESC;

SELECT po.po_no, po.document_status, po.status,
       pol.item_code, pol.qty_ordered, pol.qty_received, pol.qty_outstanding, pol.line_status
FROM purchase_orders po
JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
WHERE po.po_no = 'PO_NO_HASIL_TEST';
```

---

# E2E-013: Cek Stock Balance

Buka:

```text
Inventory > Stock Balance
```

Cari item `ITEM-E2E-001`.

```sql
SELECT company_id, site_id, warehouse_id, location_id, item_code, batch_no,
       qty_on_hand, qty_reserved, qty_available, avg_cost, stock_value
FROM inventory_stock_balances
WHERE item_code = 'ITEM-E2E-001';

SELECT movement_date, movement_type, direction, item_code, qty, unit_cost, stock_value, reference_type, reference_no, gl_entry_id
FROM inventory_stock_movements
WHERE item_code = 'ITEM-E2E-001'
ORDER BY id DESC;
```

Expected: `qty_on_hand >= 10`, `qty_available >= 10`, `stock_value >= 100000`.

---

# E2E-014: Cek GL Entry

Buka:

```text
GL > GL Entry
```

Cari berdasarkan receipt number / source number.

Expected jurnal:

| Account | Debit | Credit |
|---|---:|---:|
| 1300 Inventory | 100000 | 0 |
| 2300 GRNI | 0 | 100000 |

```sql
SELECT ge.id, ge.journal_no, ge.journal_date, ge.source_module, ge.source_type, ge.source_no, ge.description
FROM gl_entries ge
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = 'PO_NO_HASIL_TEST'
)
ORDER BY ge.id DESC;

SELECT ge.journal_no, ge.source_no, gel.account_no, ca.account_name,
       gel.debit, gel.credit, gel.description
FROM gl_entry_lines gel
JOIN gl_entries ge ON ge.id = gel.gl_entry_id
LEFT JOIN chart_accounts ca ON ca.account_no = gel.account_no
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = 'PO_NO_HASIL_TEST'
)
ORDER BY gel.id;

SELECT ge.journal_no,
       SUM(COALESCE(gel.debit, 0)) AS total_debit,
       SUM(COALESCE(gel.credit, 0)) AS total_credit,
       SUM(COALESCE(gel.debit, 0)) - SUM(COALESCE(gel.credit, 0)) AS diff
FROM gl_entries ge
JOIN gl_entry_lines gel ON gel.gl_entry_id = ge.id
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = 'PO_NO_HASIL_TEST'
)
GROUP BY ge.journal_no;
```

Expected: total debit = total credit dan diff = 0.

---

# Negative Test E2E-015: PO Receipt dengan Item Tidak Ada di Item Master

Buat PO line dengan item code yang tidak ada di Item Master, contoh `ITEM-NOT-FOUND-001`, lalu coba Receive.

Expected: receipt gagal dengan pesan item tidak ditemukan di Item Master.

---

# Negative Test E2E-016: Create PO tanpa Supplier Master

Coba buat PO tanpa supplier valid.

Expected: PO tidak bisa dibuat/submit, atau supplier lookup kosong. Solusinya buat Supplier Master dulu di `Setup > Master Data > Suppliers`.

---

# Negative Test E2E-017: GL Posting Profile Tidak Lengkap

Nonaktifkan sementara `ap.grni` atau kosongkan account no-nya di database testing, lalu coba Receive PO.

Expected: sistem menolak posting atau fallback ke akun default sesuai service yang berjalan.

Restore:

```sql
UPDATE gl_posting_profiles
SET account_no = '2300', is_active = 1, updated_at = NOW()
WHERE module_code = 'ap'
  AND posting_key = 'grni';
```

---

# Hasil Testing

| Test Case | Status | Catatan | Tester | Tanggal |
|---|---|---|---|---|
| E2E-001 Setup Company / Site |  |  |  |  |
| E2E-002 Setup COA |  |  |  |  |
| E2E-003 GL Posting Profile |  |  |  |  |
| E2E-004 Setup UOM |  |  |  |  |
| E2E-005 Setup Department |  |  |  |  |
| E2E-006 Warehouse + Location |  |  |  |  |
| E2E-007 Item Master |  |  |  |  |
| E2E-008 Supplier Master |  |  |  |  |
| E2E-009 Create PO |  |  |  |  |
| E2E-010 Submit PO |  |  |  |  |
| E2E-011 Approve PO |  |  |  |  |
| E2E-012 Receive PO |  |  |  |  |
| E2E-013 Stock Balance |  |  |  |  |
| E2E-014 GL Entry |  |  |  |  |
| E2E-015 Negative Item Not Found |  |  |  |  |
| E2E-016 Negative PO Without Supplier |  |  |  |  |
| E2E-017 Negative Posting Profile |  |  |  |  |

## Kriteria Lulus E2E Core

Alur dianggap lulus jika:

1. Master data wajib tersedia: Company, Site, UOM, Department, Warehouse, Location, Item, Supplier.
2. PO valid bisa dibuat, submit, approve, dan receive.
3. PO invalid karena item tidak ada di Item Master ditolak.
4. PO tanpa supplier valid tidak bisa diproses.
5. Stock balance bertambah sesuai qty receipt.
6. Stock movement terbentuk.
7. GL entry terbentuk.
8. GL line balanced, debit sama dengan credit.
9. Akun Inventory dan GRNI sesuai Posting Profile.
