# ERP Core UOM Setup Test Notes

Dokumen ini melengkapi E2E Purchase to GL untuk kasus Item Master tidak menemukan Stock UoM atau Purchase UoM.

## Masalah

Pada form berikut:

```text
Setup > Master Data > Items > New
```

field Stock UoM dan Purchase UoM bisa kosong jika company aktif belum memiliki data master UOM.

## Konsep

UOM dibuat per company, bukan per site.

PCS, KG, MTR, LTR adalah satuan ukur umum dan biasanya berlaku untuk seluruh site dalam company yang sama.

## Langkah Fix Local

Jalankan:

```bash
php spark db:seed CoreFinanceSeeder
php spark cache:clear
```

Seeder akan membuat default UOM untuk setiap company.

Default UOM:

| Code | Name |
|---|---|
| PCS | Pieces |
| KG | Kilogram |
| GR | Gram |
| MTR | Meter |
| LTR | Liter |
| BOX | Box |
| SET | Set |

## Query Verifikasi

```sql
SELECT u.company_id, c.code AS company_code, u.code, u.name, u.is_active
FROM uoms u
JOIN companies c ON c.id = u.company_id
WHERE c.code = 'TST'
ORDER BY u.code;
```

Expected minimal ada PCS, KG, dan MTR.

## Expected UI Result

Setelah seeder jalan:

1. Buka ulang `/setup/items/new`.
2. Hard refresh browser.
3. Field Stock UoM dan Purchase UoM menampilkan pilihan.
4. Ketik PCS di Select2.
5. Pilihan PCS - Pieces harus muncul.

Untuk E2E Purchase to GL gunakan:

```text
Stock UoM    = PCS
Purchase UoM = PCS
```
