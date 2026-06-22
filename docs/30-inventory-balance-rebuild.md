# Inventory Balance Rebuild Procedure

Tanggal: 2026-06-22

Dokumen ini menjelaskan kapan dan bagaimana menjalankan script/command rebuild `inventory_stock_balances` dari `inventory_stock_movements`, termasuk repair orphan stock.

## 1. Kapan Dipakai

Gunakan rebuild/repair jika terjadi kondisi berikut:

1. Stock Card menunjukkan stok ada, tetapi Delivery Order menunjukkan available `0`.
2. `inventory_stock_movements` sudah benar, tetapi `inventory_stock_balances` tidak sinkron.
3. Ada data stok lama hasil import yang warehouse/location-nya belum rapi.
4. Ada stock balance/movement dengan `warehouse_id` atau `location_id` NULL/0.

Jangan gunakan rebuild kalau movement ledger masih salah, karena balance akan dibangun ulang dari movement.

## 2. Repair Orphan Stock Command

Jika masalahnya hanya stok orphan untuk item tertentu, gunakan command ini dulu. Ini lebih aman daripada rebuild semua balance.

```bash
php spark inventory:repair-orphan-stock --item=ITEM-0003 --warehouse=MAIN --location=A01 --dry-run
php spark inventory:repair-orphan-stock --item=ITEM-0003 --warehouse=MAIN --location=A01
```

Command berada di:

```text
app/Commands/RepairOrphanStockCommand.php
```

Command ini akan:

1. mencari balance item yang warehouse/location-nya NULL/0,
2. merge qty orphan ke balance target jika row target sudah ada,
3. menghapus row orphan yang sudah dimerge,
4. mengisi warehouse/location pada stock movement orphan,
5. adaptif terhadap kolom `updated_at` yang mungkin tidak ada.

## 3. File SQL Rebuild

```text
database/hosting/2026-06-22_rebuild_stock_balances_from_movements.sql
```

## 4. Spark Command Rebuild

Untuk server yang bisa akses terminal, gunakan command resmi:

```bash
php spark inventory:rebuild-balances --dry-run
```

Scope per company/site:

```bash
php spark inventory:rebuild-balances --company=1 --site=1 --dry-run
php spark inventory:rebuild-balances --company=1 --site=1
```

Command berada di:

```text
app/Commands/RebuildStockBalancesCommand.php
```

## 5. Prinsip Rebuild

`inventory_stock_movements` diperlakukan sebagai sumber kebenaran. Rebuild akan:

1. Membaca movement valid yang sudah punya company, warehouse, location, dan item code.
2. Menghapus isi `inventory_stock_balances` sesuai scope command/SQL.
3. Membuat ulang balance berdasarkan movement per company, site, warehouse, location, dan item.
4. Mengisi:
   - qty_on_hand
   - qty_reserved = 0
   - qty_available = qty_on_hand
   - avg_cost
   - stock_value
   - last_movement_date

## 6. Langkah Aman

1. Backup database.
2. Jika hanya item tertentu yang orphan, jalankan `inventory:repair-orphan-stock --dry-run` dulu.
3. Jika balance global tidak sinkron, jalankan diagnostic SQL atau command `inventory:rebuild-balances --dry-run`.
4. Cek baris yang mismatch / preview output.
5. Jika movement sudah benar, baru jalankan command tanpa `--dry-run`.
6. Setelah repair/rebuild, buka Delivery Order dan klik Refresh Stock.
7. Cek Stock Card dan Stock Balance.

## 7. Catatan Reserved Quantity

Pada rebuild ini `qty_reserved` diset `0` karena belum ada rebuild allocation/reservation detail. Jika modul allocation sudah aktif dan data reservation dianggap final, perlu script khusus untuk menghitung ulang reserved quantity dari allocation order.

Untuk UAT Sales Delivery dasar, nilai ini aman karena Delivery memakai outstanding SO dan available stock.

## 8. Setelah Repair/Rebuild

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
