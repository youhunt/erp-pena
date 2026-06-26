# ERP PENA - Lanjut E2E dari PO Draft

Dokumen ini dipakai khusus untuk kondisi test saat ini: PO draft sudah berhasil dibuat dan tinggal dilanjutkan sampai Receipt, Stock, dan GL.

## Kondisi Awal

| Item | Nilai |
|---|---|
| PO No | `PO/2026/00001` |
| Supplier | `SUP-E2E - Supplier E2E Test` |
| Item | `ITEM-E2E-001 - Item E2E Test 001` |
| Qty | `10` |
| UOM | `PCS` |
| Unit Price | `10000` |
| Expected Stock Value | `100000` |

## Command Sebelum Test Ulang

```bash
git pull
php spark migrate
php spark db:seed CoreFinanceSeeder
php spark cache:clear
```

Kalau di hosting/phpMyAdmin dan tidak bisa menjalankan `php spark`, jalankan:

```text
database/sql/00_RUN_THIS_ON_HOSTING.sql
```

## 1. Submit PO

Buka URL:

```text
/purchase/orders
```

Atau langsung buka detail PO jika ID-nya sudah diketahui:

```text
/purchase/orders/{PO_ID}
```

Langkah:

1. Buka detail PO `PO/2026/00001`.
2. Klik tombol `Submit`.
3. Confirm.

Expected:

| Field | Expected |
|---|---|
| `document_status` | `submitted` |
| `status` | `submitted` |
| `submitted_at` | terisi |

Query cepat:

```sql
SELECT po_no, document_status, status, submitted_at, submitted_by
FROM purchase_orders
WHERE po_no = 'PO/2026/00001';
```

## 2. Approve PO

Masih di detail PO.

Langkah:

1. Klik tombol `Approve`.
2. Confirm.

Expected:

| Field | Expected |
|---|---|
| `document_status` | `approved` |
| `status` | `approved` |
| `approved_at` | terisi |
| Tombol Receive | muncul |

Query cepat:

```sql
SELECT po_no, document_status, status, submitted_at, approved_at, approved_by
FROM purchase_orders
WHERE po_no = 'PO/2026/00001';
```

## 3. Receive PO

Masih di detail PO.

Langkah:

1. Klik tombol `Receive`.
2. Pilih Warehouse: `WH-E2E - Warehouse E2E`.
3. Pilih Location: `LOC-E2E - Location E2E`.
4. Pastikan `Receive Now` terisi `10`.
5. Kosongkan `Receipt No` supaya auto-number dari transaction code `PR`.
6. Klik `Post Receipt & Update Stock`.

Catatan penting:

- Di form receipt tidak ada field `Unit Cost`; nilai cost otomatis diambil dari `unit_price` PO line.
- Dengan qty `10` dan unit price `10000`, nilai inventory yang diposting adalah `100000`.

Expected setelah post:

| Area | Expected |
|---|---|
| Purchase Receipt | status `posted` |
| PO Header | status menjadi `received` untuk full receipt |
| PO Line | `qty_received = 10`, `qty_outstanding = 0` |
| Stock Movement | terbentuk `purchase_receipt`, direction `in` |
| Stock Balance | `qty_on_hand >= 10`, `stock_value >= 100000` |
| GL Entry | Debit Inventory `100000`, Credit GRNI `100000` |

## 4. Cek Stock Balance

Buka menu:

```text
Inventory > Stock Balance
```

URL:

```text
/inventory/stock-balances
```

Cari:

```text
ITEM-E2E-001
```

Expected minimal:

| Field | Expected |
|---|---:|
| `qty_on_hand` | `>= 10` |
| `qty_available` | `>= 10` |
| `stock_value` | `>= 100000` |

## 5. Cek GL Entry

Buka menu:

```text
GL > GL Entry
```

URL:

```text
/gl/entries
```

Cari berdasarkan receipt number, contoh:

```text
PR/2026/00001
```

Expected jurnal receipt:

| Account | Debit | Credit |
|---|---:|---:|
| `1300 - Inventory` | `100000` | `0` |
| `2300 - Goods Received Not Invoiced` | `0` | `100000` |

## 6. Jalankan Verifikasi SQL Final

Jalankan di phpMyAdmin:

```text
database/sql/97_VERIFY_E2E_PURCHASE_TO_GL.sql
```

