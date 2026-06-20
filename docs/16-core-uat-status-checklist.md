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

## 9. UAT Sign-off Notes

Gunakan bagian ini untuk mencatat hasil test per tanggal.

| Date | Tester | Area | Result | Notes |
|---|---|---|---|---|
|  |  |  |  |  |

---

## 10. Next Bug Fix Log

| Date | Bug / Feedback | Severity | Status | Fix Commit / Notes |
|---|---|---|---|---|
| 2026-06-20 | SO cancel dikira back | Medium | Fixed | Button label + reopen draft |
| 2026-06-20 | SO/PO auto-fill master gagal | High | Fixed | Flexible mapping + Select2 events |
| 2026-06-20 | PO import freight same amount error | High | Fixed | Freight summed per PO+Site |
| 2026-06-20 | PO receipt qty tidak update | High | Patched | Needs UAT |
| 2026-06-20 | DO stock out/SO qty update | High | Patched | Needs UAT |
