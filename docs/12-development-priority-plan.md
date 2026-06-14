# Development Priority Plan

Dokumen ini menyusun tahapan development PENA ERP berdasarkan prioritas pembuatan aplikasi ERP web yang sehat: mulai dari pondasi sistem, master data, transaksi inti, finance posting, sampai fitur tambahan seperti AI/OCR, dashboard, dan enhancement UI.

Acuan utama: `pena_erp_data_dictionary_filled.xlsx`.

## Prinsip Prioritas

1. Bangun fondasi yang dipakai semua modul lebih dulu.
2. Master data harus selesai sebelum transaksi.
3. Inventory ledger harus stabil sebelum purchase, sales, production, dan costing diperdalam.
4. Finance posting harus dirancang sejak awal walaupun UI finance dibuat bertahap.
5. Fitur AI/OCR dibuat setelah struktur transaksi target cukup stabil.
6. Dashboard dan cosmetic enhancement dikerjakan setelah data transaksi nyata tersedia.

## Ringkasan Modul dari Excel

| Area | Sheet / Modul Acuan | Prioritas |
|---|---|---|
| Setup | Transaction Code, Prefix Code, Company, Site, Department, Warehouse, Location, Country, Province, City, UoM, VAT, Item VAT, Address Master, WHT/PPH | Sangat tinggi |
| Sales | Customer Master, Customer Terms, Promotion, Sales Order, Allocation Order, Delivery Order | Sangat tinggi |
| Purchase | Supplier Master, Supplier Terms, Purchase Order, Purchase Order Receipt | Sangat tinggi |
| Inventory | Item Master, Item UoM Conversion, Batch Master, Inventory In Out | Sangat tinggi |
| GL | GL Book, GL Column, Account No, Chart of Account, Recurring, GL Entry, Recurring Posting | Sangat tinggi |
| Cash Bank | Cash Bank ID, Currency, Employee Master, Rate Master, Cash/Bank Entry | Tinggi |
| AR/AP | Manual A/R Invoice, Sales Invoice, Manual A/P Invoice, Purchase Invoice, Payment/Receipt | Tinggi |
| Costing | Cost Type, Item Cost, Calculate Cost | Menengah |
| Production | BOM, Work Center, Routing, Work Order | Menengah |
| Fixed Asset | Asset ID, Asset Depreciation | Menengah |
| POS | POS Master, POS System | Menengah |
| AI/OCR | Document Upload, OCR Extraction, AI Field Extraction, Review, Convert to Transaction | Setelah transaksi inti |

## Status Legend

| Status | Arti |
|---|---|
| Done | Sudah ada implementasi dasar yang bisa dipakai atau menjadi fondasi resmi |
| Partial | Sudah mulai dibuat, tetapi belum end-to-end atau belum lengkap enterprise-grade |
| Pending | Belum dibuat selain menu placeholder atau rencana dokumentasi |
| Next | Tahap yang sebaiknya difokuskan berikutnya |

## Snapshot Status Saat Ini

