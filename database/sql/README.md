# SQL Hosting Files

Folder ini hanya untuk server hosting/cPanel/phpMyAdmin yang tidak bisa menjalankan `php spark migrate`.

## File utama

Jalankan hanya file ini untuk setup/update database via phpMyAdmin:

```text
00_RUN_THIS_ON_HOSTING.sql
```

Sebelum menjalankan file tersebut, pilih database ERP dari sidebar phpMyAdmin terlebih dahulu.

File SQL di folder ini **tidak memakai**:

```sql
USE `nama_database`;
```

karena nama database hosting bisa berbeda-beda.

## File optional

Jalankan file ini hanya kalau butuh memperbaiki data demo/testing tertentu:

```text
99_OPTIONAL_DATA_FIXES.sql
```

Contoh isinya: reset PO001 ke draft dan koreksi location FGLJ menjadi MRLJ.

## Jalur yang lebih disarankan

Kalau server bisa menjalankan CLI, jangan pakai SQL manual. Gunakan:

```bash
php spark migrate
php spark db:seed CoreFinanceSeeder
php spark cache:clear
```
