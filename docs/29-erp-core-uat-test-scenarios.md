# ERP Core UAT Test Scenarios

Tanggal: 2026-06-22

Dokumen ini dibuat untuk membantu tester yang belum memahami alur bisnis ERP. Fokusnya adalah cara mengetes modul core PENA ERP dari awal sampai akhir memakai data sederhana.

Status target dokumen ini: **panduan UAT manual**, bukan automated test.

---

## 1. Tujuan Testing

Testing ini bertujuan memastikan flow utama ERP berjalan utuh:

1. Master data bisa dipakai transaksi.
2. Purchase flow berjalan dari PO sampai payment.
3. Sales flow berjalan dari SO sampai receipt.
4. Inventory bergerak sesuai transaksi.
5. Cash/Bank bergerak sesuai payment/receipt.
6. GL/Jurnal balance dan bisa ditelusuri ke dokumen sumber.
7. Status guard berjalan, misalnya dokumen yang sudah invoice tidak bisa direverse sembarangan.

Sebuah flow dianggap **PASS** hanya jika dokumen transaksi, Stock Card, Cash/Bank, dan GL Entry sama-sama benar.

---

## 2. Aturan Sebelum Mulai

Sebelum testing browser:

1. Pull source terbaru.
2. Jalankan SQL hosting yang wajib.
3. Login sebagai admin/superadmin.
4. Pilih active company dan site di topbar.
5. Pakai satu company/site dulu, misalnya:
   - Company: `PENA`
   - Site: `HO`
6. Jangan test banyak modul sekaligus. Selesaikan satu flow sampai PASS.

SQL hosting minimum:

```text
database/hosting/2026-06-20_update_document_number_and_po_line_tax.sql
database/hosting/2026-06-20_update_purchase_receipt_core.sql
database/hosting/2026-06-20_update_sales_delivery_core.sql
database/hosting/2026-06-20_normalize_core_master_data.sql
database/hosting/2026-06-20_update_receipt_delivery_reversal_gl.sql
database/hosting/2026-06-21_update_po_uat_feedback.sql
database/hosting/2026-06-21_update_sales_order_uat_feedback.sql
database/hosting/2026-06-21_update_system_menu_development_status.sql
```

---

## 3. Data Master Minimal untuk Testing

Gunakan data sederhana supaya mudah dicek manual.

| Master | Kode Contoh | Nama Contoh | Catatan |
|---|---|---|---|
| Company | `PENA` | Pena Inovasi Sistem | Active company |
| Site | `HO` | Head Office | Active site |
| Warehouse | `WH-HO` | Warehouse HO | Untuk stok masuk/keluar |
| Location | `MAIN` | Main Location | Lokasi di dalam warehouse |
| Supplier | `SUP-001` | Supplier UAT 001 | Untuk Purchase Order |
| Customer | `CUST-001` | Customer UAT 001 | Untuk Sales Order |
| Item | `ITEM-001` | Barang UAT 001 | Item utama testing |
| UoM | `PCS` | Pieces | Satuan item |
| Cash/Bank | `KAS-HO` | Kas Head Office | Untuk payment/receipt metode cash |
| Cash/Bank | `BNK-HO` | Bank Head Office | Untuk payment/receipt metode bank |

### 3.1 Data Item Testing

Gunakan satu item dulu:

| Field | Nilai |
|---|---|
| Item Code | `ITEM-001` |
| Item Name | `Barang UAT 001` |
| UoM | `PCS` |
| Purchase Price | `10000` |
| Sales Price | `15000` |
| VAT | kosong / 0 dulu |
| WHT | kosong / 0 dulu |

Catatan: untuk UAT pertama, pajak dibuat kosong/0 supaya hitungan mudah.

---

## 4. Skenario A - Purchasing E2E

Flow:

```text
PO → Submit → Approve → Receipt → Stock Card → AP Invoice → AP Payment → Cash/Bank → GL
```

### A1. Buat Purchase Order

Menu:

```text
Purchase → Purchase Orders → New
```

Input header:

