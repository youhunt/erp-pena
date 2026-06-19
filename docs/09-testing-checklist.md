# Testing Checklist

## Installation

- `composer install` completes
- `.env` database is configured
- `php spark migrate --all` completes
- `php spark db:seed PenaErpSeeder` completes
- `php spark serve` starts

## Authentication

- Login page opens
- Admin can login with seeded credentials
- Logout works
- Protected routes redirect guests to login

## Tenant

- Seeded admin has default company access
- Seeded admin has default site access
- `TenantContext` sets active company and site

## Setup Pages

- Dashboard opens
- Companies page lists seeded company
- Sites page lists seeded site
- Sidebar only shows permitted menu items

## Core ERP Smoke

- Purchase Order can be created and approved
- Purchase Receipt can be created from PO and posted
- Purchase Invoice can be created from receipt and opens A/P payable
- A/P Payment can be posted and reduces payable outstanding
- Sales Order can be created and approved
- Allocation Order can reserve stock from SO
- Delivery Order can be posted and reduces stock
- Sales Invoice can be created from delivery and opens A/R receivable
- A/R Receipt can be posted and reduces receivable outstanding
- Stock Card shows opening, movement in/out, and running balance
- Inventory Transfer can be submitted, posted, and reversed
- Manual Inventory In/Out or adjustment respects inventory period close
- Cash/Bank Entry posts GL and respects cashbank period close
- Bank Reconciliation can record statement balance and matching result from posted bank entries
- Bank Reconciliation screen clearly states that statement `.xlsx` import is a separate future flow
- Bank Statement Import template downloads as `.xlsx`
- Bank Statement Import accepts `.xlsx` and stores statement lines without posting Cash/Bank Entry
- Bank Statement Auto Match links only one safe candidate and skips ambiguous rows
- Create Reconcile from Bank Statement Import pre-fills reconcile fields and pre-selects matched bank entries
- Create Reconcile from Bank Statement Import is only available after all statement lines are matched
- Posted Bank Reconcile links back to the source Bank Statement Import when created from statement import
- Unmatched Bank Statement Line can open a prefilled Bank Entry form for controlled adjustment posting
- Bank Entry from statement line refuses changed bank/date/direction/amount before linking back to statement line
- AP Aging and AR Aging pages open under active tenant
- GL manual journal posts balanced debit/credit only
- Period Close blocks module transactions in closed period

## Production Baseline

- BOM, Work Center, and Routing pages open
- Work Order can be created from BOM/routing
- Work Order release creates component allocation baseline
- Material issue and finished good receipt update inventory ledger
- Production period close blocks production posting

## Print Documents

- Print Purchase Order opens
- Print Sales Order opens
- Print Purchase Receipt opens
- Print Sales Delivery opens
- Print Purchase Invoice opens
- Print Sales Invoice opens

## AI Documents

- Upload page accepts PDF/image
- Upload rejects unsupported MIME types
- Uploaded file is saved outside `public/`
- Duplicate upload is marked as duplicate
- Document list shows uploaded records

## Database

- All ERP foundation tables exist
- Important unique indexes exist
- Foreign keys are valid
- Soft delete fields exist on master tables

## Security

- `.env` is not committed
- Uploads are not publicly accessible
- Provider secrets are not hardcoded
- CSRF is enabled for forms

## Regression Commands

- `php spark migrate:status` shows latest expected migration batch
- `php spark routes` lists core routes without route errors
- `vendor/bin/phpunit --no-coverage` passes or known long-running failures are documented
