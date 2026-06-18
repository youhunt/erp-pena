# Tenant and Permission Hardening Guide

Tanggal: 2026-06-19

Dokumen ini menjadi panduan teknis untuk memastikan PENA ERP aman secara multi-company, multi-site, dan role based access control.

## 1. Tujuan

Hardening ini bertujuan agar:

- User hanya bisa melihat company yang diberikan.
- User hanya bisa melihat site/branch yang diberikan.
- Semua transaksi difilter berdasarkan active company/site.
- Semua URL penting dilindungi permission.
- Developer baru tidak membuat query ERP tanpa tenant filter.
- Sidebar dynamic tidak dianggap sebagai security utama.

## 2. Komponen Existing

| Komponen | Fungsi |
|---|---|
| `TenantContext` | Menyimpan dan membaca active company/site dari session |
| `TenantBootstrapFilter` | Menginisialisasi tenant aktif saat user masuk area ERP |
| `SetupMasterTenantGuardFilter` | Menjaga setup/master agar tenant-safe |
| `PermissionGuardFilter` | Menentukan permission berdasarkan path dan HTTP method |
| `AuthGroups.php` | Role dan permission matrix Shield |
| `menu_items` | Data dynamic sidebar/menu |

## 3. Helper Baru: TenantScope

File:

```text
app/Services/Support/TenantScope.php
```

Fungsi utama:

- `activeCompanyId()`
- `activeSiteId()`
- `requireCompany()`
- `optionalSite()`
- `applyToModel()`
- `applyToBuilder()`
- `withTenantColumns()`

## 4. Contoh Penggunaan di Service

### Create transaction header

```php
use App\Services\Support\TenantScope;

$tenantScope = new TenantScope();

$data = $tenantScope->withTenantColumns([
    'document_no' => $documentNo,
    'document_date' => date('Y-m-d'),
    'customer_id' => $customerId,
    'status' => 'draft',
]);

$model->insert($data);
```

### Query model

```php
$model = new SalesOrderModel();
$orders = (new TenantScope())
    ->applyToModel($model)
    ->orderBy('document_date', 'DESC')
    ->findAll();
```

### Query builder dengan alias

```php
$db = db_connect();
$builder = $db->table('sales_orders so')
    ->select('so.*, customers.name AS customer_name')
    ->join('customers', 'customers.id = so.customer_id', 'left');

(new TenantScope())->applyToBuilder($builder, 'so');

$rows = $builder->get()->getResultArray();
```

## 5. Rule Tenant Query

Setiap query transaksi wajib menjawab pertanyaan ini:

1. Tabel ini punya `company_id`?
2. Tabel ini punya `site_id`?
3. Apakah data ini global, company-level, atau site-level?
4. Apakah user boleh akses company/site tersebut?
5. Apakah query sudah memfilter active company/site?

## 6. Rule Insert dan Update

Saat insert transaksi:

- Jangan ambil `company_id` dari input user mentah.
- Ambil `company_id` dari active tenant.
- Ambil `site_id` dari active tenant jika transaksi site-specific.
- Validasi master lookup masih berada dalam tenant yang sama.

Contoh validasi:

```php
$companyId = (new TenantScope())->requireCompany();

$customer = $customerModel
    ->where('company_id', $companyId)
    ->where('id', $customerId)
    ->first();

if ($customer === null) {
    throw new RuntimeException('Customer tidak valid untuk company aktif.');
}
```

## 7. Permission Mapping Rule

`PermissionGuardFilter` harus diperbarui setiap kali route baru ditambahkan.

Checklist saat tambah route baru:

1. Tambahkan route di `Routes.php`.
2. Pastikan route masuk path filter `permission` di `Filters.php` jika perlu.
3. Tambahkan mapping path di `PermissionGuardFilter`.
4. Tambahkan permission di `AuthGroups.php` jika permission baru diperlukan.
5. Tambahkan menu permission di seeder/menu config.
6. Test direct URL sebagai role yang tidak punya akses.

## 8. Behavior Permission Guard

Rekomendasi behavior:

- User belum login: ditangani Shield/session filter.
- User login tapi tidak punya permission: blokir akses.
- Superadmin: boleh akses semua route ERP.
- Wildcard permission Shield tetap dipakai.

## 9. Health Check Command

File:

```text
app/Commands/PenaHealthCheck.php
```

Command:

```bash
php spark pena:health
```

Command ini mengecek:

- Writable path.
- Cache path.
- Logs path.
- Uploads path.
- Public index.
- Database connection.
- Required foundation tables.
- Optional module tables.

## 10. Manual Testing Role

| Role | Test |
|---|---|
| Super Admin | Semua menu utama harus bisa dibuka |
| Company Admin | Setup, user, transaksi company harus bisa |
| Sales | Sales route bisa, purchase manage harus tertolak |
| Purchase | Purchase route bisa, sales create harus tertolak kecuali diberi permission |
| Inventory | Stock route bisa, finance posting harus tertolak |
| Finance | Finance/AP/AR route bisa, master manage terbatas |
| Viewer | View-only, create/update/post harus tertolak |

## 11. Risiko yang Harus Dihindari

- Query transaksi tanpa `company_id`.
- Query report join multi-table tanpa alias tenant filter.
- Insert transaksi memakai `company_id` dari request body.
- Route baru tidak masuk permission mapping.
- Menu disembunyikan tapi URL tetap bisa dibuka.
- User diberi akses site tanpa akses company.
- Convert AI/OCR menjadi transaksi tanpa validasi tenant.

## 12. Rekomendasi Refactor Bertahap

Urutan aman:

1. Dashboard query.
2. Sales report/list query.
3. Purchase report/list query.
4. Inventory stock/card query.
5. AR/AP aging query.
6. AI document list dan conversion query.
7. Production work order query.
8. Finance posting query.

Jangan refactor seluruh project sekaligus. Lakukan per modul dengan test manual setelah setiap commit.
