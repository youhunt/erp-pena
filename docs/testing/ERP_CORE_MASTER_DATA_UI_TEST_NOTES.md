# ERP Core Master Data UI Test Notes

Dokumen ini melengkapi skenario E2E Purchase to GL, khusus untuk catatan UI/UX master data.

## UI-MD-001: Company Base Currency harus Select2

### Tujuan

Memastikan field `Base Currency` pada form Company tidak diketik bebas, tetapi dipilih dari dropdown searchable.

### Menu

```text
Setup > Master Data > Companies > New
```

atau URL:

```text
/setup/companies/new
```

### Langkah Test

1. Buka form Create Companies.
2. Cek field `Base Currency`.
3. Field harus tampil sebagai dropdown Select2/searchable.
4. Klik dropdown.
5. Pastikan user bisa memilih minimal:
   - IDR - Indonesian Rupiah
   - USD - US Dollar
   - EUR - Euro
   - SGD - Singapore Dollar
6. Pilih `IDR - Indonesian Rupiah`.
7. Simpan company.

### Expected Result

- Field `Base Currency` tampil sebagai Select2, bukan input text biasa.
- User bisa search currency dari dropdown.
- Value yang tersimpan di database adalah kode currency, contoh: `IDR`.
- Company berhasil disimpan.

### Query Verifikasi

```sql
SELECT id, code, name, base_currency, is_active
FROM companies
WHERE code = 'TST';
```

Expected:

```text
base_currency = IDR
```

## Catatan

Saat ini enhancement Select2 dilakukan dari frontend untuk field `base_currency` agar form master data generic tetap sederhana. Jika nanti ingin sepenuhnya dinamis dari master `currencies`, field ini bisa dipindahkan ke konfigurasi select di `MasterDataController` dengan `options_source = currencies` dan `option_value = code`.