| Phase | Status | Yang Sudah Dikerjakan |
|---|---|---|
| Phase 0 - Project Stabilization | Done | CodeIgniter 4, Shield, layout Skote, dynamic menu, migration/seeder, docs, test smoke |
| Phase 1 - Tenant, User, Role, Permission Core | Partial | Role/permission config, user access table, active company/site switcher, user/role page awal |
| Phase 2 - Setup Master Core | Partial | CRUD generic setup master, wilayah sync, import/export view, menu setup, beberapa schema tambahan |
| Phase 3 - Partner, Item, Tax, Commercial Master | Partial | Customer, supplier, item, UoM, VAT, item VAT, schema item/customer/supplier diselaraskan dengan Excel |
| Phase 4 - Inventory Core | Partial | Stock balance, stock movement, stock adjustment, inventory stock service, average cost movement value |
| Phase 5 - Purchase Core | Partial | Purchase Order, Purchase Receipt, Purchase Invoice/AP Payable baseline, controller, service, views, schema ensure |
| Phase 6 - Sales Core | Partial | Sales Order, Allocation Order, Delivery Order with COGS posting, Sales Invoice/AR Receivable baseline, controller, service, views, schema ensure |
| Phase 7 - Finance Backbone | Partial | GL Entry, Posting Profile, dan auto journal untuk AR/AP invoice serta cash/bank settlement baseline sudah tersedia |
| Phase 8 - AP dan AR | Partial | Sales Invoice/AR Receivable/AR Receipt dan Purchase Invoice/AP Payable/AP Payment baseline sudah ada; aging dan period close belum selesai |
| Phase 9 - Costing | Pending | Baru rencana/menu placeholder, belum ada item cost engine |
| Phase 10 - Planning dan Production | Pending | Baru menu placeholder, belum ada BOM/MRP/work order engine |
| Phase 11 - POS dan Fixed Asset | Pending | Baru menu placeholder, belum ada transaksi POS/asset |
| Phase 12 - AI/OCR Document Processing | Partial | Upload, document tables, OCR/AI interfaces, diagnostics/sample, review, convert service ke PO/SO |
| Phase 13 - Reporting, Dashboard, Enhancement UI | Partial | Dashboard awal, audit log viewer, sidebar Skote, placeholder modul |

## Phase 0 - Project Stabilization

Tujuan: memastikan aplikasi bisa dikembangkan tanpa sering rusak.

Status: Done.

Scope:
- CodeIgniter 4 baseline.
- Shield authentication.
- Struktur layout Skote.
- Dynamic menu.
- Migration dan seeder awal.
- Environment config.
- Test smoke dasar.

Output:
- App bisa login.
- Sidebar tampil.
- Seeder bisa dijalankan ulang.
- Migration tidak konflik.
- Dokumentasi install tersedia.

Status saat ini:
- Sudah ada CodeIgniter 4, Shield, Skote layout, dynamic sidebar, menu seeder, migrations, seeders, README, dan docs.
- Sudah ada smoke/unit test dasar.
- Perlu tetap dijaga setiap selesai modul baru: route check, lint, phpunit, dan seeder check.

## Phase 1 - Tenant, User, Role, Permission Core

Tujuan: semua data ERP aman untuk multi-company dan multi-site.

Status: Partial.

Scope:
- User management.
- Role management.
- Permission management.
- User-company access.
- User-site access.
- Active company/site switcher.
- Route filter permission.
- Menu filter permission.

Tabel utama:
- `users`, Shield auth tables.
- `user_company_access`.
- `user_site_access`.
- `menu_items`.
- `companies`.
- `sites`.

Done jika:
- User hanya melihat company/site yang diberikan.
- Query transaksi dan master tenant-scoped selalu pakai `company_id`.
- Route penting tidak bisa dibuka tanpa permission.

Yang sudah dikerjakan:
- Shield authentication sudah digunakan.
- Role dan permission matrix tersedia di `AuthGroups`.
- Tabel akses company/site tersedia.
- Active company/site switcher tersedia.
- User dan role controller/view awal tersedia.
- Tenant bootstrap filter dan tenant context tersedia.

Sisa pekerjaan:
- Permission CRUD granular belum lengkap di UI.
- Assignment company/site per user perlu dipoles dan dites lebih dalam.
- Route-level permission filter perlu diperluas ke semua modul transaksi.

## Phase 2 - Setup Master Core

Tujuan: membuat data pondasi yang dipakai semua transaksi.

Status: Partial. Ini adalah kandidat fokus berikutnya karena dipakai semua modul.

Prioritas master:
- Company.
- Site.
- Department.
- Warehouse.
- Location.
- Country.
- Province.
- City.
- Postal Code.
- Unit of Measure.
- UoM Conversion.
- VAT.
- WHT/PPH.
- Transaction Code.
- Prefix Code.
- Address Master.

