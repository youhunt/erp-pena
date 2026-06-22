# Period Close Site-Scope Hardening

## Masalah yang Ditutup

Struktur lama menggunakan unique key `company_id + module_code + period`. Pada lingkungan multi-site, close periode untuk Site B dapat menemukan dan memperbarui record Site A. Endpoint reopen juga sebelumnya hanya menerima ID record sehingga service belum memvalidasi company dan site aktif.

## Aturan Baru

- `site_scope_id = 0` berarti close company-wide atau All Sites.
- `site_scope_id = site_id` berarti close hanya berlaku untuk site tersebut.
- Unique key menjadi `company_id + site_scope_id + module_code + period`.
- Close yang sudah berstatus `closed` tidak dapat dijalankan ulang.
- Hanya record `closed` yang dapat di-reopen.
- Reopen wajib cocok dengan company dan site scope sumber.
- Company-wide period hanya dapat di-reopen ketika pengguna memilih All Sites.
- Module, periode, dan tanggal transaksi divalidasi sebagai nilai kalender yang sah.
- Semua perubahan close/reopen memakai transaksi database dan row lock.

## Dampak ke Posting

- Close company-wide memblokir transaksi semua site pada module dan periode tersebut.
- Close Site A hanya memblokir transaksi Site A.
- Close Site A tidak memblokir transaksi Site B.
- Transaction service tetap memanggil `PeriodCloseService::assertOpen()` sebelum mengubah dokumen, stock, settlement, cash/bank, atau GL.

## Deployment

Pilihan utama:

```bash
php spark migrate
```

Untuk phpMyAdmin:

```text
database/sql/2026_06_22_period_close_site_scope.sql
```

Pastikan query duplicate scope dalam file SQL menghasilkan nol baris sebelum unique index ditambahkan.

## UAT Ringkas

1. Tutup periode Inventory untuk Site A.
2. Pastikan adjustment Site A ditolak dan Site B tetap dapat posting.
3. Tutup periode Inventory melalui All Sites.
4. Pastikan Site A dan Site B sama-sama ditolak.
5. Coba reopen record Site A ketika Site B aktif; harus ditolak/404.
6. Coba reopen company-wide ketika Site A aktif; tombol tidak tampil dan direct POST ditolak.
7. Pilih All Sites, reopen company-wide, lalu pastikan transaksi dapat diposting kembali.
