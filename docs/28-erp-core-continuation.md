# ERP Core Continuation Plan

Tanggal: 2026-06-21

Dokumen ini mencatat kelanjutan development ERP core setelah modul PO/SO, receipt/delivery, AR/AP settlement, inventory audit, GL validation, dan production import/edit sudah masuk tahap UAT.

## 1. Posisi Development Saat Ini

PENA ERP sekarang sudah tidak berada pada tahap blueprint kosong. Core application sudah memiliki:

- Foundation CodeIgniter 4 + Shield + Skote.
- Multi company dan multi site context.
- Route permission guard dan status guard transaksi.
- Document numbering otomatis untuk dokumen utama.
- Purchase flow: PO, receipt, AP invoice, AP payment.
- Sales flow: SO, delivery, AR invoice, AR receipt.
- Inventory movement, stock balance, stock card, running qty/value.
- GL entry posting, balance validation, trial balance summary.
- Production core: BOM, Work Center, Routing, Work Order, import, edit, dan action guard.

Status yang benar saat ini adalah **Internal UAT / Core Stabilization**.

## 2. Flow Core yang Wajib Diuji Dulu

### 2.1 Purchasing E2E

| Step | Test | Expected Result |
|---:|---|---|
| 1 | Create PO tanpa isi nomor | Nomor PO otomatis dibuat |
| 2 | Submit dan approve PO | Status menjadi approved |
| 3 | Receive PO | Receipt posted dan stock bertambah |
| 4 | Cek PO line | Received/outstanding qty akurat |
| 5 | Cek Stock Card | Movement purchase receipt tampil |
| 6 | Create AP Invoice | Payable open |
| 7 | Post AP Payment sebagian | Payable partial |
| 8 | Post AP Payment sisa | Payable paid |
| 9 | Cek Cash/Bank | Balance berkurang |
| 10 | Cek GL | Debit/credit balance |

### 2.2 Sales E2E

| Step | Test | Expected Result |
|---:|---|---|
| 1 | Create SO tanpa isi nomor | Nomor SO otomatis dibuat |
| 2 | Submit dan approve SO | Status menjadi approved |
| 3 | Delivery SO | Delivery posted dan stock berkurang |
| 4 | Cek SO line | Delivered/outstanding qty akurat |
| 5 | Cek Stock Card | Movement sales delivery tampil |
| 6 | Create AR Invoice | Receivable open |
| 7 | Post AR Receipt sebagian | Receivable partial |
| 8 | Post AR Receipt sisa | Receivable paid |
| 9 | Cek Cash/Bank | Balance bertambah |
| 10 | Cek GL | Debit/credit balance |

### 2.3 Production Core

| Step | Test | Expected Result |
|---:|---|---|
| 1 | Import BOM/Work Center/Routing/WO | Preview valid dan commit berhasil |
| 2 | Edit BOM/Work Center/Routing | Data lama load dan update berhasil |
| 3 | Edit Work Order draft | Update berhasil |
| 4 | Edit Work Order non-draft | Ditolak |
| 5 | Allocate Work Order | Component reserved |
| 6 | Issue Material | Component stock out |
| 7 | Receive Finished Good | Finished good stock in |
| 8 | Issue + Receive combined | Atomic; kalau gagal rollback |
| 9 | Cek Stock Card | Semua movement production tampil |

## 3. Guardrail yang Tidak Boleh Dilepas

1. Sidebar bukan security. Direct URL tetap harus dicek permission.
2. Tombol bukan status guard. Service tetap harus menolak status invalid.
3. Semua posting tanggal lama wajib dicek period close.
4. Semua transaksi inventory wajib bisa diaudit di Stock Card.
5. Semua transaksi finance wajib bisa diaudit di GL Entries.
6. Semua flow tenant-owned wajib memakai company/site active context.
7. Semua nomor dokumen otomatis wajib tetap bisa dioverride manual jika user mengisi nomor sendiri.

## 4. Next Core Backlog

| Priority | Item | Target |
|---:|---|---|
| 1 | Purchasing E2E UAT | Pastikan PO sampai payment balance |
| 2 | Sales E2E UAT | Pastikan SO sampai receipt balance |
| 3 | Cash/Bank report hardening | Audit cash/bank lebih mudah |
| 4 | Stock Card export | Inventory audit bisa dibawa ke Excel |
| 5 | GL export | Finance validation bisa dibawa ke Excel |
| 6 | Non-admin permission UAT | Hak akses lebih aman untuk user operasional |
| 7 | Master data cleanup | Dropdown dan mapping transaksi lebih stabil |

## 5. Catatan Deploy Hosting

Setelah pull source terbaru, pastikan SQL hosting minimum sudah dijalankan:

```text
database/hosting/2026-06-20_update_document_number_and_po_line_tax.sql
database/hosting/2026-06-20_update_purchase_receipt_core.sql
database/hosting/2026-06-20_update_sales_delivery_core.sql
database/hosting/2026-06-20_normalize_core_master_data.sql
database/hosting/2026-06-20_update_receipt_delivery_reversal_gl.sql
database/hosting/2026-06-21_update_po_uat_feedback.sql
database/hosting/2026-06-21_update_sales_order_uat_feedback.sql
```

Jika database belum menjalankan SQL di atas, UAT browser bisa gagal walaupun source code sudah benar.

## 6. Target Setelah Ini

Target berikutnya adalah memilih satu flow dan menyelesaikannya sampai `PASS`:

1. Purchasing E2E dulu jika fokus pembelian, inventory masuk, dan AP.
2. Sales E2E dulu jika fokus penjualan, inventory keluar, dan AR.
3. Production E2E setelah stock dan warehouse/location sudah rapi.

Jangan menambah modul baru sebelum minimal satu flow utama lulus end-to-end dan audit Stock Card + GL sudah sesuai.