Acuan Excel:
- `Setup 1. Transactions Code`.
- `Setup 1.1 Prefix Code`.
- `Setup 2. Company`.
- `Setup 3. Site`.
- `Setup 4. Department`.
- `Setup 5. Warehouse`.
- `Setup 6. Location`.
- `Setup 7. Country`.
- `Setup 8. Province`.
- `Setup 9. City`.
- `Setup 11. UoM`.
- `Setup 12. UoM Conversion`.
- `Setup 13. VAT`.
- `Setup 14. ITEM VAT`.
- `Setup 15. Address Master`.
- `Setup 17. WHT - PPH`.

Done jika:
- Semua CRUD bisa create, edit, delete/soft delete.
- Province dan City bisa sync dari API wilayah.
- Address Master bisa pakai Country/Province/City/Postal Code.
- Transaction Code dan Prefix Code bisa dipakai generate nomor dokumen.

Yang sudah dikerjakan:
- Generic CRUD setup master tersedia melalui `MasterDataController`.
- Menu Setup dua level sudah aktif.
- Company, Site, Department, Warehouse, Location, Country, Province, City, Postal Code, UoM, UoM Conversion, VAT, Item VAT, Address Master sudah punya route CRUD.
- Province/City sync API sudah tersedia.
- Prefix Code, Currency, dan WHT/PPH model sudah mulai tersedia.
- Import/export view setup master sudah ada.

Sisa pekerjaan:
- Validasi per tabel masih perlu dibuat lebih spesifik.
- Field dan display beberapa master masih perlu diselaraskan penuh dengan Excel.
- Number generator dari Transaction Code + Prefix Code belum menjadi service resmi.
- Soft delete dan audit trail perlu dipastikan konsisten di semua master.

## Phase 3 - Partner, Item, Tax, dan Commercial Master

Tujuan: transaksi bisa memilih customer, supplier, item, terms, promo, dan pajak.

Status: Partial.

Scope:
- Customer Master.
- Customer Terms.
- Customer Promo.
- Customer Address.
- Supplier Master.
- Supplier Terms.
- Supplier Promo.
- Supplier Address.
- Item Master.
- Item UoM Conversion.
- Batch Master.
- Item VAT.
- Cost Type awal.

Acuan Excel:
- `Sales 1. Customer Master`.
- `Sales 2. Customer Terms`.
- `Sales 3. Promotion`.
- `Purchase 1. Supplier Master`.
- `Purchase 2. Supplier Terms`.
- `Inventory 1. Item Master`.
- `Inventory 2. Item UoM Conversion`.
- `Inventory 3. Batch Master`.
- `Setup 14. ITEM VAT`.
- `Costing 1. Cost Type`.

Done jika:
- Customer, supplier, item, UoM, VAT bisa dipakai lookup transaksi.
- Item punya kontrol stock/service/asset.
- Batch bisa disiapkan untuk item yang perlu batch/expired date.

Yang sudah dikerjakan:
- Customer, supplier, dan item master sudah punya model, CRUD route, dan view generic.
- Schema item master sudah diselaraskan dengan Excel dalam beberapa migrasi/commit.
- Customer dan supplier schema sudah diselaraskan dengan Excel.
- UoM master code dropdown sudah mulai dipakai untuk item master.
- Demo master data seeder tersedia.

Sisa pekerjaan:
- Customer Terms, Customer Promo, Customer Address belum menjadi CRUD khusus.
- Supplier Terms, Supplier Promo, Supplier Address belum menjadi CRUD khusus.
- Batch Master dan Item UoM Conversion belum menjadi modul lengkap.
- Lookup transaksi perlu dibuat lebih nyaman, bukan hanya dropdown sederhana.

## Phase 4 - Inventory Core

Tujuan: stok menjadi single source of truth untuk purchase, sales, production, dan POS.

Status: Partial.

Scope:
- Warehouse stock balance.
- Inventory movement ledger.
- Inventory In Out.
- Inventory Transfer.
- Inventory Stock Opname.
- Stock adjustment.
- Batch stock.
- Stock posting dan reversal.

Acuan Excel:
- `Inventory 11. Inventory In Out`.
- `Inventory 3. Batch Master`.

