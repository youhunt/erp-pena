# Receipt and Delivery Reversal GL

Tanggal update: 2026-06-20

Dokumen ini mencatat patch ERP core untuk memastikan reversal stock pada Purchase Receipt dan Sales Delivery ikut membuat reversal GL jika dokumen asal memiliki GL entry.

---

## 1. Scope

| Dokumen | Action | Hasil |
|---|---|---|
| Purchase Receipt | Reverse | Stock out, PO qty recalculated, dan reversal GL dibuat jika `gl_entry_id` ada |
| Sales Delivery | Reverse | Stock in, SO qty recalculated, dan reversal GL dibuat jika `gl_entry_id` ada |

---

## 2. Files Updated

| File | Update |
|---|---|
| `app/Services/Purchase/PurchaseReceiptService.php` | Membuat reversal GL saat reverse receipt |
| `app/Services/Sales/SalesDeliveryService.php` | Membuat reversal GL saat reverse delivery |
| `app/Models/PurchaseReceiptModel.php` | Allow field `reversal_gl_entry_id` |
| `app/Models/SalesDeliveryModel.php` | Allow field `reversal_gl_entry_id` |
| `app/Views/purchase/receipts/show.php` | Menampilkan Reversal GL |
| `app/Views/sales/deliveries/show.php` | Menampilkan Reversal GL |
| `database/hosting/2026-06-20_update_receipt_delivery_reversal_gl.sql` | SQL tambah kolom reversal GL |

---

## 3. Rule

- Jika dokumen asal tidak punya `gl_entry_id`, reversal GL dilewati.
- Jika dokumen asal punya `gl_entry_id`, sistem membaca semua baris GL asal.
- Debit dan credit dibalik ke jurnal baru.
- Jurnal baru disimpan sebagai `reversal_gl_entry_id`.
- Jika periode GL tertutup, reversal ditolak oleh `GeneralLedgerService`.

---

## 4. Required SQL

Jalankan setelah backup database:

```text
database/hosting/2026-06-20_update_receipt_delivery_reversal_gl.sql
```

Kolom yang ditambah:

```text
purchase_receipts.reversal_gl_entry_id
sales_deliveries.reversal_gl_entry_id
```

---

## 5. UAT Checklist

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Post Purchase Receipt dengan GL berhasil | `gl_entry_id` terisi | NOT TESTED |
| 2 | Reverse Purchase Receipt tersebut | `reversal_gl_entry_id` terisi | NOT TESTED |
| 3 | Cek GL reversal receipt | Debit/credit membalik jurnal receipt asal | NOT TESTED |
| 4 | Post Sales Delivery dengan GL berhasil | `gl_entry_id` terisi | NOT TESTED |
| 5 | Reverse Sales Delivery tersebut | `reversal_gl_entry_id` terisi | NOT TESTED |
| 6 | Cek GL reversal delivery | Debit/credit membalik jurnal delivery asal | NOT TESTED |
| 7 | Cek Stock Card | Movement reversal tetap muncul | NOT TESTED |
| 8 | Cek GL Entries validation | Difference tetap 0 | NOT TESTED |

---

## 6. Status

| Area | Status |
|---|---|
| Purchase Receipt reversal GL | Patched |
| Sales Delivery reversal GL | Patched |
| Header model allowed field | Patched |
| View display reversal GL | Patched |
| Hosting SQL | Added |
| UAT | Pending |
