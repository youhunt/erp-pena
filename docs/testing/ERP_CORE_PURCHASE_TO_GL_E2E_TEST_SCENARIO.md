# ERP Core E2E Test Scenario: Master Data → PO → Receipt → Stock → GL

Dokumen ini dipakai untuk mengetes alur inti ERP PENA dari setup master sampai jurnal GL terbentuk.

## Tujuan Testing

Memastikan alur berikut berjalan end-to-end dengan urutan master data yang benar:

1. Setup Company / Site
2. Setup COA
3. Setup GL Posting Profile
4. Setup UOM
5. Setup Warehouse + Location
6. Setup Item Master
7. Create PO baru pakai item valid
8. Submit PO
9. Approve PO
10. Receive PO
11. Cek Stock Balance
12. Cek GL Entry

> Catatan penting: Warehouse, Location, dan UOM harus disiapkan sebelum Item Master karena form Item Master membutuhkan lookup Stock UoM, Purchase UoM, dan Warehouse.

## Data Testing Standar

Gunakan data ini supaya hasil test konsisten.

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

Sebelum test, jalankan database setup sesuai environment.

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

Expected:

- Tidak ada error page.
- Tabel core terdeteksi.
- Cash Bank entry rate columns ready.
- Document numbering required codes ready.
- GL posting profile defaults ready.

---

# Test Case E2E-001: Setup Company / Site

## Langkah

1. Buka menu `Setup > Master Data > Companies`.
2. Buat company:
   - Code: `TST`
   - Name: `Test Company ERP`
   - Base Currency: pilih `IDR` dari Select2.
   - Status: Active.
3. Buka `Setup > Master Data > Sites`.
4. Buat site:
   - Company: `TST`
   - Code: `TST01`
   - Name: `Test Site 01`
   - Status: Active.
5. Pastikan header tenant aktif sudah memilih company `TST` dan site `TST01`.

## Expected Result

- Company berhasil disimpan.
- Site berhasil disimpan dan terhubung ke company.
- Base currency tersimpan sebagai `IDR`.
- Tidak muncul duplicate error.

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

# Test Case E2E-002: Setup COA

## Cara Utama via GL Utilities

Buka menu:

```text
GL > GL Utilities
```

Klik:

```text
Initialize Defaults
```

Fungsi ini boleh dipakai untuk membuat default:

```text
GL Book
Chart of Account
Posting Profile
```

## Cara Manual jika diperlukan

Buka menu:

```text
GL > Chart of Account
```

Pastikan akun berikut ada dan active:

| Account No | Account Name | Normal Balance |
|---|---|---|
| 1100 | Cash and Bank | Debit |
| 1300 | Inventory | Debit |
| 2100 | Accounts Payable | Credit |
| 2300 | Goods Received Not Invoiced | Credit |

## Expected Result

- Semua akun tersedia.
- Semua akun active.
- Account number tidak duplicate.

## Query Verifikasi

```sql
SELECT account_no, account_name, is_active
FROM chart_accounts
WHERE account_no IN ('1100','1300','2100','2300')
ORDER BY account_no;
```

Expected minimal 4 row.

---

# Test Case E2E-003: Setup GL Posting Profile

## Langkah

Setelah `GL > GL Utilities > Initialize Defaults`, buka menu:

```text
GL > Posting Profile
```

Pastikan mapping berikut ada untuk company test:

| Module | Posting Key | Account No | Description |
|---|---|---|---|
| ap | inventory | 1300 | Purchased Inventory |
| ap | grni | 2300 | Goods Received Not Invoiced |
| ap | payable | 2100 | Accounts Payable |
| cashbank | cash_bank | 1100 | Cash and Bank |

Jika belum ada, jalankan seeder:

```bash
php spark db:seed CoreFinanceSeeder
```

## Expected Result

- Posting profile aktif.
- Account no mengarah ke COA yang valid.

## Query Verifikasi

```sql
SELECT gp.company_id, gp.module_code, gp.posting_key, gp.account_no, ca.account_name, gp.is_active
FROM gl_posting_profiles gp
LEFT JOIN chart_accounts ca ON ca.account_no = gp.account_no
WHERE gp.module_code IN ('ap','cashbank')
  AND gp.posting_key IN ('inventory','grni','payable','cash_bank')
ORDER BY gp.module_code, gp.posting_key;
```

Expected:

- `ap.inventory` → `1300`
- `ap.grni` → `2300`
- `ap.payable` → `2100`
- `cashbank.cash_bank` → `1100`

---

# Test Case E2E-004: Setup UOM

## Kenapa UOM sebelum Item Master?

Form Item Master membutuhkan lookup:

```text
Stock UoM
Purchase UoM
```

Jadi UOM harus tersedia dulu sebelum item dibuat.

## Langkah

Buka menu:

```text
Setup > Master Data > Units of Measure
```

Pastikan minimal UOM berikut ada untuk company aktif `TST`:

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

## Expected Result

- UOM `PCS` tersedia.
- UOM lookup bisa dipakai di Item Master.
- UOM dibuat per company, bukan per site.

## Query Verifikasi