| Field | Nilai |
|---|---|
| PO No | kosongkan agar auto number |
| PO Date | tanggal hari ini |
| Supplier | `SUP-001` |
| Site | `HO` |
| Currency | `IDR` |
| Notes | `UAT Purchase Flow` |

Input line:

| Field | Nilai |
|---|---|
| Item | `ITEM-001` |
| Qty | `10` |
| UoM | `PCS` |
| Unit Price | `10000` |
| Discount | `0` |
| Tax | `0` |

Expected result:

| Check | Expected |
|---|---|
| Save PO | berhasil |
| PO No | terisi otomatis |
| Total PO | `100000` |
| Status awal | draft / sesuai sistem |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### A2. Submit dan Approve PO

Action:

1. Buka detail PO.
2. Klik `Submit`.
3. Klik `Approve`.

Expected result:

| Check | Expected |
|---|---|
| Setelah submit | status berubah submitted / waiting approval |
| Setelah approve | status berubah approved |
| Tombol Receive | tersedia setelah approved |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### A3. Receive Purchase Order

Menu/action:

```text
PO Detail → Receive
```

Input header:

| Field | Nilai |
|---|---|
| Receipt No | kosongkan agar auto number |
| Receipt Date | tanggal hari ini |
| Warehouse | `WH-HO` |
| Location | `MAIN` |
| Notes | `UAT Receipt PO` |

Input line:

| Field | Nilai |
|---|---|
| Receive Now | `10` |
| Batch No | `BATCH-UAT-001` jika ada field batch |

Expected result:

| Check | Expected |
|---|---|
| Receipt posted | berhasil |
| Receipt No | terisi otomatis |
| PO received qty | `10` |
| PO outstanding qty | `0` |
| PO status | received / closed / sesuai sistem |
| Stock `ITEM-001` | bertambah `10` |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### A4. Cek Stock Card setelah Receipt

Menu:

```text
Inventory → Stock Card
```

Filter:

| Field | Nilai |
|---|---|
| Item | `ITEM-001` |
| Date From | tanggal PO/Receipt |
| Date To | tanggal hari ini |
| Warehouse | `WH-HO` |
| Location | `MAIN` |

Expected result:

| Check | Expected |
|---|---|
| Movement type | purchase receipt / receipt |
| Qty In | `10` |
| Qty Out | `0` |
| Balance Qty | bertambah menjadi `10` |
| Value In | `100000` |
| Running Value | bertambah `100000` |
| Reference No | Receipt No muncul |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### A5. Buat AP Invoice dari Receipt

Menu/action:

```text
Purchase Receipt Detail → Create AP Invoice
```

Input header:

| Field | Nilai |
|---|---|
| Invoice No | kosongkan agar auto number |
| Invoice Date | tanggal hari ini |
| Due Date | tanggal hari ini + 14 hari |
| Notes | `UAT AP Invoice` |

Expected result:

| Check | Expected |
|---|---|
| AP Invoice created | berhasil |
| Invoice No | terisi otomatis |
| Invoice total | `100000` |
| Status invoice | open |
| Payable outstanding | `100000` |
| Receipt status | invoiced |
| Receipt detail | tombol berubah menjadi View AP Invoice |
| GL Entry | terbentuk dan balance |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### A6. Post AP Payment sebagian

Menu/action:

```text
AP Invoice Detail → Post Payment
```

Input:

| Field | Nilai |
|---|---|
| Payment No | kosongkan agar auto number |
| Payment Date | tanggal hari ini |
| Payment Method | cash / bank |
| Cash/Bank Code | `KAS-HO` atau `BNK-HO` |
| Amount | `40000` |
| Reference No | `PAY-UAT-001` |
| Notes | `UAT partial AP payment` |

Expected result:

| Check | Expected |
|---|---|
| Payment posted | berhasil |
| Payment No | terisi otomatis |
| Invoice paid amount | `40000` |
| Invoice outstanding | `60000` |
| Invoice status | partial |
| Cash/Bank entry | terbentuk cash/bank out |
| GL Entry | terbentuk dan balance |
| Invoice detail | Payment History tampil |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### A7. Post AP Payment sisa

