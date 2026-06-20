# Unique Core Master Indexes

Tanggal update: 2026-06-20

Dokumen ini mencatat penambahan physical unique index untuk master data inti setelah proses normalize dan duplicate audit bersih.

---

## 1. Tujuan

Model guard sudah mencegah duplicate code di level aplikasi. Unique index fisik menambahkan pengamanan di level database agar data tetap aman walaupun insert/update terjadi dari:

- Import manual
- Script SQL
- Integrasi API masa depan
- Bug controller/service
- Race condition multi user

---

## 2. Urutan Aman

Jangan langsung menjalankan unique index sebelum data lama bersih.

Urutan wajib:

```text
1. Backup database
2. Run database/hosting/2026-06-20_normalize_core_master_data.sql
3. Run database/hosting/2026-06-20_audit_core_master_codes.sql
4. Pastikan audit duplicate menghasilkan 0 rows
5. Run database/hosting/2026-06-20_add_unique_core_master_indexes.sql
```

Untuk local/development, migration tersedia:

```bash
php spark migrate --all
```

---

## 3. Index Scope

| Table | Unique Index | Columns |
|---|---|---|
| `customers` | `uniq_customers_company_site_code` | `company_id`, `site_id`, `code` |
| `suppliers` | `uniq_suppliers_company_site_code` | `company_id`, `site_id`, `code` |
| `items` | `uniq_items_company_site_code` | `company_id`, `site_id`, `code` |
| `warehouses` | `uniq_warehouses_company_site_code` | `company_id`, `site_id`, `code` |
| `locations` | `uniq_locations_company_site_warehouse_code` | `company_id`, `site_id`, `warehouse_id`, `code` |

---

## 4. Files Added

| File | Purpose |
|---|---|
| `database/hosting/2026-06-20_add_unique_core_master_indexes.sql` | SQL hosting/phpMyAdmin untuk tambah unique index |
| `app/Database/Migrations/2026-06-20-120000_AddUniqueCoreMasterIndexes.php` | Migration CI4 untuk local/dev deployment |

---

## 5. Important Note About NULL site_id

MySQL/MariaDB memperbolehkan multiple NULL pada unique index.

Artinya, unique index ini paling kuat jika `site_id` terisi. Jika bisnis ingin kode unik pada level company walaupun `site_id` NULL, maka data perlu distandardisasi dulu agar `site_id` tidak NULL atau dibuat generated column khusus.

Untuk tahap ini, scope mengikuti guard aplikasi saat ini:

```text
company_id + site_id + code
```

---

## 6. UAT Checklist

| No | Test Case | Expected Result | Result |
|---:|---|---|---|
| 1 | Run normalize SQL | Master data code/name tersinkron | NOT TESTED |
| 2 | Run audit duplicate SQL | Tidak ada duplicate rows | NOT TESTED |
| 3 | Run unique index SQL | Index berhasil dibuat | NOT TESTED |
| 4 | Insert duplicate customer code same company/site via form | Ditolak | NOT TESTED |
| 5 | Insert duplicate customer code same company/site via SQL | Ditolak oleh DB | NOT TESTED |
| 6 | Insert duplicate supplier/item/warehouse/location | Ditolak sesuai scope | NOT TESTED |
| 7 | Insert same item code different site | Diperbolehkan jika site berbeda | NOT TESTED |
| 8 | Check SHOW INDEX | Semua index muncul | NOT TESTED |

---

## 7. Rollback

Untuk local migration:

```bash
php spark migrate:rollback
```

Untuk hosting manual, drop index satu per satu jika benar-benar diperlukan:

```sql
ALTER TABLE `locations` DROP INDEX `uniq_locations_company_site_warehouse_code`;
ALTER TABLE `warehouses` DROP INDEX `uniq_warehouses_company_site_code`;
ALTER TABLE `items` DROP INDEX `uniq_items_company_site_code`;
ALTER TABLE `suppliers` DROP INDEX `uniq_suppliers_company_site_code`;
ALTER TABLE `customers` DROP INDEX `uniq_customers_company_site_code`;
```

---

## 8. Status

| Area | Status |
|---|---|
| Hosting SQL | Added |
| CI4 Migration | Added |
| Documentation | Added |
| Requires clean duplicate audit | Yes |
| Production run | Pending |