```sql
SELECT u.company_id, c.code AS company_code, u.code, u.name, u.is_active
FROM uoms u
JOIN companies c ON c.id = u.company_id
WHERE c.code = 'TST'
ORDER BY u.code;
```

Expected minimal ada `PCS`.

---

# Test Case E2E-005: Setup Warehouse + Location

## Kenapa Warehouse + Location sebelum Item Master?

Pada form Item Master terdapat field `Warehouse`. Karena itu warehouse dan location perlu disiapkan lebih dulu agar pilihan lookup tersedia.

## Langkah Warehouse

Buka menu:

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
| Department | pilih department valid jika required |
| Active | Yes |

## Langkah Location

Buka menu:

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

## Expected Result

- Warehouse tersimpan.
- Location tersimpan dan link ke warehouse.
- Warehouse code boleh sama jika department berbeda, tapi dalam department yang sama tetap tidak boleh duplicate.

## Query Verifikasi

```sql
SELECT w.id, w.code, w.name, w.company_id, w.site_id, w.department_id, w.is_active
FROM warehouses w
WHERE w.code = 'WH-E2E';

SELECT l.id, l.code, l.name, l.warehouse_id, w.code AS warehouse_code, l.is_active
FROM locations l
JOIN warehouses w ON w.id = l.warehouse_id
WHERE w.code = 'WH-E2E'
  AND l.code = 'LOC-E2E';
```

---

# Test Case E2E-006: Setup Item Master

## Langkah

Buka menu:

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

- Item tersimpan.
- `item_code` tidak kosong.
- `stockuom` tidak kosong.
- `purchaseuom` atau field Purchase UoM terisi jika tersedia.
- Warehouse/default warehouse terisi jika field tersedia.
- Item active.

## Query Verifikasi

```sql
SELECT id, company_id, site_id, item_code, item_name, item_type, stockuom, purchaseuom, warehouse, is_active
FROM items
WHERE item_code = 'ITEM-E2E-001';
```

Jika database belum punya kolom `purchaseuom` atau `warehouse`, gunakan query aman ini:

```sql
SELECT id, company_id, site_id, item_code, item_name, item_type, stockuom, is_active
FROM items
WHERE item_code = 'ITEM-E2E-001';
```

Expected 1 row.

---

# Test Case E2E-007: Create PO Baru Pakai Item Valid

## Langkah

Buka menu:

```text
Purchase > Purchase Orders
```

Klik New / Create.

Header:

| Field | Value |
|---|---|
| Supplier | SUP-E2E / supplier valid |
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
- Line item menyimpan `item_code`, bukan hanya `item_name`.
- `qty_ordered = 10`.
- `qty_received = 0`.
- `qty_outstanding = 10`.

## Query Verifikasi

Ganti `PO_NO_HASIL_TEST` dengan nomor PO yang terbentuk.

```sql
SELECT id, po_no, document_status, status, total_amount
FROM purchase_orders
WHERE po_no = 'PO_NO_HASIL_TEST';

SELECT pol.item_code, pol.item_name, pol.qty_ordered, pol.qty_received, pol.qty_outstanding, pol.unit_price, pol.line_total
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE po.po_no = 'PO_NO_HASIL_TEST';
```

---

# Test Case E2E-008: Submit PO

## Langkah

1. Buka detail PO hasil test.
2. Klik Submit.
3. Confirm.

## Expected Result

- Status berubah dari `draft` ke `submitted`.
- Field `submitted_at` terisi jika tersedia.
- PO tidak bisa diedit bebas setelah submitted.

## Query Verifikasi

```sql
SELECT po_no, document_status, status, submitted_at, submitted_by
FROM purchase_orders
WHERE po_no = 'PO_NO_HASIL_TEST';
```

Expected:

```text
status/document_status = submitted
```

---

# Test Case E2E-009: Approve PO

## Langkah

1. Buka detail PO.
2. Klik Approve.
3. Confirm.

## Expected Result

- Status berubah dari `submitted` ke `approved`.
- Field `approved_at` terisi jika tersedia.
- Tombol Receive muncul.

## Query Verifikasi

```sql
SELECT po_no, document_status, status, approved_at, approved_by
FROM purchase_orders
WHERE po_no = 'PO_NO_HASIL_TEST';
```

Expected:

```text
status/document_status = approved
```

---

# Test Case E2E-010: Receive PO

## Langkah

1. Buka detail PO.
2. Klik Receive.
3. Pilih warehouse `WH-E2E`.
4. Pilih location `LOC-E2E`.
5. Receive qty `10`.
6. Unit cost mengikuti unit price `10000`.
7. Klik Post / Submit Receipt.

## Expected Result

- Receipt berhasil diposting.
- Receipt number terbentuk.
- PO line `qty_received` menjadi 10.
- PO line `qty_outstanding` menjadi 0.
- PO status menjadi `received` atau minimal `partial_received` jika tidak semua line diterima.
- Stock movement terbentuk.
- Stock balance bertambah 10.
- GL Entry terbentuk:
  - Debit Inventory 100000
  - Credit GRNI 100000

## Query Verifikasi Receipt

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

# Test Case E2E-011: Cek Stock Balance

