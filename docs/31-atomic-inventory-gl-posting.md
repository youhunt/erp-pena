# Atomic Inventory and GL Posting

Tanggal update: 2026-06-22

## 1. Tujuan

Menjaga stock subledger dan General Ledger selalu konsisten saat Purchase Receipt, Sales Delivery, atau reversal diposting.

## 2. Aturan

| Proses | Aturan |
|---|---|
| Purchase Receipt bernilai | Wajib menghasilkan jurnal Inventory/GRNI |
| Sales Delivery dengan COGS | Wajib menghasilkan jurnal COGS/Inventory |
| Nilai persediaan atau COGS nol | Boleh diposting tanpa jurnal GL |
| Dokumen lama tanpa `gl_entry_id` | Reversal stock tetap didukung untuk kompatibilitas |
| Dokumen dengan `gl_entry_id` tanpa detail GL | Reversal ditolak karena jurnal tidak dapat dibalik dengan aman |

## 3. Perilaku Transaksi

Posting document header, document lines, stock movement, stock balance, update quantity order, dan jurnal GL berada dalam satu transaksi database. Exception dari konfigurasi account, periode GL, validasi jurnal, atau penyimpanan GL diteruskan ke transaksi utama sehingga seluruh perubahan di-rollback.

Pesan kegagalan berasal dari service layer dan tetap berlaku ketika endpoint dipanggil langsung, bukan hanya melalui tombol UI.

## 4. File

| File | Perubahan |
|---|---|
| `app/Services/Support/PostingIntegrityGuard.php` | Memastikan transaksi bernilai memiliki GL entry dan reversal memiliki detail jurnal asal |
| `app/Services/Purchase/PurchaseReceiptService.php` | Menghapus mekanisme `GL skipped` dan mewajibkan posting GL atomik |
| `app/Services/Sales/SalesDeliveryService.php` | Menghapus mekanisme `GL skipped` dan mewajibkan posting COGS atomik |
| `tests/unit/PostingIntegrityGuardTest.php` | Unit test aturan nilai dan integritas reversal |

## 5. Database

Patch ini tidak menambah atau mengubah tabel. Tidak ada migration atau SQL hosting yang perlu dijalankan.

## 6. UAT Minimum

1. Post Receipt bernilai dengan posting profile valid dan pastikan stock serta GL sama-sama terbentuk.
2. Rusakkan sementara account profile Receipt, lalu pastikan posting gagal tanpa receipt atau stock baru.
3. Post Delivery dengan COGS valid dan pastikan stock berkurang serta GL terbentuk.
4. Rusakkan sementara account profile Delivery, lalu pastikan posting gagal tanpa delivery atau pengurangan stock.
5. Reverse dokumen dengan jurnal lengkap dan pastikan stock serta reversal GL terbentuk bersama.
6. Uji dokumen dengan `gl_entry_id` yang detailnya hilang pada database cadangan; reversal harus ditolak.

## 7. Catatan Operasional

Posting profile harus disiapkan per company sebelum transaksi bernilai diposting. Pesan kegagalan GL tidak lagi disimpan sebagai warning pada notes karena transaksi sekarang tidak boleh selesai dalam kondisi tersebut.
