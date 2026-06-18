# PENA ERP Continuation Plan

Tanggal: 2026-06-19

Dokumen ini menjelaskan rencana lanjutan development PENA ERP setelah audit repository. Project sudah memiliki foundation CodeIgniter 4, Shield, Skote layout, tenant context, permission guard, master data, transaksi inti, finance, inventory, dan AI/OCR foundation. Karena itu, arah development adalah memperkuat dan melengkapi, bukan membuat ulang.

## 1. Prinsip Development

1. Jangan reset atau regenerate project.
2. Gunakan branch kecil per perubahan.
3. Semua schema baru wajib lewat migration.
4. Semua data awal wajib lewat seeder.
5. Controller hanya mengatur request, response, redirect, dan validation.
6. Business logic wajib di service.
7. Query transaksi wajib tenant-scoped.
8. Permission route wajib mengikuti `PermissionGuardFilter`.
9. Posting transaksi wajib memakai database transaction.
10. Aktivitas penting wajib masuk audit log.

## 2. Arsitektur Target

```text
app/
  Config/
    AuthGroups.php
    Filters.php
    Routes.php
  Filters/
    PermissionGuardFilter.php
    TenantBootstrapFilter.php
    SetupMasterTenantGuardFilter.php
  Controllers/
    Admin/
    Setup/
    Sales/
    Purchase/
    Inventory/
    Finance/
    AccountsReceivable/
    AccountsPayable/
    Production/
    Ai/
  Models/
  Services/
    TenantContext.php
    AuditLogService.php
    MenuService.php
    Support/
      TenantScope.php
    Sales/
    Purchase/
    Inventory/
    Finance/
    Ai/
  Database/
    Migrations/
    Seeds/
  Views/
    layouts/
    partials/
    dashboard/
    setup/
    sales/
    purchase/
    inventory/
    finance/
    ai/
```

## 3. Tahap Lanjutan Prioritas

### Sprint 1 - Tenant dan Permission Hardening

Output:

- Helper `TenantScope`.
- Health check command `php spark pena:health`.
- Dokumentasi audit dan continuation plan.
- Checklist route permission dan tenant scope.

Tujuan:

- Mengurangi risiko query lupa `company_id` atau `site_id`.
- Mempermudah developer baru memahami status project.
- Menyediakan command cepat untuk cek environment lokal.

### Sprint 2 - Transaction Number Service

Output:

- Service generate nomor dokumen berbasis transaction code, prefix code, company, site, dan periode.
- Locking atau database transaction untuk menghindari duplicate number.
- Integrasi bertahap ke PO, SO, receipt, delivery, invoice, payment, journal.

### Sprint 3 - Approval Workflow Foundation

Output:

- Finalisasi approval workflow, step, dan history.
- Service submit/approve/reject/cancel.
- Integrasi awal ke PO dan SO.
- Audit log approval.

### Sprint 4 - Inventory Posting Consistency

Output:

- Semua inventory operation lewat stock posting service.
- Stock movement history wajib ada.
- Balance table diperbarui dari movement.
- Reversal dan period close guard diperkuat.

### Sprint 5 - Finance Posting Consistency

Output:

- Posting profile distandardisasi.
- AR/AP invoice posting diperkuat.
- Payment/receipt allocation diperkuat.
- Journal reversal dan period close guard diperkuat.

### Sprint 6 - AI/OCR Hardening

Output:

- Queue-ready OCR job.
- Provider abstraction untuk Tesseract, PaddleOCR, Google Vision, OpenAI Vision/LLM.
- Provider log.
- Document type mapping.
- Review field versioning.
- Duplicate checking workflow.
- Conversion validation.

## 4. Tenant Scope Rule

| Jenis Data | company_id | site_id | Catatan |
|---|---:|---:|---|
| Global reference | Tidak | Tidak | Country, province, city jika global |
| Company master | Ya | Opsional | Customer, supplier, item, COA |
| Site master | Ya | Ya | Warehouse, location, department jika site-specific |
| Transaction header | Ya | Ya | SO, PO, delivery, receipt, invoice, payment |
| Transaction line | Mengikuti header | Mengikuti header | Bisa disimpan langsung untuk query cepat |
| Audit log | Opsional | Opsional | Isi jika action terkait tenant |
| AI document | Ya | Opsional/Ya | Sesuai dokumen yang diupload |

## 5. Permission Rule

Sidebar bukan security. Menu boleh disembunyikan, tetapi route tetap wajib dijaga oleh permission filter.

Pola permission:

| Area | View | Create/Manage/Post |
|---|---|---|
| Dashboard | `dashboard.view` | - |
| Setup | `setup.master.view` | `setup.master.manage` |
| Users | `users.view` | `users.manage` |
| Sales | `sales.order.view` | `sales.order.create`, `sales.order.approve` |
| Purchase | `purchase.po.view` | `purchase.po.create`, `purchase.po.approve` |
| Inventory | `inventory.stock.view` | `inventory.movement.post` |
| Finance GL | `finance.gl.view` | `finance.gl.post` |
| AP | `finance.ap.view` | `finance.ap.manage` |
| AR | `finance.ar.view` | `finance.ar.manage` |
| Cash Bank | `cashbank.view` | `cashbank.manage` |
| Production | `production.view` | `production.manage` |
| AI/OCR | `ai.document.upload`, `ai.document.review` | `ai.document.convert` |
| Audit | `audit.logs.view` | - |

## 6. Testing Minimum Setiap Sprint

Jalankan:

```bash
composer install
php spark migrate --all
php spark db:seed PenaErpSeeder
php spark pena:health
php spark serve
```

Manual test:

1. Login superadmin.
2. Buka dashboard.
3. Switch company/site.
4. Buka setup master.
5. Coba create/edit/delete master.
6. Buka Sales Order dan Purchase Order.
7. Buka Inventory Stock.
8. Buka Finance GL/AP/AR.
9. Buka AI Documents.
10. Login role non-admin.
11. Coba akses URL yang tidak punya permission.
12. Pastikan tidak ada data tenant lain yang tampil.

## 7. Git Workflow

```bash
git checkout main
git pull origin main
git checkout -b feature/nama-sprint
# edit file
git add .
git commit -m "clear commit message"
git push origin feature/nama-sprint
```

Commit message yang disarankan:

- `add reusable tenant scope helper`
- `add local pena health check command`
- `harden erp route permission mapping`
- `add transaction numbering service`
- `add approval workflow service foundation`
- `harden inventory posting service`
- `harden ai ocr processing workflow`

## 8. Next Step Teknis Setelah Dokumen Ini

1. Gunakan `TenantScope` di service baru.
2. Refactor query manual secara bertahap, mulai dari dashboard dan report.
3. Buat `DocumentNumberService`.
4. Buat/rapikan approval service.
5. Perkuat AI/OCR queue dan provider abstraction.