## Langkah

Buka menu:

```text
Inventory > Stock Balance
```

Cari item:

```text
ITEM-E2E-001
```

## Expected Result

- Qty on hand bertambah 10.
- Qty available bertambah 10.
- Avg cost sekitar 10000 jika sebelumnya tidak ada stock.
- Stock value 100000 jika sebelumnya stock kosong.

## Query Verifikasi

```sql
SELECT company_id, site_id, warehouse_id, location_id, item_code, batch_no,
       qty_on_hand, qty_reserved, qty_available, avg_cost, stock_value
FROM inventory_stock_balances
WHERE item_code = 'ITEM-E2E-001';
```

Expected minimal:

```text
qty_on_hand >= 10
qty_available >= 10
stock_value >= 100000
```

## Query Stock Movement

```sql
SELECT movement_date, movement_type, direction, item_code, qty, unit_cost, stock_value, reference_type, reference_no, gl_entry_id
FROM inventory_stock_movements
WHERE item_code = 'ITEM-E2E-001'
ORDER BY id DESC;
```

Expected:

```text
direction = in
qty = 10
stock_value = 100000
reference_no = receipt number
```

---

# Test Case E2E-012: Cek GL Entry

## Langkah

Buka menu:

```text
GL > GL Entry
```

Cari berdasarkan receipt number / source number.

## Expected Result

Harus ada jurnal receipt:

| Account | Debit | Credit |
|---|---:|---:|
| 1300 Inventory | 100000 | 0 |
| 2300 GRNI | 0 | 100000 |

Total debit harus sama dengan total credit.

## Query Verifikasi GL Header

```sql
SELECT ge.id, ge.journal_no, ge.journal_date, ge.source_module, ge.source_type, ge.source_no, ge.description
FROM gl_entries ge
WHERE ge.source_module IN ('purchase','inventory','ap')
  AND ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = 'PO_NO_HASIL_TEST'
  )
ORDER BY ge.id DESC;
```

## Query Verifikasi GL Lines

```sql
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
```

## Query Validasi Balance

```sql
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

Expected:

```text
total_debit = total_credit
diff = 0
```

---

# Negative Test Case E2E-013: PO Receipt dengan Item Tidak Ada di Item Master

## Tujuan

Memastikan sistem menolak receipt kalau PO line tidak punya item valid.

## Langkah

1. Buat PO line dengan item code yang tidak ada di Item Master, misalnya:
   - `ITEM-NOT-FOUND-001`
2. Submit dan approve PO jika sistem mengizinkan.
3. Coba Receive.

## Expected Result

Receipt harus gagal dengan pesan sejenis:

```text
Item code ITEM-NOT-FOUND-001 is not found in Item Master.
Please fix item master / imported document before posting.
```

## Catatan

Ini behavior yang benar. ERP tidak boleh posting stock dan GL dari item yang tidak ada di Item Master.

---

# Negative Test Case E2E-014: GL Posting Profile Tidak Lengkap

## Tujuan

Memastikan tester tahu penyebab jika GL tidak terbentuk sesuai akun.

## Langkah

1. Nonaktifkan sementara `ap.grni` atau kosongkan account no-nya di database testing.
2. Coba Receive PO.

## Expected Result

Sistem harus:

- Menolak posting, atau
- Fallback ke akun default `2300`, tergantung service yang berjalan.

Setelah test selesai, aktifkan ulang posting profile.

## Query Restore

```sql
UPDATE gl_posting_profiles
SET account_no = '2300', is_active = 1, updated_at = NOW()
WHERE module_code = 'ap'
  AND posting_key = 'grni';
```

---

# Hasil Testing

Gunakan tabel ini untuk mencatat hasil UAT.

| Test Case | Status | Catatan | Tester | Tanggal |
|---|---|---|---|---|
| E2E-001 Setup Company / Site |  |  |  |  |
| E2E-002 Setup COA |  |  |  |  |
| E2E-003 GL Posting Profile |  |  |  |  |
| E2E-004 Setup UOM |  |  |  |  |
| E2E-005 Warehouse + Location |  |  |  |  |
| E2E-006 Item Master |  |  |  |  |
| E2E-007 Create PO |  |  |  |  |
| E2E-008 Submit PO |  |  |  |  |
| E2E-009 Approve PO |  |  |  |  |
| E2E-010 Receive PO |  |  |  |  |
| E2E-011 Stock Balance |  |  |  |  |
| E2E-012 GL Entry |  |  |  |  |
| E2E-013 Negative Item Not Found |  |  |  |  |
| E2E-014 Negative Posting Profile |  |  |  |  |

## Kriteria Lulus E2E Core

Alur dianggap lulus jika:

1. PO valid bisa dibuat, submit, approve, dan receive.
2. PO invalid karena item tidak ada di Item Master ditolak.
3. Stock balance bertambah sesuai qty receipt.
4. Stock movement terbentuk.
5. GL entry terbentuk.
6. GL line balanced, debit sama dengan credit.
7. Akun Inventory dan GRNI sesuai Posting Profile.
8. UOM, Warehouse, dan Location tersedia sebelum Item Master dibuat.
