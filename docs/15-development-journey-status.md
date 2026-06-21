# PENA ERP Development Journey & Status

Dokumen ini mencatat perjalanan development PENA ERP sampai status terakhir. Tujuannya agar setiap perubahan dari hasil coding, Codex/AI implementation, dan UAT manual tidak hilang.

Tanggal snapshot: 2026-06-20  
Repository: `youhunt/erp-pena`  
Mode development terakhir: direct update ke `main` atas permintaan owner project.

---

## 1. Ringkasan Executive

PENA ERP sudah berkembang dari blueprint/pondasi awal menjadi aplikasi ERP CodeIgniter 4 yang memiliki alur transaksi inti:

- Purchase Order
- Purchase Receipt / PO Receipt
- Purchase Invoice / A/P Payable
- A/P Payment
- Sales Order
- Delivery Order
- Sales Invoice / A/R Receivable
- A/R Receipt
- Inventory Stock In/Out
- Stock Card
- GL Entry dan validation summary
- Import Excel SO/PO
- AI/OCR foundation

ERP belum dinyatakan production-ready penuh. Status saat ini layak untuk UAT internal intensif dan demo terbatas, tetapi masih perlu penyempurnaan payment reversal, GL validation, stock audit, security permission, dan report sebelum dijual sebagai sistem produksi customer.

---

## 2. Persentase Readiness Saat Ini

Persentase berikut adalah estimasi readiness berbasis implementasi + patch + UAT manual, bukan automated test coverage.

| Area | Status | Readiness |
|---|---|---:|
| CI4 + Shield + Skote foundation | Sudah ada dan berjalan | 80% |
| Multi company / multi site | Active tenant berjalan, permission masih perlu UAT | 75% |
| Master data setup | CRUD baseline ada, field mapping perlu diselaraskan lagi | 70% |
| Document numbering otomatis | Service tersedia, wajib SQL table di hosting | 85% |
| Core transaction status guard | Service-layer guard sudah diperkuat, perlu UAT manual | 75% |
| SO manual + import | Sudah dipatch dari UAT | 75% |
| PO manual + import | Sudah dipatch dari UAT | 75% |
| Purchase Receipt | Posting, stock in, recalc PO qty sudah diperkuat | 70% |
| Sales Delivery | Posting, stock out, recalc SO qty sudah diperkuat | 65% |
| AP Invoice | Receipt to invoice + auto PI numbering | 60% |
| AR Invoice | Delivery to invoice + auto SI numbering | 60% |
| AP Payment | Auto APP numbering + settlement flow | 55% |
| AR Receipt | Auto ARR numbering + settlement flow | 55% |
| Inventory stock card | Qty/value movement audit sudah tampil | 65% |
| GL validation | Debit/credit/difference + trial balance summary | 60% |
| Production Work Order | CRUD/import tersedia; tenant/status/atomic posting guard diperkuat | 60% |
| AI/OCR document | Foundation ada, belum full UAT | 45% |
| Reporting enterprise | Baru baseline | 35% |
| Production readiness | Belum full | 40% |
| Demo readiness | Cukup untuk demo internal/terbatas | 60% |
| UAT readiness | Siap UAT bertahap | 65% |

---

## 3. Kontribusi Development

| Sumber | Peran | Hasil |
|---|---|---|
| Owner / user testing | Melakukan real UAT, memberi screenshot, menemukan business rule asli | Bug nyata ditemukan cepat dan dipatch langsung |
| Codex / implementation awal | Membangun baseline repo ERP, CI4, Shield, Skote, modul awal | Repository menjadi aplikasi ERP berjalan |
| AI development lanjut | Audit repo, patch runtime bug, hardening service, auto numbering, SQL hosting, form UX | Flow ERP mulai tersambung end-to-end |
| Feedback business user | Aturan PO/SO, freight, discount, site key, cancel/reopen | Logic transaksi mengikuti kebutuhan nyata |

---

## 4. Development Milestone

### Milestone 1 - Foundation

