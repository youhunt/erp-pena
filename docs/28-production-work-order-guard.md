# Production Work Order Guard

Tanggal: 2026-06-21

## Scope

Patch ini memperkuat transaksi Work Order tanpa menambah tabel atau mengubah layout:

- Action allocate, issue, receive, dan issue + receive wajib berada dalam company/site aktif.
- Work Order baru selalu disimpan sebagai `draft`.
- `Issue + Receive` diproses sebagai satu transaksi database.
- Exception database melakukan rollback dan dikembalikan sebagai pesan service yang jelas.

## Status Flow

| Status | Action yang Diizinkan |
|---|---|
| `draft`, `partial_allocated` | Allocate material |
| `allocated`, `partial_issued` | Issue material |
| `material_issued`, `partial_finished` | Receive finished good |
| `allocated`, `partial_issued`, `material_issued`, `partial_finished` | Issue + Receive sesuai sisa proses |
| `finished` | Tidak dapat diproses ulang |

Production dan Inventory period close guard tetap dijalankan sebelum stock posting.

## Tenant Guard

Controller mengirim `company_id` dan `site_id` aktif ke service. Service mengambil Work Order menggunakan scope tersebut. ID dari company atau site lain ditolak walaupun endpoint POST dipanggil langsung.

Ketika user memilih `All Sites`, service tetap membatasi berdasarkan company aktif.

## Atomic Combined Posting

Action `Issue + Receive` membuka transaksi luar. Posting issue material, receipt finished good, perubahan status, stock ledger, dan audit log berada di transaksi yang sama. Jika receipt gagal, issue material ikut rollback.

## SQL

Tidak ada migration atau SQL hosting baru untuk patch ini.

## UAT

Gunakan bagian Production Work Order Guard pada `docs/16-core-uat-status-checklist.md`, khususnya pengujian cross-tenant dan rollback combined posting.
