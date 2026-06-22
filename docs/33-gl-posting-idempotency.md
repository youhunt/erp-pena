# GL Posting Idempotency

## Tujuan

Hardening ini memastikan satu transaksi sumber hanya dapat menghasilkan satu jurnal GL untuk jenis posting yang sama. Guard diterapkan di service dan database sehingga replay request, double-click, retry worker, atau perubahan nomor jurnal tidak membentuk jurnal ganda.

## Aturan

- `journal_no` wajib unik per company.
- Kombinasi `company_id + source_module + source_type + source_id` wajib unik bila `source_id` tersedia.
- Manual journal tetap dapat dibuat berkali-kali karena tidak memiliki `source_id`; keunikannya mengikuti `journal_no`.
- Posting normal dan reversal diperbolehkan untuk dokumen yang sama karena menggunakan `source_type` berbeda.
- Tanggal jurnal harus valid, currency wajib terisi, dan exchange rate harus lebih besar dari nol.
- Detail GL hanya dapat dibaca dalam company aktif dan site aktif, termasuk jurnal company-wide dengan `site_id` kosong.

## Perlindungan Berlapis

1. `GlPostingIntegrityGuard` memvalidasi identitas dan konteks jurnal.
2. `GeneralLedgerService` mengecek nomor jurnal dan source key dalam transaksi sebelum insert.
3. Unique index `uq_gl_entries_company_source` menutup race condition antar-request.
4. Error duplikat diterjemahkan menjadi pesan bisnis yang jelas.

## Deployment

Pilihan utama:

```bash
php spark migrate
```

Untuk server yang hanya menyediakan phpMyAdmin, jalankan:

```text
database/sql/2026_06_22_gl_source_idempotency.sql
```

Query pertama pada file SQL harus menghasilkan nol baris. Jika ditemukan duplicate source, hentikan proses dan audit jurnal terkait sebelum menambahkan unique index.

## UAT Ringkas

1. Post transaksi normal dan pastikan satu jurnal terbentuk.
2. Ulangi request posting yang sama dengan nomor jurnal berbeda.
3. Pastikan request kedua ditolak dan jumlah jurnal tidak bertambah.
4. Post reversal resmi dan pastikan jurnal reversal tetap terbentuk karena `source_type` berbeda.
5. Pilih site A lalu akses URL detail jurnal milik site B; hasil harus 404.