File ini default sudah diarahkan ke:

```sql
SET @po_no := 'PO/2026/00001';
SET @item_code := 'ITEM-E2E-001';
SET @supplier_code := 'SUP-E2E';
SET @expected_qty := 10.0000;
SET @expected_stock_value := 100000.00;
```

Kalau PO number berbeda, ubah `@po_no` saja.

## 7. Kriteria Lulus

E2E core dianggap lulus kalau semua summary berikut sesuai expected:

| Summary | Expected |
|---|---|
| `SUMMARY_MASTER_READY` | `ok_count >= 7` |
| `SUMMARY_PO_HAS_SUPPLIER` | `ok_count >= 1` |
| `SUMMARY_PO_SUBMITTED_APPROVED` | `ok_count >= 1` |
| `SUMMARY_RECEIPT_POSTED` | `ok_count >= 1` |
| `SUMMARY_RECEIPT_LINE_POSTED` | `ok_count >= 1` |
| `SUMMARY_PO_RECEIVED_FULL` | `ok_count >= 1` |
| `SUMMARY_STOCK_UPDATED` | `ok_count >= 1` |
| `SUMMARY_STOCK_MOVEMENT_CREATED` | `ok_count >= 1` |
| `SUMMARY_GL_BALANCED` | `ok_count >= 1` |
| `SUMMARY_GL_EXPECTED_AMOUNT` | `ok_count = 1` |

## 8. Error yang Paling Mungkin dan Penyebabnya

### Error: `PO status draft cannot be changed to approved`

Penyebab: PO belum di-submit.

Solusi:

1. Balik ke detail PO.
2. Klik `Submit` dulu.
3. Baru klik `Approve`.

### Error: `Only approved or partially received PO can be received`

Penyebab: PO belum approve atau status belum berubah ke `approved`.

Solusi:

```sql
SELECT po_no, document_status, status, submitted_at, approved_at
FROM purchase_orders
WHERE po_no = 'PO/2026/00001';
```

Pastikan status sudah `approved`.

### Error: `Warehouse and location are required before posting purchase receipt`

Penyebab: Warehouse/location belum dipilih atau location tidak ikut terpilih setelah warehouse berubah.

Solusi:

1. Pilih ulang Warehouse `WH-E2E`.
2. Pilih ulang Location `LOC-E2E`.
3. Pastikan dropdown Location tidak kosong.

### Error: `Selected location does not belong to selected warehouse`

Penyebab: location yang dipilih bukan milik warehouse tersebut.

Solusi:

```sql
SELECT l.id, l.code, l.name, l.warehouse_id, w.code AS warehouse_code
FROM locations l
JOIN warehouses w ON w.id = l.warehouse_id
WHERE l.code = 'LOC-E2E'
   OR w.code = 'WH-E2E';
```

Pastikan `LOC-E2E` berada di bawah `WH-E2E`.

### Error: `Item code ITEM-E2E-001 is not found in Item Master`

Penyebab: PO line berisi item code yang tidak ada / tidak active di Item Master.

Solusi:

```sql
SELECT id, company_id, site_id, item_code, item_name, stockuom, is_active, deleted_at
FROM items
WHERE item_code = 'ITEM-E2E-001';
```

Pastikan item ada, active, dan company-nya sama dengan company PO.

### Error: `Account not found or inactive: 1300` atau `2300`

Penyebab: COA untuk company `TST` belum ada atau inactive.

Solusi cepat:

1. Buka `GL > GL Utilities`.
2. Klik `Initialize Defaults`.
3. Jalankan lagi:

```bash
php spark db:seed CoreFinanceSeeder
php spark cache:clear
```

Cek ulang:

```sql
SELECT c.code AS company_code, ca.account_no, ca.account_name, ca.is_active, ca.is_postable
FROM chart_accounts ca
JOIN companies c ON c.id = ca.company_id
WHERE c.code = 'TST'
  AND ca.account_no IN ('1300','2300');
```

### Error: `GL journal or transaction source has already been posted`

Penyebab: receipt yang sama sudah pernah berhasil posting GL, lalu form dikirim ulang.

Solusi:

- Jangan refresh/re-submit halaman receipt yang sama.
- Cek receipt dan GL dari menu `Purchase > Purchase Receipts` atau `GL > GL Entry`.