Done jika:
- Setiap transaksi stok menghasilkan ledger.
- Saldo stok bisa dihitung per company, site, warehouse, location, item, batch.
- Tidak boleh ada stock movement tanpa transaction code dan posting status.
- Period close inventory bisa lock periode.

Yang sudah dikerjakan:
- Tabel inventory stock core tersedia.
- Model stock balance dan stock movement tersedia.
- Inventory stock service tersedia.
- Stock balance controller/view tersedia.
- Stock adjustment form tersedia dan sudah mendukung manual item entry.
- Stock out memakai average cost saat unit cost tidak diisi, sehingga nilai inventory movement lebih layak untuk COGS.

Sisa pekerjaan:
- Inventory In Out, Transfer, dan Stock Opname belum lengkap sebagai workflow.
- Posting/reversal dan period close belum lengkap.
- Integrasi stock ledger dengan PO receipt, DO, POS, dan production masih perlu dipastikan end-to-end.

## Phase 5 - Purchase Core

Tujuan: alur procure-to-stock/payable mulai berjalan.

Status: Partial.

Scope:
- Purchase Order.
- Purchase Intransit.
- Inventory Purchase Receipt.
- Cost Purchase Receipt.
- Purchase Invoice draft.
- Supplier terms dan tax.
- Approval PO sederhana.

Acuan Excel:
- `Purchase 11. Purchase Order`.
- `Purchase 12. Purchase Order Receipt`.
- `Purchase 1. Supplier Master`.
- `Purchase 2. Supplier Terms`.

Done jika:
- PO bisa dibuat dari supplier dan item.
- Receipt bisa menambah stock ledger.
- Purchase invoice bisa dibuat dari receipt.
- Status PO jelas: draft, submitted, approved, partially received, closed, cancelled.

Yang sudah dikerjakan:
- Purchase Order migration tersedia.
- Ensure purchase order schema tersedia.
- PurchaseOrderModel dan PurchaseOrderLineModel tersedia.
- PurchaseOrderController, PurchaseOrderService, dan views index/form/show tersedia.
- Menu Purchase Order sudah diarahkan ke modul nyata.
- Purchase Receipt core tersedia sebagai transaksi penerimaan barang.
- Purchase Invoice dari Purchase Receipt sudah tersedia.
- AP Payable otomatis dibuka saat Purchase Invoice diposting.

Sisa pekerjaan:
- Purchase intransit dan cost purchase receipt belum ada sebagai workflow penuh.
- Approval PO dan status lifecycle perlu dipoles.
- Posting ke GL belum end-to-end.
- Aging, GL posting, dan A/P period close belum selesai.

## Phase 6 - Sales Core

Tujuan: alur order-to-delivery/invoice mulai berjalan.

Status: Partial.

Scope:
- Sales Order.
- Allocation Order.
- Delivery Order.
- Sales Invoice draft.
- Customer terms, promo, tax.
- Stock allocation.
- Approval SO sederhana.

Acuan Excel:
- `Sales 11. Sales Order`.
- `Sales 12. Allocation Order`.
- `Sales 13. Delivery Order`.
- `A_R 13. Sales Invoice`.

Done jika:
- SO bisa dibuat dari customer dan item.
- Allocation bisa reserve stock.
- DO bisa mengurangi stock ledger.
- Sales Invoice bisa dibuat dari DO/SO.
- Status SO jelas: draft, submitted, approved, allocated, delivered, invoiced, closed, cancelled.

Yang sudah dikerjakan:
- Sales Order migration tersedia.
- Ensure sales order schema tersedia.
- SalesOrderModel dan SalesOrderLineModel tersedia.
- SalesOrderController, SalesOrderService, dan views index/form/show tersedia.
- Menu Sales Order sudah diarahkan ke modul nyata.
- Delivery Order core tersedia.
- Delivery Order sudah membuat COGS GL Entry baseline dari nilai stock out.
- Allocation Order baseline tersedia dan menghasilkan `allocationorder`/`allocationline`.
- Sales Invoice dari Delivery Order sudah tersedia.
- AR Receivable otomatis dibuka saat Sales Invoice diposting.

