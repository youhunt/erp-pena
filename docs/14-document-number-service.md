# Document Number Service

Tanggal: 2026-06-19

Dokumen ini menjelaskan standar nomor dokumen ERP untuk PENA ERP.

## 1. Tujuan

`DocumentNumberService` dibuat agar nomor transaksi seperti PO, SO, DO, Invoice, Payment, Receipt, Journal, dan dokumen AI conversion tidak dibuat manual terus-menerus di form.

Service ini memastikan:

- Nomor dokumen konsisten per company.
- Nomor dokumen bisa per site/branch.
- Sequence bisa reset harian, bulanan, tahunan, atau tidak pernah reset.
- Format nomor fleksibel.
- Generate nomor dilakukan dengan sequence table khusus.
- Risiko duplicate number lebih kecil karena memakai dedicated sequence table dan row lock.

## 2. File yang Ditambahkan

```text
app/Database/Migrations/2026-06-19-203600_CreateDocumentNumberSequences.php
app/Services/Support/DocumentNumberService.php
app/Commands/PenaDocumentNumberCommand.php
```

## 3. Tabel Sequence

Tabel:

```text
document_number_sequences
```

Field utama:

| Field | Fungsi |
|---|---|
| `company_id` | Company owner sequence |
| `site_id` | Site owner sequence; `0` berarti company-level/no site |
| `transaction_code` | Kode transaksi seperti SO, PO, DO, SI, PI, JV |
| `prefix` | Prefix final yang dipakai dalam format nomor |
| `period_key` | Periode sequence seperti `2026`, `202606`, `20260619`, atau `ALL` |
| `last_number` | Running number terakhir |
| `padding` | Panjang nomor urut, misalnya 5 menjadi `00001` |
| `reset_period` | `daily`, `monthly`, `yearly`, atau `never` |
| `last_document_no` | Nomor dokumen terakhir yang dihasilkan |

Unique key:

```text
company_id + site_id + transaction_code + prefix + period_key
```

## 4. Format Token

Format default:

```text
{PREFIX}/{YYYY}{MM}/{SEQ}
```

Token yang tersedia:

| Token | Contoh | Keterangan |
|---|---|---|
| `{PREFIX}` | SO | Prefix dokumen |
| `{CODE}` | SO | Transaction code |
| `{YYYY}` | 2026 | Tahun 4 digit |
| `{YY}` | 26 | Tahun 2 digit |
| `{MM}` | 06 | Bulan 2 digit |
| `{DD}` | 19 | Tanggal 2 digit |
| `{PERIOD}` | 202606 | Period key |
| `{COMPANY}` | 1 | Company ID |
| `{SITE}` | 1 | Site ID |
| `{SEQ}` | 00001 | Running number dengan padding |
| `{N}` | 1 | Running number tanpa padding |

## 5. Contoh Output

### Sales Order bulanan

```php
$service = new \App\Services\Support\DocumentNumberService();

$number = $service->next('SO', null, [
    'company_id' => 1,
    'site_id' => 1,
    'prefix' => 'SO',
    'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
    'reset_period' => 'monthly',
    'padding' => 5,
]);
```

Output:

```text
SO/202606/00001
```

### Purchase Order per site

```php
$number = $service->next('PO', null, [
    'company_id' => 1,
    'site_id' => 2,
    'prefix' => 'PO-JKT',
    'format' => '{PREFIX}/{YY}{MM}/{SEQ}',
]);
```

Output:

```text
PO-JKT/2606/00001
```

## 6. CLI Test

Preview tanpa increment:

```bash
php spark pena:docno SO --preview --company=1 --site=1 --prefix=SO --format="{PREFIX}/{YYYY}{MM}/{SEQ}" --reset-period=monthly --padding=5
```

Generate dan increment sequence:

```bash
php spark pena:docno SO --company=1 --site=1 --prefix=SO --format="{PREFIX}/{YYYY}{MM}/{SEQ}" --reset-period=monthly --padding=5
```

Generate PO:

```bash
php spark pena:docno PO --company=1 --site=1 --prefix=PO --format="{PREFIX}/{YY}{MM}/{SEQ}" --reset-period=monthly --padding=5
```

## 7. Integrasi Saat Ini

Nomor otomatis sudah diintegrasikan ke create form berikut:

| Modul | Field | Transaction Code | Format Default |
|---|---|---|---|
| Sales Order | `so_no` | `SO` | `{PREFIX}/{YYYY}{MM}/{SEQ}` |
| Purchase Order | `po_no` | `PO` | `{PREFIX}/{YYYY}{MM}/{SEQ}` |

Cara kerja:

1. Form create menampilkan preview nomor sebagai placeholder.
2. Field nomor boleh dikosongkan.
3. Saat submit, controller membuat nomor otomatis jika field kosong.
4. Jika user mengisi nomor manual, nomor manual tetap dipakai.
5. Edit PO tetap mempertahankan nomor existing.

File yang terintegrasi:

```text
app/Controllers/Sales/SalesOrderController.php
app/Views/sales/orders/form.php
app/Controllers/Purchase/PurchaseOrderController.php
app/Views/purchase/orders/form.php
```

## 8. Integrasi dengan Transaction Code dan Prefix Code

Service sudah mencoba membaca tabel setup seperti `prefix_codes` jika tersedia dan memiliki field yang dikenali:

- `transaction_code`
- `code`
- `prefix`
- `format`
- `reset_period`
- `padding`
- `is_active`

Namun explicit options saat memanggil `next()` atau `preview()` tetap menjadi prioritas tertinggi.

## 9. Catatan Risiko

Saat ini SO/PO generate nomor dilakukan di controller sebelum memanggil service transaksi existing. Ini sengaja dipilih agar tidak mengubah service transaksi yang sudah besar dan sensitif.

Risiko kecil: jika nomor sudah digenerate tetapi create transaksi gagal setelahnya, sequence tetap naik. Ini masih normal untuk banyak ERP karena nomor dokumen tidak harus selalu gapless. Jika nanti dibutuhkan nomor benar-benar gapless, perlu desain khusus berbasis draft reservation dan posting final.

## 10. Next Step

1. Integrasikan ke Delivery Order dan Purchase Receipt.
2. Integrasikan ke Sales Invoice dan Purchase Invoice.
3. Integrasikan ke Payment, Receipt, dan Journal Entry.
4. Tambahkan UI setup untuk format nomor dokumen jika field prefix/format belum lengkap.
5. Refactor generation ke service transaksi jika flow SO/PO sudah stabil dan test coverage tersedia.
