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

## Phase 0 - Project Stabilization

Tujuan: memastikan aplikasi bisa dikembangkan tanpa sering rusak.

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
- Sebagian besar sudah ada.
- Perlu lanjut hardening UI, menu, permission page, dan CRUD validation.

## Phase 1 - Tenant, User, Role, Permission Core

Tujuan: semua data ERP aman untuk multi-company dan multi-site.

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

## Phase 2 - Setup Master Core

Tujuan: membuat data pondasi yang dipakai semua transaksi.

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

## Phase 3 - Partner, Item, Tax, dan Commercial Master

Tujuan: transaksi bisa memilih customer, supplier, item, terms, promo, dan pajak.

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

## Phase 4 - Inventory Core

Tujuan: stok menjadi single source of truth untuk purchase, sales, production, dan POS.

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

## Phase 5 - Purchase Core

Tujuan: alur procure-to-stock/payable mulai berjalan.

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

## Phase 6 - Sales Core

Tujuan: alur order-to-delivery/invoice mulai berjalan.

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

## Phase 7 - Finance Backbone

Tujuan: ERP mulai punya akuntansi yang bisa dipertanggungjawabkan.

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

## Phase 8 - AP dan AR

Tujuan: hutang dan piutang terhubung ke purchase, sales, cash bank, dan GL.

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

## Phase 9 - Costing

Tujuan: biaya item dan transaksi bisa dihitung untuk margin dan produksi.

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

## Phase 10 - Planning dan Production

Tujuan: manufaktur berjalan setelah item, stock, UoM, batch, dan costing siap.

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

## Phase 11 - POS dan Fixed Asset

Tujuan: modul tambahan operasional setelah sales, inventory, cash bank, dan GL siap.

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

## Phase 12 - AI/OCR Document Processing

Tujuan: dokumen PDF/gambar bisa membantu input transaksi tanpa mengunci ke satu provider OCR.

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

## Phase 13 - Reporting, Dashboard, dan Enhancement UI

Tujuan: aplikasi lebih informatif, nyaman, dan siap dipakai user harian.

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

## Prioritas Sprint yang Disarankan

| Sprint | Fokus | Output Praktis |
|---|---|---|
| 1 | Stabilkan Setup CRUD | Semua menu Setup jalan dan validation lebih rapi |
| 2 | User Access Management | Halaman user, role, company access, site access |
| 3 | Customer, Supplier, Item | Master commercial dan inventory siap dipakai transaksi |
| 4 | Inventory Ledger | Stock movement dan stock balance valid |
| 5 | Purchase Order + Receipt | Procure-to-stock minimum berjalan |
| 6 | Sales Order + Delivery Order | Order-to-delivery minimum berjalan |
| 7 | Invoice + AP/AR Basic | Outstanding payable/receivable berjalan |
| 8 | GL Posting Basic | Posting journal dari transaksi inti |
| 9 | AI/OCR Review Basic | Upload, OCR/AI result, review, convert draft |
| 10 | Dashboard + Polish | Monitoring dan usability |

## Catatan Teknis Penting

- Jangan membangun semua modul sekaligus dalam bentuk CRUD kosong. Lebih baik satu flow transaksi selesai end-to-end.
- Untuk transaksi, selalu pisahkan header dan line.
- Semua transaksi harus punya status, posting status, approval status, dan audit trail.
- Semua transaksi wajib punya `company_id`; tambahkan `site_id` jika terkait cabang/gudang.
- Gunakan service untuk posting, stock mutation, journal generation, approval, dan OCR conversion.
- Controller cukup mengatur request, validation, response.
- Gunakan database transaction untuk posting PO receipt, DO, invoice, payment, GL entry, stock opname, dan conversion OCR.