Sisa pekerjaan:
- Allocation Order masih baseline; partial allocation per line/manual split warehouse-location perlu diperdalam.
- Stock issue perlu dipastikan end-to-end untuk warehouse/location/batch yang lebih rinci.
- Approval SO dan status lifecycle perlu dipoles.
- Aging, GL posting, dan A/R period close belum selesai.

## Phase 7 - Finance Backbone

Tujuan: ERP mulai punya akuntansi yang bisa dipertanggungjawabkan.

Status: Partial.

Scope:
- Chart of Account.
- Account No.
- GL Book.
- GL Column.
- GL Entry.
- Posting journal.
- Recurring journal.
- Currency dan rate.
- Cash Bank ID.
- Cash Entry.
- Bank Entry.
- Bank Reconcile.

Acuan Excel:
- `General Ledger 1. GL Book`.
- `General Ledger 2. GL Column`.
- `General Ledger 3. Account No`.
- `General Ledger 4. Chart of Account`.
- `General Ledger 05. Recurring Master`.
- `General Ledger 11. GL Entry`.
- `General Ledger 12. Recurring Posting`.
- `Cash Bank 1. Cash Bank ID`.
- `Cash Bank 2. Currency`.
- `Cash Bank 4. Rate Master`.
- `CASH BANK 11.CashBank Entry`.

Done jika:
- Journal balanced debit/credit.
- Posting tidak bisa diubah tanpa reversal.
- Currency rate dipakai untuk transaksi multi-currency.
- Period close GL bisa lock transaksi.

Yang sudah dikerjakan:
- Permission dan menu dasar Finance/GL tersedia.
- Currency model sudah tersedia sebagai master pendukung.
- GL Entry manual dan Posting Profile tersedia.
- Manual A/R Invoice, Sales Invoice dari Delivery Order, Manual A/P Invoice, dan Purchase Invoice dari Receipt sudah bisa membuat jurnal GL otomatis.
- A/R Receipt dan A/P Payment sudah terhubung ke Cash/Bank Entry dan GL Entry.

Sisa pekerjaan:
- GL Book, GL Column, Account No, Chart of Account masih perlu CRUD enterprise-grade yang lebih lengkap.
- Recurring, reversal, approval journal, dan period close GL perlu diperdalam.
- Journal generation inventory movement, receipt valuation, COGS, production, POS, dan fixed asset belum selesai.

## Phase 8 - AP dan AR

Tujuan: hutang dan piutang terhubung ke purchase, sales, cash bank, dan GL.

Status: Pending.

Scope AP:
- Manual A/P Invoice.
- Purchase Invoice.
- Inventory Purchase Invoice.
- Advanced A/P Invoice.
- Payment Invoice.
- A/P Period Close.

Scope AR:
- Manual A/R Invoice.
- Proforma Invoice.
- Sales Invoice.
- Inventory Sales Invoice.
- Advanced A/R Receipt.
- Payment Receipt.
- A/R Period Close.

Acuan Excel:
- `A_R 11. Manual A_R Invoice`.
- `A_R 13. Sales Invoice`.
- Purchase invoice sheet dari modul Purchase/AP.
- Cash Bank entry untuk payment dan receipt.

Done jika:
- Invoice menghasilkan aging.
- Payment/receipt mengurangi outstanding.
- Posting AP/AR ke GL valid.
- Period close AP/AR lock dokumen.

Yang sudah dikerjakan:
- Sales Invoice baseline tersedia dan menghasilkan `ar_receivables`.
- Purchase Invoice baseline tersedia dan menghasilkan `ap_payables`.
- Payment Invoice baseline tersedia melalui `ap_payments`.
- Payment Receipt baseline tersedia melalui `ar_receipts`.
- Menu Sales Invoice dan Purchase Invoice sudah diarahkan ke modul nyata.

Sisa pekerjaan:
- Aging AP/AR belum tersedia.
- Posting AP/AR ke GL belum tersedia.
- Manual invoice, proforma invoice, advance invoice/receipt, dan period close belum tersedia.

## Phase 9 - Costing

Tujuan: biaya item dan transaksi bisa dihitung untuk margin dan produksi.

