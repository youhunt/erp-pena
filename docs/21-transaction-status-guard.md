# PENA ERP Transaction Status Guard

Dokumen ini mendefinisikan aturan status untuk transaksi inti. Service layer adalah sumber kebenaran. Controller melakukan pre-check untuk respons yang lebih cepat, sedangkan view hanya menyembunyikan action yang tidak relevan.

Tanggal verifikasi: 2026-06-20

---

## 1. Prinsip

- Dokumen baru selalu dibuat sebagai `draft`, walaupun request mengirim status lain.
- Hanya dokumen `draft` yang boleh diubah bebas.
- Setiap action hanya menerima status asal yang telah ditentukan.
- Action replay, direct URL, dan request POST manual tetap ditolak oleh service.
- Period close guard tetap berlaku bersama status guard.
- Stock reversal harus diikuti GL reversal jika dokumen asal memiliki GL entry.

---

## 2. Sales Order

| Status | Action yang Diizinkan | Guard Utama |
|---|---|---|
| `draft` | submit, cancel | Create selalu menghasilkan draft |
| `submitted` | approve, cancel | Tidak boleh diedit bebas |
| `approved` | reserve atau delivery sesuai flow | Tidak boleh diedit/cancel bebas |
| `partial_reserved` | reserve lanjutan | Tidak boleh submit/approve ulang |
| `reserved`, `partial_delivered` | delivery sesuai outstanding | Tidak boleh edit atau reopen |
| `delivered`, `invoiced` | action downstream yang sesuai | Tidak boleh cancel/reprocess |
| `cancelled` | reopen sebagai draft | Reopen hanya melalui action yang tersedia |

Status transition Sales Order diverifikasi di `SalesOrderService`:

- `submit()` hanya dari `draft`.
- `approve()` hanya dari `submitted`.
- `reserve()` hanya dari `approved` atau `partial_reserved`.
- `cancel()` hanya dari `draft` atau `submitted`.
- `reopen()` hanya dari `cancelled`.

---

## 3. Purchase Order

| Status | Action yang Diizinkan | Guard Utama |
|---|---|---|
| `draft` | edit, submit, cancel | Edit ditolak jika pernah menerima quantity |
| `submitted` | approve, cancel | Edit melalui URL langsung ditolak |
| `approved`, `partial_received` | purchase receipt | Tidak boleh edit/cancel bebas |
| `received` | close | Tidak boleh receive ulang jika tidak ada outstanding |
| `closed`, `cancelled` | tidak ada action proses ulang | Belum ada fitur reopen PO |

Status transition Purchase Order diverifikasi di `PurchaseOrderService`:

- `update()` hanya untuk `draft` dan menolak PO yang sudah punya received qty.
- `submit()` hanya dari `draft`.
- `approve()` hanya dari `submitted`.
- `close()` hanya dari `received`.
- `cancel()` hanya dari `draft` atau `submitted`.

---

## 4. Fulfillment Guard

| Dokumen | Status | Action yang Diizinkan | Guard Utama |
|---|---|---|---|
| Delivery Order | `posted` | create sales invoice atau reverse jika belum ada invoice aktif | Service cek status posted |
| Delivery Order | `invoiced` | tidak boleh reverse atau invoiced ulang | Invoice aktif harus dibatalkan dulu |
| Delivery Order | `reversed` | tidak boleh diproses ulang | Reverse kedua ditolak |
| Purchase Receipt | `posted` | create purchase invoice atau reverse jika belum ada invoice aktif | Service cek status posted |
| Purchase Receipt | `invoiced` | tidak boleh reverse atau invoiced ulang | Invoice aktif harus dibatalkan dulu |
| Purchase Receipt | `reversed` | tidak boleh diproses ulang | Reverse kedua ditolak |

Nomor Delivery Order dan Purchase Receipt yang sudah tersimpan ditolak sebelum stock posting dijalankan kembali.

View detail juga sudah mengikuti status:

- Purchase Receipt detail hanya menampilkan `Create AP Invoice` dan `Reverse` jika status `posted`.
- Sales Delivery detail hanya menampilkan `Create Invoice` dan `Reverse` jika status `posted`.

---

## 5. Receipt / Delivery Reversal GL

Saat Purchase Receipt atau Sales Delivery di-reverse:

| Dokumen | Stock Reversal | GL Reversal |
|---|---|---|
| Purchase Receipt | Stock out dari lokasi receipt | Membalik jurnal inventory/GRNI jika `gl_entry_id` ada |
| Sales Delivery | Stock in ke lokasi delivery | Membalik jurnal COGS/inventory jika `gl_entry_id` ada |

Rule:

- Jika dokumen asal tidak punya `gl_entry_id`, reversal GL dilewati.
- Jika dokumen asal punya `gl_entry_id`, sistem membuat jurnal baru dengan debit/credit dibalik.
- ID jurnal reversal disimpan di `reversal_gl_entry_id`.
- Jika periode GL tertutup, reversal akan ditolak oleh `GeneralLedgerService`.

SQL kolom tambahan:

```text
database/hosting/2026-06-20_update_receipt_delivery_reversal_gl.sql
```

---

## 6. Invoice dan Settlement Guard

| Dokumen/Status | Action yang Diizinkan | Guard Utama |
|---|---|---|
| Invoice `open` | payment/receipt, cancel jika belum ada settlement posted | Cancel memeriksa settlement berstatus posted |
| Invoice `partial` | payment/receipt lanjutan | Tidak boleh cancel sebelum settlement dibatalkan |
| Invoice `paid` | tidak ada payment/cancel tambahan | Outstanding dan status diperiksa di service |
| Invoice `cancelled` | tidak ada action proses ulang | Payment/receipt baru ditolak |
| Settlement `posted` | cancel | Cancellation melakukan reversal dan recalculate invoice |
| Settlement `cancelled` | tidak ada action ulang | Cancel kedua ditolak |

### A/R Invoice

- `SalesInvoiceService::cancel()` hanya menerima invoice status `open`.
- Invoice yang sudah `cancelled` ditolak.
- Invoice dengan `paid_amount > 0` ditolak.
- Invoice dengan A/R Receipt status `posted` ditolak.
- Setelah cancel, delivery source dikembalikan ke `posted` bila invoice berasal dari delivery.

### A/P Invoice

- `PurchaseInvoiceService::cancel()` hanya menerima invoice status `open`.
- Invoice yang sudah `cancelled` ditolak.
- Invoice dengan `paid_amount > 0` ditolak.
- Invoice dengan A/P Payment status `posted` ditolak.
- Setelah cancel, receipt source dikembalikan ke `posted` bila invoice berasal dari receipt.

### Settlement

- `SettlementService::postArReceipt()` menolak receivable dengan outstanding <= 0.
- `SettlementService::postApPayment()` menolak payable dengan outstanding <= 0.
- Payment/receipt amount tidak boleh melebihi outstanding.
- Cancel settlement ditolak jika cash/bank entry sudah reconciled.
- Cancel settlement kedua ditolak.
- Invoice balance dihitung ulang dari transaksi settlement yang masih `posted`.

---

## 7. Pesan Error

Pesan penolakan harus menyebut action yang valid atau status saat ini, misalnya:

- `Only draft purchase order can be edited. Current status: approved.`
- `Only posted delivery order can be invoiced. Current status: reversed.`
- `Sales invoice has a posted receipt. Cancel the receipt first.`
- `A/P payment has already been cancelled.`

---

## 8. UAT Minimum

Gunakan bagian Transaction Status Guard pada `docs/16-core-uat-status-checklist.md`. Uji melalui tombol normal dan request URL langsung untuk memastikan service tetap menolak action yang tidak valid.

Minimum test:

1. Submit SO/PO dua kali.
2. Approve SO/PO saat status masih draft.
3. Edit PO yang sudah submitted/approved.
4. Reverse receipt yang sudah punya AP Invoice.
5. Reverse delivery yang sudah punya AR Invoice.
6. Cancel AR Invoice yang sudah punya posted receipt.
7. Cancel AP Invoice yang sudah punya posted payment.
8. Post receipt/payment ke invoice yang sudah paid.
9. Cancel payment/receipt dua kali.
10. Proses action pada periode closed.
11. Reverse receipt posted yang punya GL entry dan cek reversal GL muncul.
12. Reverse delivery posted yang punya GL entry dan cek reversal GL muncul.
13. Cek GL Entries difference tetap 0 setelah reversal.

---

## 9. Gap / Next Guard

| Area | Status | Catatan |
|---|---|---|
| Sales Order edit/update | Needs check | Jika fitur edit SO aktif, pastikan hanya draft seperti PO |
| Database unique index | Pending | Setelah data duplicate clean, bisa tambah unique index fisik |
| Automated test | Pending | Manual UAT dulu, lalu unit/feature test |

---

## 10. Status

| Area | Status |
|---|---|
| PO status guard | Verified in service |
| SO basic transition guard | Verified in service |
| Receipt reverse/invoice guard | Verified in service |
| Delivery reverse/invoice guard | Verified in service |
| Receipt reversal GL | Patched |
| Delivery reversal GL | Patched |
| AR invoice paid cancel guard | Verified in service |
| AP invoice paid cancel guard | Verified in service |
| Settlement outstanding/reconciliation guard | Verified/Patched previously |
| View action visibility | Verified for receipt, delivery, AR invoice, AP invoice |
| UAT checklist | Updated |
