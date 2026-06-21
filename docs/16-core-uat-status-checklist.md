# PENA ERP Core UAT Status Checklist

Dokumen ini dipakai untuk testing manual agar UAT tidak acak. Isi kolom Result setiap kali test dilakukan.

Status values:

- `PASS`: sudah lulus test.
- `FAIL`: gagal dan perlu bug fix.
- `BLOCKED`: tidak bisa dites karena data/SQL/master belum siap.
- `NOT TESTED`: belum dites.

---

## 1. Environment & Hosting

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | `git pull origin main` | Source code terbaru berhasil ditarik | NOT TESTED |  |
| 2 | SQL document number dijalankan | Table `document_number_sequences` tersedia | NOT TESTED |  |
| 3 | SQL purchase receipt core dijalankan | Reversal fields ada di `purchase_receipt_lines` | NOT TESTED |  |
| 4 | SQL sales delivery core dijalankan | Reversal fields ada di `sales_delivery_lines` | NOT TESTED |  |
| 5 | Login admin | Admin bisa masuk dashboard | NOT TESTED |  |
| 6 | Active company/site | Company dan site aktif tampil benar | NOT TESTED |  |

---

## 2. Master Data Minimum

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Company master | Minimal 1 company aktif | NOT TESTED |  |
| 2 | Site master | Minimal 1 site aktif | NOT TESTED |  |
| 3 | Warehouse master | Minimal 1 warehouse aktif | NOT TESTED |  |
| 4 | Location master | Minimal 1 location aktif dan terhubung warehouse | NOT TESTED |  |
| 5 | Item master | Item punya code, name, UoM, purchase/sales price | NOT TESTED |  |
| 6 | Supplier master | Supplier bisa dipilih di PO | NOT TESTED |  |
| 7 | Customer master | Customer bisa dipilih di SO | NOT TESTED |  |
| 8 | Cash/bank account | Cash/bank account tersedia untuk payment/receipt | NOT TESTED |  |
| 9 | GL posting profile | Account posting profile tersedia | NOT TESTED |  |

---

## 3. Purchasing End-to-End

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Create PO manual tanpa isi PO No | Nomor PO otomatis muncul setelah save | NOT TESTED |  |
| 2 | Pilih Supplier | Supplier Name auto-fill | NOT TESTED |  |
| 3 | Pilih Item | Item Name, UoM, Price auto-fill | NOT TESTED |  |
| 4 | Isi line discount/VAT/WHT | Line total benar | NOT TESTED |  |
| 5 | Save PO | PO status draft | NOT TESTED |  |
| 6 | Submit PO | PO status submitted | NOT TESTED |  |
| 7 | Approve PO | PO status approved | NOT TESTED |  |
| 8 | Create Receipt dari PO | Form receipt terbuka | NOT TESTED |  |
| 9 | Ubah Receive Now | Qty tetap sesuai input setelah post | NOT TESTED |  |
| 10 | Post Receipt & Update Stock | Receipt posted dan stock bertambah | NOT TESTED |  |
| 11 | Cek PO line | `qty_received` dan `qty_outstanding` update | NOT TESTED |  |
| 12 | Cek Stock Card | Ada movement purchase receipt | NOT TESTED |  |
| 13 | Create AP Invoice dari Receipt | Form invoice terbuka | NOT TESTED |  |
| 14 | Kosongkan Invoice No | PI otomatis dibuat | NOT TESTED |  |
| 15 | Post Invoice & Open A/P | AP payable open | NOT TESTED |  |
| 16 | Post AP Payment sebagian | Payable status partial | NOT TESTED |  |
| 17 | Post AP Payment sisa | Payable/invoice status paid | NOT TESTED |  |
| 18 | Cek Cash/Bank | Cash/bank balance berkurang | NOT TESTED |  |
| 19 | Cek GL Entries | GL balance dan trial balance muncul | NOT TESTED |  |

---