Status: Pending.

Scope:
- Cost Type.
- Item Cost.
- Calculate Cost.
- Purchase cost allocation.
- Inventory valuation awal.

Acuan Excel:
- `Costing 1. Cost Type`.
- `Costing 2. Item Cost`.
- `Costing 11. Calculate COst_`.

Done jika:
- Item cost bisa dihitung dan disimpan per company/site/item.
- Purchase receipt bisa mempengaruhi cost.
- Sales margin bisa mengambil cost.

Yang sudah dikerjakan:
- Menu costing tersedia sebagai placeholder.
- Cost Type sudah tercatat dalam roadmap master prioritas.

Sisa pekerjaan:
- Belum ada cost type CRUD khusus, item cost table, dan calculate cost service.
- Belum ada inventory valuation.

## Phase 10 - Planning dan Production

Tujuan: manufaktur berjalan setelah item, stock, UoM, batch, dan costing siap.

Status: Pending.

Scope Planning:
- Forecast.
- Planned Released.
- MPS.
- MRP.

Scope Production:
- BOM.
- Work Center.
- Routing.
- Work Order.
- Allocate Work Order.
- Work Order In.
- Work Order Out.
- Work Order In Out.
- Work Order Labor.
- Production Period Close.

Acuan Excel:
- `Production 1. BOM`.
- `Production 2. Work Center`.
- `Production 3. Routing`.
- `Production 11. Work Order Entry`.

Done jika:
- BOM bisa explode kebutuhan material.
- Work order bisa allocate material.
- Material issue mengurangi stock.
- Finished goods receipt menambah stock.
- Labor dan overhead bisa masuk costing.

Yang sudah dikerjakan:
- BOM, Work Center, Routing, dan Work Order baseline tersedia.
- Work Center Machine dan Work Center Cost child table tersedia sesuai workbook.

Sisa pekerjaan:
- Planning MRP/MPS belum tersedia.
- Work Order labor, period close, dan costing produksi belum selesai.

## Phase 11 - POS dan Fixed Asset

Tujuan: modul tambahan operasional setelah sales, inventory, cash bank, dan GL siap.

Status: Pending.

Scope POS:
- POS Master.
- POS System.
- Sales transaction.
- Cash/card settlement.
- Stock deduction.

Scope Fixed Asset:
- Asset ID.
- Asset acquisition.
- Asset depreciation.
- Asset disposal.
- Asset period close.

Acuan Excel:
- `POS Master`.
- `POS System`.
- `Fixed Asset 01. Asset ID`.

Done jika:
- POS bisa membuat transaksi sales dan mengurangi stok.
- Asset bisa dihitung depresiasinya dan diposting ke GL.

Yang sudah dikerjakan:
- Menu POS dan FA tersedia sebagai placeholder.

Sisa pekerjaan:
- Belum ada POS transaction, settlement, asset acquisition, depreciation, disposal, dan posting GL.

## Phase 12 - AI/OCR Document Processing

Tujuan: dokumen PDF/gambar bisa membantu input transaksi tanpa mengunci ke satu provider OCR.

Status: Partial.

Scope:
- Document upload.
- OCR provider interface.
- AI extraction provider interface.
- Document type detection.
- Field extraction.
- Confidence score.
- Human review.
- Duplicate checking.
- Convert to PO/SO/Invoice/DO.
- Audit trail.

Prioritas dokumen:
- Purchase Order.
- Customer Order / Sales Order.
- Invoice.
- Delivery Order.

Dependency:
- PO/SO/Invoice/DO minimal harus punya struktur header dan line yang stabil.
- Customer, supplier, item, UoM, VAT sudah tersedia untuk mapping.

Done jika:
- File asli tersimpan aman.
- Raw OCR dan hasil AI tersimpan.
- User bisa koreksi hasil ekstraksi.
- Hasil review bisa dikonversi menjadi transaksi ERP.

