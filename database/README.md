# ERP PENA Database Guide

Database source of truth untuk ERP PENA dibagi menjadi 2 jalur resmi.

## Jalur 1 - Development / Local / Server yang punya CLI

Gunakan ini kalau bisa menjalankan command `php spark`.

```bash
git pull
php spark migrate
php spark db:seed CoreFinanceSeeder
php spark cache:clear
```

Ini adalah jalur utama karena struktur database diatur oleh:

```text
app/Database/Migrations
app/Database/Seeds
```

## Jalur 2 - Hosting / cPanel / phpMyAdmin

Gunakan ini kalau tidak bisa menjalankan CLI.

Jalankan satu file ini saja:

```text
database/sql/00_RUN_THIS_ON_HOSTING.sql
```

Setelah itu, kalau ada kebutuhan data fix khusus testing/demo, baru jalankan:

```text
database/sql/99_OPTIONAL_DATA_FIXES.sql
```

## Folder lama

Folder `database/hosting` sebelumnya berisi banyak patch kecil. Folder itu sudah tidak menjadi jalur resmi karena membingungkan urutan eksekusi.

Mulai sekarang:

```text
Migration + Seeder = utama
Database SQL       = fallback hosting
```

Jangan menjalankan file SQL patch lama satu per satu kecuali ada instruksi khusus.