| Komponen | Status | Catatan |
|---|---|---|
| CodeIgniter 4 app | Done | Appstarter CI4 digunakan |
| Shield auth | Done | Login dan protected route tersedia |
| Skote layout | Done | Layout admin tersedia |
| Sidebar/menu ERP | Partial | Menu aktif, permission masih perlu UAT granular |
| Company/site tenant | Partial | Active company/site sudah ada |
| Seeder baseline | Partial | Data demo/master tersedia, perlu review customer |
| Documentation | Partial | Docs awal tersedia dan terus diupdate |

### Milestone 2 - Core Utility

| Komponen | Status | Catatan |
|---|---|---|
| `TenantScope` helper | Done | Untuk query/payload tenant-owned |
| `DocumentNumberService` | Done | Sequence per transaction/company/site/period |
| `pena:health` | Done | Local readiness check |
| `pena:docno` | Done | Preview/generate document number via CLI |
| Hosting SQL manual | Partial | Beberapa SQL sudah dibuat untuk hosting phpMyAdmin |

### Milestone 3 - Purchase Core

| Flow | Status | Catatan |
|---|---|---|
| Create PO manual | Partial | Auto supplier/item fill sudah dipatch |
| Import PO | Partial | PO+Site grouping, freight relaxed, line tax/discount |
| Approve PO | Partial | Transisi hanya submitted ke approved; perlu UAT role |
| Edit PO | Partial | Hanya draft tanpa received quantity; perlu UAT URL langsung |
| PO Receipt | Partial | Hanya PO eligible; posting ulang ditolak dan stock in/recalc tersedia |
| Reverse PO Receipt | Partial | Field tracking reversal disiapkan |
| AP Invoice from Receipt | Partial | Auto PI numbering tersedia |
| AP Payment | Partial | Auto APP numbering tersedia |

### Milestone 4 - Sales Core

| Flow | Status | Catatan |
|---|---|---|
| Create SO manual | Partial | Customer/item auto fill dan table scroll dipatch |
| Import SO | Partial | Site lookup dan form UX dipatch |
| Approve SO | Partial | Transisi hanya submitted ke approved; perlu UAT role |
| Reopen cancelled SO | Done | Cancel tidak lagi membingungkan, SO bisa reopen draft |
| Delivery Order | Partial | Posting ulang ditolak; stock out dan SO qty recalc sudah dipatch |
| Reverse Delivery | Partial | Field tracking reversal disiapkan |
| AR Invoice from Delivery | Partial | Auto SI numbering tersedia |
| AR Receipt | Partial | Auto ARR numbering tersedia |

### Milestone 5 - Inventory + GL Audit

| Modul | Status | Catatan |
|---|---|---|
| Stock Balance | Partial | Sudah ada halaman |
| Stock Card | Partial | Qty/value in-out dan running balance tampil |
| Stock Adjustment | Partial | Shortcut ditambahkan dari stock card |
| Inventory Transfer | Partial | Baseline ada |
| GL Entries | Partial | Validation cards + trial balance summary ditambahkan |
| Trial Balance | Partial | Summary per account ada di halaman GL Entries |

---

## 5. Bug / Feedback UAT yang Sudah Ditangani

