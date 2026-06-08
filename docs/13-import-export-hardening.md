# Import Export Hardening Notes

Date: 2026-06-08

This note documents the current import/export status and the next hardening tasks for PENA ERP.

## 1. Current Implementation

PENA ERP already has CSV template, import, and export support for setup/master resources through:

- `app/Controllers/Setup/MasterDataTransferController.php`
- `app/Views/setup/master/import.php`
- `setup/{resource}/template`
- `setup/{resource}/import`
- `setup/{resource}/export`

The Data Import Export Center also supports finance and inventory import/export through:

- `app/Controllers/System/DataImportController.php`
- `app/Views/system/data_import/index.php`
- `app/Views/system/data_import/import.php`

## 2. Completed Fixes

### Opening Stock Routes

Opening stock controller methods already existed but the routes were missing. These routes have been added:

```php
$routes->get('data-import/opening-stock/template', 'System\\DataImportController::openingStockTemplate');
$routes->get('data-import/opening-stock/import', 'System\\DataImportController::openingStockImportForm');
$routes->post('data-import/opening-stock/import', 'System\\DataImportController::openingStockImport');
$routes->get('data-import/opening-stock/export', 'System\\DataImportController::openingStockExport');
```

### Import Page Instructions

The master import page now tells users to use business codes for relation fields, for example:

- `warehouse_code`
- `item_code`
- `from_uom_code`
- `to_uom_code`
- `vat_code`
- `country_code`
- `province_code`
- `city_code`
- `postal_code`

### Active Company and Site Validation

Master data import now requires:

- active company for tenant-level resources
- active company and active site for site-level resources

This prevents site-level data such as items, customers, suppliers, departments, warehouses, and locations from being imported with empty `site_id`.

## 3. Next Required Hardening

### 3.1 Database Transaction for Master Import

Current row-by-row import should be wrapped in a database transaction so that failed imports do not partially save data.

Recommended pattern:

```php
$db->transBegin();

try {
    // read and process CSV rows
} catch (Throwable $exception) {
    $db->transRollback();
    throw $exception;
}

if ($db->transStatus() === false) {
    $db->transRollback();
    throw new RuntimeException('Import transaction failed. No data was saved.');
}

$db->transCommit();
```

### 3.2 Upload Validation

Add a reusable CSV upload guard for:

- valid uploaded file
- non-empty file
- max size, recommended 5 MB for initial phase
- extension `csv` or `txt`
- optional MIME validation when server configuration is stable

Suggested method:

```php
private function validateCsvUpload($file): ?string
{
    if ($file === null || ! $file->isValid()) {
        return 'Please upload a valid CSV file.';
    }

    if ($file->getSize() < 1) {
        return 'Uploaded CSV file is empty.';
    }

    if ($file->getSize() > 5 * 1024 * 1024) {
        return 'CSV file is too large. Maximum allowed size is 5 MB.';
    }

    if (! in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
        return 'Only CSV files are supported for now.';
    }

    return null;
}
```

### 3.3 Transaction for COA Import

`DataImportController::coaImport()` should also use a transaction in `importCoaCsv()`.

Reason:

- COA is foundational finance data.
- Partial COA import can break posting profile and journal setup.

### 3.4 Transaction for Opening Stock Import

`DataImportController::openingStockImport()` should use a transaction around `InventoryStockService::stockIn()` calls.

Reason:

- Opening stock creates inventory movements.
- Partial import can make opening stock balances inaccurate.

### 3.5 Preview Before Posting

For transaction-like imports, especially opening stock, future improvement should add:

1. upload CSV
2. parse and validate
3. show preview
4. user confirms
5. post final movements

This reduces accidental stock posting.

## 4. Manual Testing Checklist

### Master Data Template

Open these URLs after login:

```text
/setup/items/template
/setup/customers/template
/setup/suppliers/template
/setup/warehouses/template
/setup/locations/template
```

Expected:

- Browser downloads CSV file.
- Header row exists.
- Sample row exists.

### Master Data Import

Open:

```text
/setup/items/import
```

Expected:

- Page displays required headers.
- Page shows business-code based relation instructions.
- Import without active site should show validation error for site-level data.

### Master Data Export

Open:

```text
/setup/items/export
/setup/customers/export
/setup/suppliers/export
```

Expected:

- Export returns CSV.
- Data is filtered by active company/site.
- Soft-deleted rows are excluded when table has `deleted_at`.

### Opening Stock

Open:

```text
/system/data-import/opening-stock/template
/system/data-import/opening-stock/import
/system/data-import/opening-stock/export
```

Expected:

- Template downloads correctly.
- Import page opens correctly.
- Export returns CSV or an empty CSV with header if no data exists.

### COA

Open:

```text
/system/data-import/coa/template
/system/data-import/coa/import
/system/data-import/coa/export
```

Expected:

- Template downloads correctly.
- Import validates required headers.
- Export is filtered by active company.

## 5. Suggested Next Commit

Recommended next commit message:

```text
wrap csv imports in database transactions
```

Files to update:

- `app/Controllers/Setup/MasterDataTransferController.php`
- `app/Controllers/System/DataImportController.php`

## 6. Risk Notes

Do not add transaction imports blindly without testing in a local environment because some services may already manage their own transaction internally. If nested transaction behavior is present, test with CodeIgniter transaction settings before enabling this in production.