## 4. Sales End-to-End

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Create SO manual tanpa isi SO No | Nomor SO otomatis muncul setelah save | NOT TESTED |  |
| 2 | Pilih Customer | Customer Name auto-fill | NOT TESTED |  |
| 3 | Pilih Item | Item Name, UoM, Price auto-fill | NOT TESTED |  |
| 4 | Save SO | SO status draft | NOT TESTED |  |
| 5 | Submit SO | SO status submitted | NOT TESTED |  |
| 6 | Approve SO | SO status approved | NOT TESTED |  |
| 7 | Create Delivery dari SO | Form delivery terbuka | NOT TESTED |  |
| 8 | Pilih Warehouse/Location | Available stock tampil | NOT TESTED |  |
| 9 | Isi Deliver Now | Qty tidak boleh melebihi outstanding/available | NOT TESTED |  |
| 10 | Post Delivery & Update Stock | Delivery posted dan stock berkurang | NOT TESTED |  |
| 11 | Cek SO line | `qty_delivered` dan `qty_outstanding` update | NOT TESTED |  |
| 12 | Cek Stock Card | Ada movement sales delivery | NOT TESTED |  |
| 13 | Create AR Invoice dari DO | Form invoice terbuka | NOT TESTED |  |
| 14 | Kosongkan Invoice No | SI otomatis dibuat | NOT TESTED |  |
| 15 | Post Invoice & Open A/R | AR receivable open | NOT TESTED |  |
| 16 | Post AR Receipt sebagian | Receivable status partial | NOT TESTED |  |
| 17 | Post AR Receipt sisa | Receivable/invoice status paid | NOT TESTED |  |
| 18 | Cek Cash/Bank | Cash/bank balance bertambah | NOT TESTED |  |
| 19 | Cek GL Entries | GL balance dan trial balance muncul | NOT TESTED |  |

---

## 5. Import SO/PO

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Download SO template | File xlsx berhasil diunduh | NOT TESTED |  |
| 2 | Upload SO file valid | Preview valid tanpa error | NOT TESTED |  |
| 3 | Commit SO import | SO dibuat sesuai file | NOT TESTED |  |
| 4 | Download PO template | File xlsx berhasil diunduh | NOT TESTED |  |
| 5 | Upload PO file valid | Preview valid tanpa error | NOT TESTED |  |
| 6 | PO No sama, site beda | Tidak dianggap duplicate | NOT TESTED |  |
| 7 | Freight beda antar row | Tidak error same freight amount | NOT TESTED |  |
| 8 | Commit PO import | PO dibuat sesuai file | NOT TESTED |  |

---

## 6. Reversal & Cancellation

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Cancel SO draft/submitted | Status cancelled | NOT TESTED |  |
| 2 | Reopen cancelled SO | Status kembali draft | NOT TESTED |  |
| 3 | Reverse Purchase Receipt | Stock keluar dan PO qty recalculated | NOT TESTED |  |
| 4 | Reverse Sales Delivery | Stock masuk dan SO qty recalculated | NOT TESTED |  |
| 5 | Cancel AR Receipt | Invoice balance terbuka kembali | NOT TESTED |  |
| 6 | Cancel AP Payment | Invoice balance terbuka kembali | NOT TESTED |  |
| 7 | Cancel AR Invoice | Receivable cancelled dan delivery terbuka jika applicable | NOT TESTED |  |
| 8 | Cancel AP Invoice | Payable cancelled dan receipt terbuka jika applicable | NOT TESTED |  |

---

## 7. Inventory Audit

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Stock Balance setelah receipt | Qty on hand bertambah | NOT TESTED |  |
| 2 | Stock Balance setelah delivery | Qty on hand berkurang | NOT TESTED |  |
| 3 | Stock Card filter item | Hanya item terpilih tampil | NOT TESTED |  |
| 4 | Stock Card filter warehouse/location | Movement sesuai lokasi | NOT TESTED |  |
| 5 | Running qty | Opening + in - out = ending | NOT TESTED |  |
| 6 | Running value | Opening value + value in - value out = ending value | NOT TESTED |  |

---

## 8. GL / Finance Audit

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | GL Entries page | Total debit/credit/difference tampil | NOT TESTED |  |
| 2 | Filter GL by date | Journal sesuai periode | NOT TESTED |  |
| 3 | Filter GL by source module | Journal source sesuai filter | NOT TESTED |  |
| 4 | Difference = 0 | Alert hijau balance | NOT TESTED |  |
| 5 | Trial balance summary | Account debit/credit/balance tampil | NOT TESTED |  |
| 6 | Manual GL balanced | Posting berhasil | NOT TESTED |  |
| 7 | Manual GL unbalanced | Posting ditolak | NOT TESTED |  |

---

## 9. Transaction Status Guard