| No | Temuan | Solusi | Status |
|---:|---|---|---|
| 1 | PO boleh sama nomor jika site berbeda | Duplicate/grouping berbasis PO No + Site | Done |
| 2 | Supplier dipilih tapi nama kosong | Flexible supplier mapping + Select2 event | Done |
| 3 | Item dipilih tapi nama/UoM/price kosong | Flexible item mapping + Select2 event | Done |
| 4 | PO line perlu discount/VAT/WHT | Kolom dan service PO line ditambah | Done |
| 5 | Freight tidak perlu sama antar line | Freight tidak lagi divalidasi sama; dijumlahkan per PO | Done |
| 6 | SO customer/item auto-fill gagal | Mapping customer/item diperluas | Done |
| 7 | Kolom SO/PO kepotong | Fixed-width table + horizontal scroll | Done |
| 8 | Cancel SO dikira Back | Label dipisah `Back to List` dan `Cancel SO` | Done |
| 9 | SO cancelled perlu aktif lagi | Tambah `Reopen as Draft` | Done |
| 10 | PO Receipt qty balik/tidak update | Service receipt recalculate PO qty from posted receipts | Patched, perlu UAT |
| 11 | DO perlu update SO qty dan stock | Delivery service stock out + recalc SO qty | Patched, perlu UAT |
| 12 | Invoice number tidak boleh selalu manual | Auto SI/PI numbering | Done |
| 13 | Payment/receipt number tidak boleh selalu manual | Auto ARR/APP numbering | Done |
| 14 | GL harus bisa divalidasi | GL validation cards + trial balance summary | Done |
| 15 | Stock Card harus bisa audit value | Value in/out/running value ditambahkan | Done |
| 16 | Action transaksi bisa ditembak ulang lewat URL/status lama | Guard dipusatkan di service, controller dan tombol diselaraskan | Patched, perlu UAT |
| 17 | Action Work Order dapat memakai ID tenant lain dan Issue + Receive tidak atomic | Scope company/site diwajibkan dan combined posting dibungkus satu transaksi | Patched, perlu UAT |

---

## 6. SQL Hosting yang Sudah Disiapkan

| File | Fungsi | Wajib |
|---|---|---|
| `database/hosting/2026-06-20_update_document_number_and_po_line_tax.sql` | Membuat `document_number_sequences`, menambah field PO line discount/VAT/WHT | Ya |
| `database/hosting/2026-06-20_update_purchase_receipt_core.sql` | Menambah reversal tracking ke `purchase_receipt_lines` | Ya jika pakai reverse receipt |
| `database/hosting/2026-06-20_update_sales_delivery_core.sql` | Menambah reversal tracking ke `sales_delivery_lines` | Ya jika pakai reverse delivery |

Catatan penting:

- Auto numbering PO/SO/PR/DO/SI/PI/ARR/APP butuh table `document_number_sequences`.
- Jalankan SQL di phpMyAdmin setelah backup database.
- Kalau hosting memblokir `PREPARE`, gunakan fallback manual ALTER yang ada di bagian bawah SQL.

---

## 7. Core Flow yang Harus Diuji

### Purchasing

| Step | Flow | Expected |
|---:|---|---|
| 1 | Create / Import PO | PO created with correct site |
| 2 | Submit PO | Status submitted |
| 3 | Approve PO | Status approved |
| 4 | Receive PO | Receipt posted |
| 5 | Check Inventory | Stock increases |
| 6 | Check PO Line | `qty_received` and `qty_outstanding` updated |
| 7 | Create AP Invoice | Payable open |
| 8 | Post AP Payment | Payable partial/paid |
| 9 | Check Cash/Bank | Balance decreases |
| 10 | Check GL | Debit/credit balance |

### Sales

| Step | Flow | Expected |
|---:|---|---|
| 1 | Create / Import SO | SO created with correct customer/item |
| 2 | Submit SO | Status submitted |
| 3 | Approve SO | Status approved |
| 4 | Create Delivery | Delivery posted |
| 5 | Check Inventory | Stock decreases |
| 6 | Check SO Line | `qty_delivered` and `qty_outstanding` updated |
| 7 | Create AR Invoice | Receivable open |
| 8 | Post AR Receipt | Receivable partial/paid |
| 9 | Check Cash/Bank | Balance increases |
| 10 | Check GL | Debit/credit balance |

### Inventory + Finance Audit

| Step | Flow | Expected |
|---:|---|---|
| 1 | Open Stock Card | Qty/value running balance visible |
| 2 | Filter item/warehouse/location | Movement filtered correctly |
| 3 | Open GL Entries | Total debit/credit visible |
| 4 | Filter source module | Only selected source appears |
| 5 | Check difference | Difference should be 0 |
| 6 | Check trial balance | Account summary visible |

