# ERP Core GL Setup Test Notes

Dokumen ini melengkapi skenario `ERP_CORE_PURCHASE_TO_GL_E2E_TEST_SCENARIO.md`, khusus untuk setup COA dan Posting Profile melalui menu GL Utilities.

## UI-GL-001: Setup COA via GL Utilities

### Tujuan

Memastikan tester boleh memakai fungsi otomatis di menu GL Utilities untuk memenuhi step:

```text
E2E-002 Setup COA
E2E-003 Setup GL Posting Profile
```

### Menu

```text
GL > GL Utilities
```

### Langkah Test

1. Pastikan company aktif di header sudah benar, contoh `TST`.
2. Buka menu `GL > GL Utilities`.
3. Klik tombol:

```text
Initialize Defaults
```

4. Tunggu sampai muncul notifikasi sukses.
5. Cek angka `Modern COA` bertambah atau minimal tidak 0.
6. Buka:

```text
GL > Chart of Account
```

7. Pastikan akun wajib tersedia.
8. Buka:

```text
GL > Posting Profile
```

9. Pastikan mapping AP/Inventory/GRNI sudah tersedia.

## Expected Result

Setelah `Initialize Defaults`, sistem minimal memiliki:

| Area | Expected |
|---|---|
| GL Book | Ada default GL Book |
| COA | Ada default Chart of Account |
| Posting Profile | Ada default mapping akun otomatis |

Akun wajib untuk E2E Purchase Receipt:

| Account No | Account Name | Normal Balance |
|---|---|---|
| 1100 | Cash and Bank | Debit |
| 1300 | Inventory | Debit |
| 2100 | Accounts Payable | Credit |
| 2300 | Goods Received Not Invoiced | Credit |

Posting Profile wajib:

| Module | Posting Key | Account No |
|---|---|---|
| ap | inventory | 1300 |
| ap | grni | 2300 |
| ap | payable | 2100 |
| cashbank | cash_bank | 1100 |

## Query Verifikasi COA

```sql
SELECT account_no, account_name, is_active
FROM chart_accounts
WHERE account_no IN ('1100','1300','2100','2300')
ORDER BY account_no;
```

Expected minimal 4 row.

## Query Verifikasi Posting Profile

```sql
SELECT gp.company_id, gp.module_code, gp.posting_key, gp.account_no, ca.account_name, gp.is_active
FROM gl_posting_profiles gp
LEFT JOIN chart_accounts ca ON ca.account_no = gp.account_no
WHERE gp.module_code IN ('ap','cashbank')
  AND gp.posting_key IN ('inventory','grni','payable','cash_bank')
ORDER BY gp.module_code, gp.posting_key;
```

Expected:

```text
ap.inventory       -> 1300
ap.grni            -> 2300
ap.payable         -> 2100
cashbank.cash_bank -> 1100
```

## Catatan Penting

`Initialize Defaults` boleh dipakai sebagai cara utama untuk E2E testing karena lebih cepat dan lebih konsisten dibanding input COA manual satu per satu.

Tetap lakukan query verifikasi setelahnya, karena E2E dianggap lulus bukan hanya karena tombol sukses, tetapi karena COA dan Posting Profile benar-benar tersedia di database.