Jalankan skenario ini juga dengan URL/action POST langsung. Tombol yang tersembunyi bukan pengganti validasi service.

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Create SO/PO dengan payload status non-draft | Dokumen tetap dibuat sebagai draft | NOT TESTED |  |
| 2 | Edit PO draft | Perubahan berhasil disimpan | NOT TESTED |  |
| 3 | Edit PO submitted melalui URL langsung | Ditolak dengan pesan hanya draft yang dapat diedit | NOT TESTED |  |
| 4 | Edit PO approved melalui URL langsung | Ditolak oleh controller dan service | NOT TESTED |  |
| 5 | Submit SO/PO dua kali | Submit kedua ditolak dengan status saat ini | NOT TESTED |  |
| 6 | Approve SO/PO yang bukan submitted | Ditolak dengan transisi status yang jelas | NOT TESTED |  |
| 7 | Proses SO/PO cancelled | Ditolak; SO hanya dapat diproses lagi melalui Reopen as Draft | NOT TESTED |  |
| 8 | Reopen SO yang bukan cancelled | Ditolak oleh service | NOT TESTED |  |
| 9 | Post ulang nomor Delivery Order yang sama | Ditolak sebelum posting stock | NOT TESTED |  |
| 10 | Post ulang nomor Purchase Receipt yang sama | Ditolak sebelum posting stock | NOT TESTED |  |
| 11 | Buat invoice dari Delivery Order reversed/invoiced | Ditolak; hanya status posted yang boleh | NOT TESTED |  |
| 12 | Buat invoice dari Purchase Receipt reversed/invoiced | Ditolak; hanya status posted yang boleh | NOT TESTED |  |
| 13 | Reverse Delivery Order yang sudah invoiced | Ditolak; invoice aktif harus dibatalkan lebih dahulu | NOT TESTED |  |
| 14 | Reverse Purchase Receipt yang sudah invoiced | Ditolak; invoice aktif harus dibatalkan lebih dahulu | NOT TESTED |  |
| 15 | Post payment/receipt ke invoice paid/cancelled | Ditolak oleh controller dan SettlementService | NOT TESTED |  |
| 16 | Cancel invoice dengan payment/receipt posted | Ditolak dan diminta cancel settlement lebih dahulu | NOT TESTED |  |
| 17 | Cancel payment/receipt posted | Settlement cancelled dan saldo invoice dihitung ulang | NOT TESTED |  |
| 18 | Cancel payment/receipt untuk kedua kali | Ditolak sebagai already cancelled | NOT TESTED |  |
| 19 | Cancel invoice setelah semua settlement dibatalkan | Berhasil jika invoice kembali open dan periode masih open | NOT TESTED |  |
| 20 | Action status pada periode closed | Ditolak oleh period close guard | NOT TESTED |  |

---

## 10. Production Work Order Guard

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Create Work Order dengan payload status non-draft | Work Order tetap dibuat sebagai draft | NOT TESTED |  |
| 2 | Allocate Work Order tenant aktif | Reservation komponen dan status berhasil diperbarui | NOT TESTED |  |
| 3 | Tembak allocate/issue/receive untuk WO company lain | Ditolak sebagai tidak ditemukan pada company/site aktif | NOT TESTED |  |
| 4 | Tembak action WO site lain saat site aktif dipilih | Ditolak oleh service | NOT TESTED |  |
| 5 | Allocate WO yang sudah allocated/finished | Ditolak dengan status saat ini | NOT TESTED |  |
| 6 | Issue material sebelum allocation | Ditolak tanpa stock movement | NOT TESTED |  |
| 7 | Receive finished good sebelum material issued | Ditolak tanpa stock movement | NOT TESTED |  |
| 8 | Issue + Receive valid | Material keluar dan finished good masuk dalam satu transaksi | NOT TESTED |  |
| 9 | Paksa Receive gagal setelah Issue dalam combined action | Seluruh issue/receive rollback | NOT TESTED |  |
| 10 | Action WO pada production/inventory period closed | Ditolak tanpa perubahan stock/status | NOT TESTED |  |

---

## 11. UAT Sign-off Notes

Gunakan bagian ini untuk mencatat hasil test per tanggal.

| Date | Tester | Area | Result | Notes |
|---|---|---|---|---|
|  |  |  |  |  |

---

## 12. Next Bug Fix Log

| Date | Bug / Feedback | Severity | Status | Fix Commit / Notes |
|---|---|---|---|---|
| 2026-06-20 | SO cancel dikira back | Medium | Fixed | Button label + reopen draft |
| 2026-06-20 | SO/PO auto-fill master gagal | High | Fixed | Flexible mapping + Select2 events |
| 2026-06-20 | PO import freight same amount error | High | Fixed | Freight summed per PO+Site |
| 2026-06-20 | PO receipt qty tidak update | High | Patched | Needs UAT |
| 2026-06-20 | DO stock out/SO qty update | High | Patched | Needs UAT |
| 2026-06-20 | Direct URL/replay action dapat melewati kondisi tombol | High | Patched | Service-layer transaction status guard; needs UAT |
| 2026-06-21 | Work Order action belum tenant-scoped dan combined posting belum atomic | High | Patched | Production service tenant/transaction guard; needs UAT |
| 2026-06-22 | Rantai PO-Receipt-AP-Payment masih dapat menerima payload tenant/status yang tidak konsisten di service boundary | High | Patched | Strict company/site and authoritative payload guard; needs UAT |