---

## 8. Current Known Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Hosting SQL not executed | Auto numbering or reversal fields fail | Run all required SQL hosting files |
| Master data incomplete | Transaction dropdown empty or price/UoM missing | Seed/import master data first |
| Cash/bank account missing | AR Receipt/AP Payment cannot post | Setup cash/bank master and posting profile |
| GL posting profile incomplete | GL skipped or warning appears | Configure posting profile per company |
| Stock balance empty | DO cannot stock out | Use purchase receipt or stock adjustment |
| Route permission not fully granular | Non-admin access may be inconsistent | Expand permission mapping and UAT role |
| UAT not systematic | Regression bugs missed | Follow checklist in this document and `docs/09-testing-checklist.md` |
| Status guard belum diuji lintas semua role | Action valid bisa tertahan atau action invalid bisa lolos | Jalankan skenario di `docs/16-core-uat-status-checklist.md` dan matriks `docs/21-transaction-status-guard.md` |

---

## 9. Next Development Priority

| Priority | Module | Reason | Target Progress Impact |
|---:|---|---|---:|
| 1 | UAT transaction status guard | Memastikan direct URL dan replay action ditolak oleh service | +5% |
| 2 | Cash/Bank report and reconciliation hardening | Payment/receipt audit finance | +6% |
| 3 | GL report export | Finance validation lebih mudah | +5% |
| 4 | Stock card export | Inventory audit lebih mudah | +4% |
| 5 | Permission hardening | Customer production security | +8% |
| 6 | Master data cleanup | Dropdown/value lebih stabil | +5% |
| 7 | AI/OCR conversion UAT | Nilai tambah produk | +5% |

---

## 10. Deployment Reminder

After every pull on server:

```bash
git pull origin main
```

If database structure changed, run the related SQL from `database/hosting/` in phpMyAdmin.

Minimum required SQL for current core flow:

```text
database/hosting/2026-06-20_update_document_number_and_po_line_tax.sql
database/hosting/2026-06-20_update_purchase_receipt_core.sql
database/hosting/2026-06-20_update_sales_delivery_core.sql
```

---

## 11. Definition of Done for Production Candidate

PENA ERP can be considered production candidate only when:

- Purchase end-to-end UAT passes.
- Sales end-to-end UAT passes.
- Inventory stock card matches stock balance.
- GL validation difference is 0 after all major transactions.
- AR/AP aging values match invoice/payment data.
- Non-admin role cannot access restricted modules.
- Required hosting SQL is applied.
- Backup/restore process is documented.
- Known risks have owner and resolution plan.

---

## 12. Update 2026-06-22 - Purchasing E2E Boundary Hardening

- Rantai PO, receipt, AP invoice, payable, payment, dan cash/bank sekarang memvalidasi company/site pada service layer.
- Source document menjadi sumber kebenaran untuk parent ID, supplier, company, dan site.
- Status dan total hasil kalkulasi tidak dapat dioverride oleh payload request/import.
- Cash/Bank account dibatasi ke company-wide account atau site transaksi yang sama.
- Tidak ada perubahan database pada patch ini.
- Detail guard dan skenario UAT tersedia di `docs/29-purchasing-e2e-tenant-payload-guard.md`.

---

## 13. Update 2026-06-22 - Sales E2E Boundary Hardening

- Edit draft SO/PO sekarang menolak perpindahan company/site pada service layer.
- Sales Delivery mengambil tenant, SO, dan customer dari Sales Order yang tervalidasi.
- Sales Invoice mengambil tenant, delivery, SO, dan customer dari Delivery Order yang tervalidasi.
- Status dan total hasil kalkulasi SO, Delivery, AR Invoice, dan A/R Receipt tidak dapat dioverride payload.
- A/R Receipt dan Cash/Bank telah memakai strict tenant guard dari settlement hardening sebelumnya.
- Tidak ada perubahan database pada patch ini.
- Detail tersedia di `docs/30-sales-e2e-tenant-payload-guard.md`.
