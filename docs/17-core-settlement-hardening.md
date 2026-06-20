# Core Settlement Hardening

Tanggal update: 2026-06-20

Dokumen ini mencatat hardening ERP core untuk settlement finance:

- A/R Receipt
- A/P Payment
- Cancel A/R Receipt
- Cancel A/P Payment
- Cash/Bank reversal
- Invoice payable/receivable balance recalculation

Tujuan utama update ini adalah membuat saldo invoice, payable/receivable, dan cash/bank lebih aman saat terjadi partial payment, full payment, atau pembatalan payment/receipt.

---

## 1. Problem yang Dicegah

| Risiko | Dampak | Solusi |
|---|---|---|
| Payment dihitung hanya tambah/kurang dari data terakhir | Saldo invoice bisa drift jika ada data lama/cancel | Recalculate paid/outstanding dari semua transaksi `posted` |
| Payment/receipt dibuat untuk site berbeda | Data tenant/site tidak konsisten | Validasi site payable/receivable vs transaksi |
| Payment/receipt nomor sama | Duplicate document number | Validasi unique number per company/site dan non-cancelled status |
| Payment/receipt di periode cashbank tertutup | Cash/bank bisa berubah di periode locked | Period close guard untuk `cashbank` ikut dicek |
| Fully paid invoice masih bisa dibayar lagi | Overpayment tidak terkontrol | Outstanding <= 0 ditolak |
| Cancel payment/receipt setelah reconciliation | Bank reconciliation bisa rusak | Cancel ditolak jika cash/bank entry sudah reconciled |

---

## 2. Perubahan Service

File utama:

```text
app/Services/Finance/SettlementService.php
```

### A/P Payment

Saat posting A/P payment:

1. Validasi company, payable, dan payment number.
2. Validasi payable ada dan company/site sesuai.
3. Validasi payment number belum pernah dipakai pada company/site yang sama.
4. Validasi amount lebih besar dari 0.
5. Validasi amount tidak melebihi outstanding.
6. Validasi AP period open.
7. Validasi Cash/Bank period open.
8. Posting A/P payment.
9. Posting cash/bank out.
10. Recalculate payable dan purchase invoice dari semua A/P payment berstatus `posted`.

### A/R Receipt

Saat posting A/R receipt:

1. Validasi company, receivable, dan receipt number.
2. Validasi receivable ada dan company/site sesuai.
3. Validasi receipt number belum pernah dipakai pada company/site yang sama.
4. Validasi amount lebih besar dari 0.
5. Validasi amount tidak melebihi outstanding.
6. Validasi AR period open.
7. Validasi Cash/Bank period open.
8. Posting A/R receipt.
9. Posting cash/bank in.
10. Recalculate receivable dan sales invoice dari semua A/R receipt berstatus `posted`.

---

## 3. Balance Recalculation Rule

### Payable

```text
paid_amount = SUM(ap_payments.payment_amount WHERE status = posted)
outstanding_amount = invoice_amount - paid_amount
status = paid jika outstanding <= 0
status = partial jika paid_amount > 0 dan outstanding > 0
status = open jika paid_amount = 0
```

### Receivable

```text
paid_amount = SUM(ar_receipts.receipt_amount WHERE status = posted)
outstanding_amount = invoice_amount - paid_amount
status = paid jika outstanding <= 0
status = partial jika paid_amount > 0 dan outstanding > 0
status = open jika paid_amount = 0
```

---

## 4. Cancel/Reversal Rule

Saat A/P Payment atau A/R Receipt dibatalkan:

1. Sistem cek payment/receipt belum cancelled.
2. Sistem cek cash/bank entry belum direkonsiliasi.
3. Sistem cek AP/AR period masih open.
4. Sistem cek cashbank period masih open.
5. Sistem membuat reversal cash/bank entry.
6. Payment/receipt diubah menjadi `cancelled`.
7. Payable/receivable dihitung ulang dari transaksi `posted` yang tersisa.

---

## 5. UAT Checklist Tambahan

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Post AP payment full | AP payable dan purchase invoice menjadi `paid` | NOT TESTED |
| 2 | Post AP payment partial | AP payable dan purchase invoice menjadi `partial` | NOT TESTED |
| 3 | Cancel AP payment | Cash/bank reversal dibuat dan invoice balance terbuka ulang | NOT TESTED |
| 4 | Post AR receipt full | AR receivable dan sales invoice menjadi `paid` | NOT TESTED |
| 5 | Post AR receipt partial | AR receivable dan sales invoice menjadi `partial` | NOT TESTED |
| 6 | Cancel AR receipt | Cash/bank reversal dibuat dan invoice balance terbuka ulang | NOT TESTED |
| 7 | Coba bayar invoice paid | Sistem menolak karena outstanding sudah 0 | NOT TESTED |
| 8 | Coba pakai payment/receipt number sama | Sistem menolak duplicate document number | NOT TESTED |
| 9 | Coba cancel receipt/payment yang sudah reconciled | Sistem menolak cancellation | NOT TESTED |
| 10 | Cek GL Entries setelah payment/receipt | Debit/credit tetap balance | NOT TESTED |

---

## 6. Status

| Area | Status | Notes |
|---|---|---|
| A/P Payment posting | Patched | Recalculate from posted records |
| A/R Receipt posting | Patched | Recalculate from posted records |
| A/P Payment cancel | Patched | Reversal cash/bank + recalculate |
| A/R Receipt cancel | Patched | Reversal cash/bank + recalculate |
| Duplicate document guard | Patched | Per company/site and non-cancelled status |
| Cashbank period guard | Patched | Payment/receipt and cancellation |
| Automated test | Pending | Manual UAT required |