---

## 13. Purchasing E2E Tenant and Payload Guard

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Post receipt memakai PO company lain melalui request langsung | Ditolak sebelum stock/header receipt dibuat | NOT TESTED |  |
| 2 | Post receipt memakai PO site lain | Ditolak sebelum stock/header receipt dibuat | NOT TESTED |  |
| 3 | Buat AP Invoice dari receipt company/site lain | Ditolak sebelum invoice, payable, dan GL dibuat | NOT TESTED |  |
| 4 | Post A/P Payment ke payable company/site lain | Ditolak sebelum cash/bank berubah | NOT TESTED |  |
| 5 | Inject status ke payload receipt/invoice/payment | Status tetap ditentukan service | NOT TESTED |  |
| 6 | Inject total PO yang berbeda dari line | Total hasil kalkulasi service yang disimpan | NOT TESTED |  |
| 7 | Gunakan cash/bank account site lain | Ditolak sebagai account tidak valid | NOT TESTED |  |
| 8 | Replay nomor Purchase Invoice yang sama | Ditolak sebelum posting | NOT TESTED |  |

---

## 14. Sales E2E Tenant and Payload Guard

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Edit draft SO dengan company/site berbeda | Ditolak tanpa mengubah SO | NOT TESTED |  |
| 2 | Post Delivery memakai SO company lain | Ditolak sebelum stock/header delivery dibuat | NOT TESTED |  |
| 3 | Post Delivery memakai SO site lain | Ditolak sebelum stock/header delivery dibuat | NOT TESTED |  |
| 4 | Buat AR Invoice dari Delivery company/site lain | Ditolak sebelum invoice, receivable, dan GL dibuat | NOT TESTED |  |
| 5 | Post A/R Receipt ke receivable company/site lain | Ditolak sebelum cash/bank berubah | NOT TESTED |  |
| 6 | Inject status atau total ke SO/Delivery/Invoice/Receipt | Nilai authoritative service yang disimpan | NOT TESTED |  |
| 7 | Gunakan cash/bank account site lain | Ditolak sebagai account tidak valid | NOT TESTED |  |
| 8 | Replay nomor Sales Invoice yang sama | Ditolak sebelum posting | NOT TESTED |  |

### Bug Fix Log Tambahan

| Date | Bug / Feedback | Severity | Status | Fix Commit / Notes |
|---|---|---|---|---|
| 2026-06-22 | Rantai SO-Delivery-AR-Receipt masih menerima payload tenant/status yang tidak konsisten di service boundary | High | Patched | Strict tenant and authoritative payload guard; needs UAT |

---

## 15. Atomic Inventory and GL Posting

| No | Test Case | Expected Result | Result | Notes |
|---:|---|---|---|---|
| 1 | Post Purchase Receipt bernilai dengan posting profile lengkap | Receipt, stock, qty PO, dan GL berhasil dalam satu transaksi | NOT TESTED |  |
| 2 | Post Purchase Receipt bernilai saat account posting profile tidak valid | Seluruh proses ditolak; receipt, stock, qty PO, dan GL tidak berubah | NOT TESTED |  |
| 3 | Post Sales Delivery dengan COGS dan posting profile lengkap | Delivery, stock, qty SO, dan GL berhasil dalam satu transaksi | NOT TESTED |  |
| 4 | Post Sales Delivery dengan COGS saat account posting profile tidak valid | Seluruh proses ditolak; delivery, stock, qty SO, dan GL tidak berubah | NOT TESTED |  |
| 5 | Post Receipt/Delivery dengan nilai persediaan atau COGS nol | Posting berhasil tanpa `gl_entry_id` dan audit menjelaskan jurnal tidak diperlukan | NOT TESTED |  |
| 6 | Reverse dokumen lama yang tidak memiliki `gl_entry_id` | Reversal stock tetap berjalan untuk kompatibilitas data lama | NOT TESTED |  |
| 7 | Reverse dokumen yang memiliki `gl_entry_id`, tetapi detail jurnal hilang | Ditolak tanpa perubahan stock, status, atau qty order | NOT TESTED |  |
| 8 | Reverse dokumen dengan jurnal asal lengkap | Stock, qty order, status, dan reversal GL berhasil atomik | NOT TESTED |  |

### Bug Fix Log Atomic Posting

| Date | Bug / Feedback | Severity | Status | Fix Commit / Notes |
|---|---|---|---|---|
| 2026-06-22 | Receipt/Delivery tetap commit stock ketika posting GL gagal | Critical | Patched | GL failure now propagates to the outer database transaction; needs UAT |