Menu/action:

```text
AP Invoice Detail → Post Payment
```

Input:

| Field | Nilai |
|---|---|
| Amount | `60000` |
| Cash/Bank Code | sama dengan sebelumnya |
| Reference No | `PAY-UAT-002` |

Expected result:

| Check | Expected |
|---|---|
| Payment posted | berhasil |
| Total paid | `100000` |
| Outstanding | `0` |
| Invoice status | paid |
| Tombol Post Payment | hilang / tidak bisa dipakai |
| Payment History | ada 2 baris payment |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### A8. Cek Cash/Bank dan GL Purchasing

Menu:

```text
Cash/Bank → Cash Entries atau Bank Entries
GL → GL Entries
```

Expected Cash/Bank:

| Check | Expected |
|---|---|
| Entry type | cash_out / bank_out |
| Total out | `100000` |
| Source document | AP Payment tampil di Source Documents |

Expected GL:

| Check | Expected |
|---|---|
| GL difference | `0` |
| AP Invoice GL | ada |
| AP Payment GL | ada |
| Open Source Document | dari GL bisa balik ke dokumen sumber |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

## 5. Skenario B - Sales E2E

Flow:

```text
SO → Submit → Approve → Delivery → Stock Card → AR Invoice → AR Receipt → Cash/Bank → GL
```

Syarat: stok `ITEM-001` harus tersedia minimal `5`. Jika belum ada stok, jalankan Purchasing E2E dulu atau buat stock adjustment masuk.

### B1. Buat Sales Order

Menu:

```text
Sales → Sales Orders → New
```

Input header:

| Field | Nilai |
|---|---|
| SO No | kosongkan agar auto number |
| SO Date | tanggal hari ini |
| Customer | `CUST-001` |
| Site | `HO` |
| Currency | `IDR` |
| Notes | `UAT Sales Flow` |

Input line:

| Field | Nilai |
|---|---|
| Item | `ITEM-001` |
| Qty | `5` |
| UoM | `PCS` |
| Unit Price | `15000` |
| Discount | `0` |
| Tax | `0` |

Expected result:

| Check | Expected |
|---|---|
| Save SO | berhasil |
| SO No | terisi otomatis |
| Total SO | `75000` |
| Status awal | draft / sesuai sistem |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### B2. Submit dan Approve SO

Action:

1. Buka detail SO.
2. Klik `Submit`.
3. Klik `Approve`.

Expected result:

| Check | Expected |
|---|---|
| Setelah approve | status approved |
| Tombol Delivery | tersedia |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### B3. Delivery Sales Order

Menu/action:

```text
SO Detail → Deliver
```

Input header:

| Field | Nilai |
|---|---|
| Delivery No | kosongkan agar auto number |
| Delivery Date | tanggal hari ini |
| Warehouse | `WH-HO` |
| Location | `MAIN` |
| Notes | `UAT Delivery SO` |

Input line:

| Field | Nilai |
|---|---|
| Deliver Now | `5` |
| Batch No | `BATCH-UAT-001` jika batch dipakai |

Expected result:

| Check | Expected |
|---|---|
| Delivery posted | berhasil |
| Delivery No | terisi otomatis |
| SO delivered qty | `5` |
| SO outstanding qty | `0` |
| Stock `ITEM-001` | berkurang `5` |
| COGS GL Entry | terbentuk jika posting profile lengkap |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### B4. Cek Stock Card setelah Delivery

Menu:

```text
Inventory → Stock Card
```

Expected result:

| Check | Expected |
|---|---|
| Movement type | sales delivery / delivery |
| Qty In | `0` |
| Qty Out | `5` |
| Balance Qty | berkurang `5` |
| Reference No | Delivery No muncul |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### B5. Buat AR Invoice dari Delivery

Menu/action:

```text
Sales Delivery Detail → Create Invoice
```

Input header:

| Field | Nilai |
|---|---|
| Invoice No | kosongkan agar auto number |
| Invoice Date | tanggal hari ini |
| Due Date | tanggal hari ini + 14 hari |
| Notes | `UAT AR Invoice` |

