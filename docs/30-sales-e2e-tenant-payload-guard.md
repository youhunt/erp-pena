# Sales E2E Tenant and Payload Guard

Tanggal: 2026-06-22

## Tujuan

Patch ini menguatkan rantai transaksi:

`Sales Order -> Sales Delivery -> Sales Invoice -> A/R Receipt -> Cash/Bank -> GL`

## Guard yang Ditambahkan

1. Edit draft Sales Order tidak dapat memindahkan dokumen ke company/site lain.
2. Sales Delivery wajib memakai company dan site yang sama dengan Sales Order.
3. Sales Invoice dari delivery wajib memakai company dan site yang sama dengan Sales Delivery.
4. A/R Receipt wajib memakai company dan site yang sama dengan receivable dan Sales Invoice.
5. Source document menjadi sumber kebenaran untuk parent ID, customer, company, dan site.
6. Status, calculated total, posting user, dan posting timestamp tidak dapat dioverride oleh request/import.
7. Nomor Sales Invoice dari delivery diperiksa sebelum insert agar replay menghasilkan pesan duplicate yang jelas.
8. Draft Purchase Order juga memakai guard yang sama agar edit tidak dapat memindahkan tenant.

## Dampak Database

Tidak ada migration, tabel, atau SQL hosting baru.

## UAT Minimum

| No | Skenario | Expected Result |
|---:|---|---|
| 1 | Edit draft SO menggunakan company/site lain | Ditolak tanpa mengubah SO |
| 2 | Post delivery menggunakan SO company lain | Ditolak sebelum stock movement dibuat |
| 3 | Post delivery menggunakan SO site lain | Ditolak sebelum stock movement dibuat |
| 4 | Buat AR Invoice dari delivery site lain | Ditolak sebelum invoice/receivable/GL dibuat |
| 5 | Post receipt menggunakan receivable site lain | Ditolak sebelum cash/bank berubah |
| 6 | Kirim payload status/total palsu | Nilai authoritative dari service yang disimpan |
| 7 | Ulangi nomor Sales Invoice | Ditolak sebelum posting |
| 8 | Jalankan flow valid sampai A/R Receipt | Stock, receivable, cash/bank, dan GL berubah pada tenant yang sama |

## Verifikasi Teknis

```bash
php -l app/Services/Sales/SalesOrderService.php
php -l app/Services/Sales/SalesDeliveryService.php
php -l app/Services/Sales/SalesInvoiceService.php
php vendor/bin/phpunit
```
