# Repository Audit

Tanggal audit: 2026-06-19
Repository: `https://github.com/youhunt/erp-pena.git`
Branch basis: `main`

## 1. Kesimpulan Audit

Repository PENA ERP sudah berisi aplikasi CodeIgniter 4 yang berjalan sebagai fondasi ERP enterprise. Project tidak boleh dibuat ulang dari nol. Development berikutnya harus melanjutkan struktur existing secara bertahap.

## 2. Stack yang Terdeteksi

Berdasarkan `composer.json` dan struktur repository:

| Komponen | Status |
|---|---|
| PHP | `^8.2` |
| CodeIgniter 4 | `^4.7` |
| CodeIgniter Shield | `^1.3` |
| MySQL/MariaDB | Digunakan melalui config database CI4 |
| Skote layout | Struktur view dan asset sudah tersedia |
| PhpSpreadsheet | Tersedia untuk import/export Excel |

## 3. Struktur Aplikasi Existing

Area penting yang sudah ada:

| Path | Fungsi |
|---|---|
| `app/Config/Routes.php` | Route utama ERP, auth, module groups |
| `app/Config/AuthGroups.php` | Role, permission, permission matrix Shield |
| `app/Config/Filters.php` | Session, tenant, setup tenant, dan permission filters |
| `app/Filters/PermissionGuardFilter.php` | Guard permission route berdasarkan path/method |
| `app/Filters/TenantBootstrapFilter.php` | Bootstrap active company/site |
| `app/Filters/SetupMasterTenantGuardFilter.php` | Guard tenant untuk setup/master |
| `app/Services/TenantContext.php` | Active tenant dan akses company/site user |
| `app/Controllers/Admin` | User dan role management foundation |
| `app/Controllers/Setup` | Generic setup/master CRUD |
| `app/Controllers/Sales` | Sales order, allocation, delivery foundation |
| `app/Controllers/Purchase` | Purchase order dan receiving foundation |
| `app/Controllers/Inventory` | Stock balance, movement, transfer, adjustment foundation |
| `app/Controllers/Finance` | GL, cash bank, period close, finance utilities |
| `app/Controllers/Ai` | AI/OCR document processing foundation |
| `app/Views/layouts` | Main/auth layout |
| `app/Views/partials` | Header, sidebar, topbar, footer |
| `docs` | Dokumentasi awal dan roadmap |

## 4. Authentication dan Authorization

CodeIgniter Shield sudah digunakan. Role enterprise minimal sudah tersedia:

- Super Admin
- Company Admin
- Finance
- Sales
- Purchase
- Inventory
- Production
- Viewer

Permission matrix juga sudah mencakup dashboard, setup, users, roles, sales, purchase, inventory, finance, production, POS, planning, costing, cash bank, fixed asset, AI document, dan audit logs.

## 5. Route Security

`Filters.php` pada `main` sudah mendaftarkan:

- `tenant` untuk area ERP aktif.
- `setupTenant` untuk area setup/master.
- `permission` yang mengarah ke `PermissionGuardFilter`.

Ini berarti repository saat ini sudah lebih maju daripada PR lama yang menambahkan filter permission baru. Karena itu, development lanjutan tidak perlu membuat `PermissionFilter` baru. Fokus berikutnya adalah menyempurnakan mapping permission dan memastikan service/controller memakai tenant scope secara konsisten.

## 6. Multi Company dan Multi Site

`TenantContext` sudah menangani:

- Active company.
- Active site.
- Switch company/site.
- Accessible company untuk user.
- Accessible site untuk user.
- User access check ke company/site.
- Bootstrap default tenant setelah login.

Sisa pekerjaan penting adalah membuat helper reusable agar semua service dan query builder memakai pola tenant filtering yang sama.

## 7. Modul Existing

### Setup Master

Sudah tersedia generic CRUD untuk master data seperti company, site, department, warehouse, location, country, province, city, postal code, currency, UoM, tax, address, customer, supplier, item, item location, dan batch master.

### Sales

Sudah tersedia Sales Order, allocation, Delivery Order, delivery import, dan integrasi ke Sales Invoice.

### Purchase

Sudah tersedia Purchase Order, PO import, PO receive, Purchase Receipt, dan integrasi ke Purchase Invoice.

### Inventory

Sudah tersedia stock balance, stock alert, stock card, inventory in/out, movement document, transfer, stock opname, dan stock adjustment.

### Finance

Sudah tersedia GL route foundation, chart of accounts, posting profile, entries, recurring, cash/bank, AR, AP, aging, dan period close foundation.

### Production

Sudah tersedia BOM, work center, routing, work order, allocation, issue material, dan receive finished goods route foundation.

### AI/OCR

Sudah tersedia upload, process, review, save review, convert to PO, convert to SO, sample documents, dan diagnostics.

## 8. Risiko Teknis

| Risiko | Level | Rekomendasi |
|---|---:|---|
| Tenant filtering masih dapat tersebar manual | Tinggi | Gunakan helper `App\Services\Support\TenantScope` secara bertahap |
| Permission mapping route bisa tertinggal saat route baru dibuat | Tinggi | Update `PermissionGuardFilter` setiap modul baru |
| Generic setup controller bisa membesar | Sedang | Master kompleks dipisah ke service/controller khusus |
| AI/OCR masih perlu hardening production | Sedang | Tambah queue, retry, provider log, dan mapping versioning |
| Finance posting perlu audit dan reversal konsisten | Tinggi | Semua posting harus melalui service + transaction DB + audit log |
| PR lama tidak mergeable | Sedang | Jangan merge langsung; porting perubahan aman ke branch baru |

## 9. Rekomendasi Lanjutan

Urutan aman berikutnya:

1. Tambahkan helper tenant scope reusable.
2. Tambahkan command `pena:health` untuk cek environment lokal.
3. Tambahkan dokumentasi hardening permission dan tenant.
4. Refactor service/controller transaksi secara bertahap agar memakai `TenantScope`.
5. Perkuat AI/OCR dengan queue-ready processing.
6. Perkuat approval workflow dan document numbering service.

## 10. Kesimpulan

PENA ERP sudah memiliki fondasi enterprise yang benar. Development selanjutnya harus bersifat incremental, migration-based, dan service-based. Tidak ada alasan teknis untuk mengganti project dari nol.