Expected result:

| Check | Expected |
|---|---|
| AR Invoice created | berhasil |
| Invoice No | terisi otomatis |
| Invoice total | `75000` |
| Status invoice | open |
| Receivable outstanding | `75000` |
| Delivery status | invoiced |
| Delivery detail | tombol berubah menjadi View AR Invoice |
| GL Entry | terbentuk dan balance |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### B6. Post AR Receipt sebagian

Menu/action:

```text
AR Invoice Detail → Post Receipt
```

Input:

| Field | Nilai |
|---|---|
| Receipt No | kosongkan agar auto number |
| Receipt Date | tanggal hari ini |
| Receipt Method | cash / bank |
| Cash/Bank Code | `KAS-HO` atau `BNK-HO` |
| Amount | `30000` |
| Reference No | `RCV-UAT-001` |
| Notes | `UAT partial AR receipt` |

Expected result:

| Check | Expected |
|---|---|
| Receipt posted | berhasil |
| Receipt No | terisi otomatis |
| Invoice received amount | `30000` |
| Invoice outstanding | `45000` |
| Invoice status | partial |
| Cash/Bank entry | terbentuk cash/bank in |
| GL Entry | terbentuk dan balance |
| Invoice detail | Receipt History tampil |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### B7. Post AR Receipt sisa

Menu/action:

```text
AR Invoice Detail → Post Receipt
```

Input:

| Field | Nilai |
|---|---|
| Amount | `45000` |
| Cash/Bank Code | sama dengan sebelumnya |
| Reference No | `RCV-UAT-002` |

Expected result:

| Check | Expected |
|---|---|
| Receipt posted | berhasil |
| Total received | `75000` |
| Outstanding | `0` |
| Invoice status | paid |
| Tombol Post Receipt | hilang / tidak bisa dipakai |
| Receipt History | ada 2 baris receipt |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

### B8. Cek Cash/Bank dan GL Sales

Expected Cash/Bank:

| Check | Expected |
|---|---|
| Entry type | cash_in / bank_in |
| Total in | `75000` |
| Source document | AR Receipt tampil di Source Documents |

Expected GL:

| Check | Expected |
|---|---|
| GL difference | `0` |
| AR Invoice GL | ada |
| AR Receipt GL | ada |
| Open Source Document | dari GL bisa balik ke dokumen sumber |

Checklist:

| Result | Catatan |
|---|---|
| PASS / FAIL | |

---

## 6. Skenario C - Negative Test / Guard Test

Testing ini penting untuk memastikan sistem tidak bisa dipakai salah.

### C1. Tidak boleh receive PO melebihi outstanding

Langkah:

1. Buat PO qty `10`.
2. Receive `11`.

Expected:

| Check | Expected |
|---|---|
| Sistem | menolak |
| Pesan error | qty tidak boleh melebihi outstanding |
| Stock | tidak berubah |

---

### C2. Tidak boleh delivery SO melebihi stock / outstanding

Langkah:

1. Buat SO qty `999` untuk item yang stoknya tidak cukup.
2. Delivery `999`.

Expected:

| Check | Expected |
|---|---|
| Sistem | menolak |
| Stock | tidak minus |
| GL | tidak terbentuk |

---

### C3. Receipt yang sudah jadi AP Invoice tidak boleh direverse

Langkah:

1. Jalankan PO → Receipt → AP Invoice.
2. Buka Purchase Receipt.
3. Coba reverse.

Expected:

| Check | Expected |
|---|---|
| Tombol reverse | hilang / tidak tersedia |
| Jika dipaksa direct POST | sistem menolak |
| Pesan | harus cancel invoice dulu |

---

### C4. Delivery yang sudah jadi AR Invoice tidak boleh direverse

Langkah:

1. Jalankan SO → Delivery → AR Invoice.
2. Buka Delivery.
3. Coba reverse.

Expected:

