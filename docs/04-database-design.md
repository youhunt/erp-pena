# Database Design

## Excel Review Summary

Source workbook: `pena_erp_data_dictionary_filled.xlsx`.

Modules and representative sheets:

- Setup: Company, Site, Department, Warehouse, Location, Country, Province, City, UoM, UoM Conversion, VAT, WHT/PPH, Transaction Code, Prefix Code
- Sales: Customer Master, Customer Terms, Promotion, Sales Order, Allocation Order, Delivery Order
- Purchase: Supplier Master, Supplier Terms, Purchase Order, Purchase Order Receipt
- Inventory: Item Master, Item UoM Conversion, Batch Master, Inventory In Out
- General Ledger: GL Book, GL Column, Account No, Chart of Account, GL Entry, Recurring
- Cash Bank: Cash Bank ID, Currency, Employee Master, Rate Master, CashBank Entry
- Account Receivable: Manual A/R Invoice, Sales Invoice
- Costing: Cost Type, Item Cost, Calculate Cost
- Production: BOM, Work Center, Routing, Work Order Entry
- Fixed Asset: Asset ID
- POS: POS Master, POS System

## Identified Gaps

The Excel baseline does not fully define:

- CodeIgniter Shield user tables
- User-company and user-site access
- Granular permission tables and menu access
- Approval workflows
- Audit trail
- Document upload storage
- OCR raw text and AI extraction results
- Field mapping from documents to ERP transactions
- Duplicate document checking
- Transaction conversion logs

These gaps are covered by added foundation tables.

## Foundation Tables

### Setup

- `companies`: company master; unique `code`
- `sites`: branch/site master; FK `company_id`
- `departments`: department per company/site
- `warehouses`: warehouse per company/site
- `locations`: warehouse location per company/site
- `countries`, `provinces`, `cities`: geographical master
- `currencies`: currency master
- `vat_rates`: VAT master
- `wht_rates`: WHT/PPH master
- `uoms`: unit of measure master
- `uom_conversions`: conversion between UoMs
- `transaction_codes`: transaction code master
- `prefix_codes`: numbering prefix master
- `postal_codes`: postal code, district, and village reference
- `item_vat_rates`: item-to-VAT mapping
- `addresses`: address master for company, site, customer, supplier, bill-to, ship-to, and mail-to use cases

### Access

- `user_company_access`: maps Shield users to companies
- `user_site_access`: maps Shield users to sites
- `menu_items`: dynamic sidebar/menu controlled by permission

### Sales and Purchase

- `customers`: customer master from Sales Customer Master
- `suppliers`: supplier master from Purchase Supplier Master
- `sales_orders` and `sales_order_lines`: normalized Sales Order
- `allocationorder` and `allocationline`: stock allocation document from Sales Order based on workbook naming
- `sales_deliveries` and `sales_delivery_lines`: delivery document generated from Sales Order
- `sales_invoices` and `sales_invoice_lines`: customer invoice generated from Delivery Order
- `purchase_orders` and `purchase_order_lines`: normalized Purchase Order
- `purchase_receipts` and `purchase_receipt_lines`: goods receipt generated from Purchase Order
- `purchase_invoices` and `purchase_invoice_lines`: vendor invoice generated from Purchase Receipt

### Inventory

- `items`: item master
- `inventory_movements` and `inventory_movement_lines`: inventory in/out and adjustment foundation

### Production

- `production_work_centers`: work center header
- `work_center_machine`: machine/capacity child records for each work center
- `work_center_cost`: cost child records for each work center

### Finance

- `ar_receivables`: customer outstanding generated from Sales Invoice
- `ap_payables`: supplier outstanding generated from Purchase Invoice
- `ar_receipts`: customer payment receipt that reduces A/R outstanding
- `ap_payments`: supplier payment that reduces A/P outstanding
- `invoices` and `invoice_lines`: legacy/manual invoice foundation retained until finance consolidation

### Workflow and Audit

- `approval_workflows`: approval rule header
- `approval_steps`: approval role/permission steps
- `audit_trails`: immutable activity and data change log

### AI/OCR

- `document_uploads`: original file metadata, hash, status, duplicate link
- `document_extractions`: OCR text and AI structured JSON
- `document_field_mappings`: source-to-target field mapping and human correction
- `document_processing_logs`: processing events
- `document_transaction_links`: relation from document to final ERP transaction

## Tenant Columns

Transaction tables include `company_id` and `site_id`.

Master tables include:

- `company_id` when company-specific
- `site_id` when site-specific
- no tenant columns for global reference data such as countries

## Key Indexes

Important unique indexes:

- `companies.code`
- `sites.company_id + code`
- master tables: `company_id + site_id + code`
- transactions: `company_id + site_id + document_no`
- document duplicate detection: `company_id + sha256_hash`

## Change Rationale

The migration does not copy the Excel one-to-one because the workbook uses screen fields and old file names. The implementation normalizes those fields into scalable ERP tables while preserving the source concepts and lookup relationships.
