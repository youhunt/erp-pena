# Inventory Balance Rebuild Procedure

Tanggal: 2026-06-22

Dokumen ini menjelaskan kapan dan bagaimana menjalankan script rebuild `inventory_stock_balances` dari `inventory_stock_movements`.

## 1. Kapan Dipakai

Gunakan script rebuild jika terjadi kondisi berikut:

1. Stock Card menunjukkan stok ada, tetapi Delivery Order menunjukkan available `0`.
2. `inventory_stock_movements` sudah benar, tetapi `inventory_stock_balances` tidak sinkron.
3. Ada data stok lama hasil import yang warehouse/location-nya belum rapi.
4. Setelah manual repair warehouse/location stok orphan.

Jangan gunakan script ini kalau movement ledger masih salah, karena balance akan dibangun ulang dari movement.

## 2. File SQL

```text
database/hosting/2026-06-22_rebuild_stock_balances_from_movements.sql
```

## 3. Prinsip

`inventory_stock_movements` diperlakukan sebagai sumber kebenaran. Script akan:

1. Membandingkan balance dengan movement ledger.
2. Menghapus isi `inventory_stock_balances`.
3. Membuat ulang balance berdasarkan movement per company, site, warehouse, location, dan item.
4. Mengisi:
   - qty_on_hand
   - qty_reserved = 0
   - qty_available = qty_on_hand
   - avg_cost
   - stock_value
   - last_movement_date

## 4. Langkah Aman

1. Backup database.
2. Jalankan bagian diagnostic di script.
3. Cek baris yang mismatch.
4. Jika movement sudah benar, baru jalankan bagian rebuild.
5. Setelah rebuild, buka Delivery Order dan klik Refresh Stock.
6. Cek Stock Card dan Stock Balance.

## 5. Catatan Reserved Quantity

Pada rebuild ini `qty_reserved` diset `0` karena belum ada rebuild allocation/reservation detail. Jika modul allocation sudah aktif dan data reservation dianggap final, perlu script khusus untuk menghitung ulang reserved quantity dari allocation order.

Untuk UAT Sales Delivery dasar, nilai ini aman karena Delivery memakai outstanding SO dan available stock.

## 6. Setelah Rebuild

Validasi minimal:

```sql
SELECT
    b.item_code,
    w.code AS warehouse_code,
    l.code AS location_code,
    b.qty_on_hand,
    b.qty_reserved,
    b.qty_available
FROM inventory_stock_balances b
JOIN warehouses w ON w.id = b.warehouse_id
JOIN locations l ON l.id = b.location_id
ORDER BY b.item_code, w.code, l.code;
```

Expected:

- Item punya warehouse/location valid.
- Qty available sesuai Stock Card.
- Tidak ada item penting dengan warehouse/location null.
