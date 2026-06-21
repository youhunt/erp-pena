# Purchasing E2E Tenant and Payload Guard

Tanggal: 2026-06-22

## Tujuan

Patch ini menguatkan rantai transaksi berikut pada service layer:

`Purchase Order -> Purchase Receipt -> Purchase Invoice -> A/P Payment -> Cash/Bank -> GL`

## Guard yang Ditambahkan

1. Purchase Receipt wajib memakai company dan site yang sama dengan Purchase Order.
2. Purchase Invoice dari receipt wajib memakai company dan site yang sama dengan Purchase Receipt.
3. A/P Payment wajib memakai company dan site yang sama dengan payable dan Purchase Invoice.
4. Guard site membandingkan nilai secara ketat. Site kosong tidak dapat dipakai untuk melewati dokumen yang memiliki site.
5. Cash/Bank account hanya dapat dipakai jika bersifat company-wide atau sesuai site transaksi.
6. Field sistem seperti status, source document, calculated total, posting user, dan posting timestamp tidak dapat dioverride oleh payload request.
7. Nomor Purchase Invoice dari receipt diperiksa sebelum insert agar duplicate replay menghasilkan pesan yang jelas.

Guard yang sama pada `SettlementService` juga melindungi sisi A/R Receipt karena settlement A/P dan A/R menggunakan boundary yang sama.

## Dampak Database

Tidak ada migration atau tabel baru. Patch hanya memperketat validasi dan penyusunan payload pada service layer.

## UAT Minimum

| No | Skenario | Expected Result |
|---:|---|---|
| 1 | Post receipt dengan PO company lain | Ditolak sebelum stock movement dibuat |
| 2 | Post receipt dengan PO site lain | Ditolak sebelum stock movement dibuat |
| 3 | Buat AP Invoice dari receipt site lain | Ditolak sebelum invoice/payable/GL dibuat |
| 4 | Post payment dengan payable site lain | Ditolak sebelum cash/bank berubah |
| 5 | Kirim payload `status=cancelled` saat posting receipt/invoice/payment | Status sistem tetap `posted` atau `open` sesuai proses |
| 6 | Kirim total PO palsu melalui request | Total disimpan dari kalkulasi line dan commercial header |
| 7 | Pakai cash/bank account milik site lain | Ditolak sebagai account tidak valid |
| 8 | Ulangi nomor Purchase Invoice | Ditolak dengan pesan duplicate sebelum posting |

## Verifikasi Teknis

```bash
php -l app/Services/Support/TransactionDocumentGuard.php
php vendor/bin/phpunit
```