Yang sudah dikerjakan:
- Config AI/OCR tersedia.
- Document upload table dan processing tables tersedia.
- Upload page, index, show, review page tersedia.
- OCR provider interface dan OCR engine interface tersedia.
- Null OCR, local command OCR, diagnostics, sample document, dan rule-based extraction tersedia.
- Convert service ke Purchase Order dan Sales Order tersedia.

Sisa pekerjaan:
- Provider OCR/AI produksi belum dipilih dan dikonfigurasi final.
- Duplicate checking dan confidence workflow perlu dipoles.
- Convert ke invoice dan delivery order belum ada.
- Review UI perlu dibuat lebih operasional untuk koreksi line item.

## Phase 13 - Reporting, Dashboard, dan Enhancement UI

Tujuan: aplikasi lebih informatif, nyaman, dan siap dipakai user harian.

Status: Partial.

Scope:
- Dashboard total sales, purchase, invoice.
- Pending approval.
- Pending OCR review.
- Stock alert.
- Recent activity.
- DataTables server-side.
- Export Excel/PDF.
- Notification.
- Audit trail viewer.
- Better form UX.
- Bulk import.
- Search global.
- Mobile-friendly refinement.

Done jika:
- Dashboard mengambil data transaksi nyata.
- User bisa mencari, filter, export, dan audit data.
- UI konsisten dengan Skote.

Yang sudah dikerjakan:
- Dashboard awal tersedia.
- Layout Skote dan sidebar dinamis tersedia.
- Audit log table, service, controller, dan viewer tersedia.
- Placeholder module page tersedia agar menu tidak dead link.

Sisa pekerjaan:
- Dashboard belum penuh mengambil KPI transaksi nyata.
- DataTables server-side, export, notification, global search, dan mobile polish belum selesai.

## Prioritas Sprint yang Disarankan

| Sprint | Fokus | Status | Output Praktis |
|---|---|---|---|
| 1 | Stabilkan Setup CRUD | Partial / Next | Semua menu Setup jalan dan validation lebih rapi |
| 2 | User Access Management | Partial | Halaman user, role, company access, site access |
| 3 | Customer, Supplier, Item | Partial | Master commercial dan inventory siap dipakai transaksi |
| 4 | Inventory Ledger | Partial | Stock movement dan stock balance valid |
| 5 | Purchase Order + Receipt | Partial | Procure-to-stock minimum berjalan |
| 6 | Sales Order + Delivery Order | Partial | Order-to-delivery minimum berjalan |
| 7 | Invoice + AP/AR Basic | Partial | Outstanding payable/receivable baseline berjalan |
| 8 | GL Posting Basic | Pending | Posting journal dari transaksi inti |
| 9 | AI/OCR Review Basic | Partial | Upload, OCR/AI result, review, convert draft |
| 10 | Dashboard + Polish | Partial | Monitoring dan usability |

## Rekomendasi Fokus Berikutnya

1. Selesaikan Setup CRUD yang masih tanggung: validation, field Excel alignment, prefix number generator, WHT/PPH, import/export, audit.
2. Selesaikan User Access Management supaya multi-company/site tidak bocor saat modul transaksi makin banyak.
3. Rapikan Master Customer/Supplier/Item supaya PO dan SO punya lookup data yang stabil.
4. Lanjutkan Inventory Ledger sampai posting stock adjustment benar-benar reliable.
5. Baru lanjut Purchase Receipt dan Delivery Order, karena keduanya menggerakkan stok dan menjadi dasar invoice.

## Catatan Teknis Penting

- Jangan membangun semua modul sekaligus dalam bentuk CRUD kosong. Lebih baik satu flow transaksi selesai end-to-end.
- Untuk transaksi, selalu pisahkan header dan line.
- Semua transaksi harus punya status, posting status, approval status, dan audit trail.
- Semua transaksi wajib punya `company_id`; tambahkan `site_id` jika terkait cabang/gudang.
- Gunakan service untuk posting, stock mutation, journal generation, approval, dan OCR conversion.
- Controller cukup mengatur request, validation, response.
- Gunakan database transaction untuk posting PO receipt, DO, invoice, payment, GL entry, stock opname, dan conversion OCR.