| Check | Expected |
|---|---|
| Tombol reverse | hilang / tidak tersedia |
| Jika dipaksa direct POST | sistem menolak |
| Pesan | harus cancel invoice dulu |

---

### C5. Invoice paid tidak boleh dibayar/diterima lagi

Langkah:

1. Bayar AP Invoice sampai paid.
2. Coba buka URL post payment lagi.
3. Terima AR Invoice sampai paid.
4. Coba buka URL post receipt lagi.

Expected:

| Check | Expected |
|---|---|
| AP paid | tidak bisa payment lagi |
| AR paid | tidak bisa receipt lagi |
| Outstanding | tetap 0 |
| Cash/Bank | tidak ada double entry |
| GL | tidak ada double journal |

---

### C6. Cancel settlement harus membuat reversal

Langkah AP:

1. Buat AP Payment.
2. Buka detail AP Payment.
3. Klik Cancel.

Expected AP:

| Check | Expected |
|---|---|
| Payment status | cancelled |
| Invoice outstanding | bertambah lagi |
| Reversal cash/bank | terbentuk |
| Reversal GL | terbentuk |

Langkah AR:

1. Buat AR Receipt.
2. Buka detail AR Receipt.
3. Klik Cancel.

Expected AR:

| Check | Expected |
|---|---|
| Receipt status | cancelled |
| Invoice outstanding | bertambah lagi |
| Reversal cash/bank | terbentuk |
| Reversal GL | terbentuk |

---

## 7. Skenario D - Audit Trace Test

Tujuan: memastikan audit trail bisa ditelusuri dua arah.

### D1. Dari invoice ke settlement

| Source | Expected |
|---|---|
| AP Invoice detail | Payment History tampil |
| AR Invoice detail | Receipt History tampil |

### D2. Dari settlement ke Cash/Bank dan GL

| Source | Expected |
|---|---|
| AP Payment detail | link Cash/Bank Entry dan GL Entry tampil |
| AR Receipt detail | link Cash/Bank Entry dan GL Entry tampil |

### D3. Dari Cash/Bank ke AP/AR

| Source | Expected |
|---|---|
| Cash/Bank Entry detail | Source Documents berisi AP Payment atau AR Receipt |

### D4. Dari GL ke dokumen sumber

| Source | Expected |
|---|---|
| GL Entry detail | tombol Open Source Document mengarah ke dokumen transaksi |

---

## 8. Format Catatan Bug

Jika ada error, catat dengan format ini:

```text
Module:
Scenario:
Step ke-:
Input:
Expected:
Actual:
Screenshot:
Error message:
URL:
User login:
Company/Site:
Waktu kejadian:
```

Contoh:

```text
Module: AP Payment
Scenario: A6 - Post AP Payment sebagian
Step ke-: Klik Save Payment
Input: Amount 40000, Cash Bank KAS-HO
Expected: Payment posted dan invoice partial
Actual: Error 500
Screenshot: terlampir
Error message: Unknown column cash_bank_entry_id
URL: /ap/purchase-invoices/12/payment
User login: admin
Company/Site: PENA / HO
Waktu kejadian: 2026-06-22 10:15
```

---

## 9. Urutan Testing yang Disarankan

Jangan test semuanya sekaligus. Urutan paling aman:

1. Pastikan master data minimal ada.
2. Jalankan Purchasing E2E sampai PASS.
3. Jalankan Sales E2E sampai PASS.
4. Jalankan negative test untuk reverse dan double posting.
5. Jalankan audit trace test.
6. Baru lanjut Production Core.
7. Setelah admin flow PASS, ulang sebagian test dengan user non-admin.

---

## 10. Kesimpulan PASS ERP Core

ERP Core boleh dianggap lulus UAT awal jika:

| Area | Status |
|---|---|
| Purchasing E2E | PASS |
| Sales E2E | PASS |
| Stock Card qty/value | PASS |
| Cash/Bank movement | PASS |
| GL balance difference 0 | PASS |
| Source document trace | PASS |
| Negative guard test | PASS |
| Non-admin permission smoke test | PASS |

Jika salah satu area di atas FAIL, jangan masuk production dulu.
