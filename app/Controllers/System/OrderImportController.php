<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Libraries\XlsxSheetReader;
use App\Libraries\XlsxSheetWriter;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Sales\SalesOrderService;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;
use Throwable;

class OrderImportController extends BaseController
{
    private const MAX_UPLOAD_BYTES = 10485760;

    private const SALES_HEADERS = [
        'so_no',
        'so_date',
        'customer_code',
        'customer_name',
        'terms_code',
        'currency_code',
        'notes',
        'item_code',
        'item_name',
        'qty',
        'uom_code',
        'unit_price',
        'discount_amount',
        'tax_amount',
    ];

    private const PURCHASE_HEADERS = [
        'po_no',
        'po_date',
        'supplier_code',
        'supplier_name',
        'terms_code',
        'currency_code',
        'notes',
        'item_code',
        'item_name',
        'qty',
        'uom_code',
        'unit_price',
        'discount_amount',
        'tax_amount',
    ];

    public function salesForm(): string
    {
        return $this->form('sales');
    }

    public function purchaseForm(): string
    {
        return $this->form('purchase');
    }

    public function salesTemplate()
    {
        return $this->template('sales');
    }

    public function purchaseTemplate()
    {
        return $this->template('purchase');
    }

    public function importSales()
    {
        return $this->import('sales');
    }

    public function importPurchase()
    {
        return $this->import('purchase');
    }

    private function form(string $type): string
    {
        $config = $this->config($type);

        return view('system/order_import/form', [
            'title' => $config['title'],
            'typeLabel' => $config['label'],
            'headers' => $config['headers'],
            'sampleRows' => $config['sampleRows'],
            'importUrl' => $config['importUrl'],
            'templateUrl' => $config['templateUrl'],
            'backUrl' => $config['backUrl'],
        ]);
    }

    private function template(string $type)
    {
        $config = $this->config($type);
        $path = (new XlsxSheetWriter())->writeFirstSheet(
            array_merge([$config['headers']], $config['sampleRows']),
            $config['sheetName']
        );

        $content = file_get_contents($path) ?: '';
        @unlink($path);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $config['fileName'] . '"')
            ->setBody($content);
    }

    private function import(string $type)
    {
        $config = $this->config($type);
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();

        if ($companyId === null || $companyId < 1) {
            return redirect()->to(site_url($config['importUrl']))->with('error', 'Active company is required before importing orders.');
        }

        $file = $this->request->getFile('order_file');
        $uploadError = $this->validateUpload($file);
        if ($uploadError !== null) {
            return redirect()->to(site_url($config['importUrl']))->with('error', $uploadError);
        }

        try {
            $rows = $this->readRows($file->getTempName(), strtolower($file->getClientExtension()));
            $records = $this->rowsToRecords($rows, $config['headers']);
            $result = $this->createDocuments($type, $records, $tenant);
        } catch (Throwable $exception) {
            return redirect()->to(site_url($config['importUrl']))->with('error', $exception->getMessage());
        }

        return redirect()
            ->to(site_url($config['backUrl']))
            ->with('message', 'Import selesai. ' . $result['documents'] . ' dokumen dibuat, ' . $result['lines'] . ' line diproses.');
    }

    private function createDocuments(string $type, array $records, TenantContext $tenant): array
    {
        if ($records === []) {
            throw new RuntimeException('No valid order rows found in the uploaded file.');
        }

        $config = $this->config($type);
        $db = Database::connect();
        $groups = [];
        foreach ($records as $record) {
            $documentNo = trim((string) ($record[$config['documentField']] ?? ''));
            if ($documentNo === '') {
                throw new RuntimeException('Document number is required on Excel row ' . $record['_excel_row'] . '.');
            }

            $groups[$documentNo][] = $record;
        }

        $created = 0;
        $lineCount = 0;

        foreach ($groups as $documentNo => $lines) {
            $first = $lines[0];
            $this->assertNotDuplicate($config['table'], $config['documentField'], $documentNo, (int) $tenant->activeCompanyId());
            $partner = $this->lookupPartner($type, (string) ($first[$config['partnerCodeField']] ?? ''));

            $header = [
                'company_id' => $tenant->activeCompanyId(),
                'site_id' => $tenant->activeSiteId(),
                'company' => $this->activeCode('company'),
                'site' => $this->activeCode('site'),
                $config['documentField'] => $documentNo,
                $config['dateField'] => $this->normalizeDate($first[$config['dateField']] ?? '', (int) $first['_excel_row']),
                $config['partnerIdField'] => $partner['id'] ?? null,
                $config['partnerLegacyField'] => $partner['code'] ?? $this->nullIfBlank($first[$config['partnerCodeField']] ?? ''),
                $config['partnerCodeField'] => $partner['code'] ?? $this->nullIfBlank($first[$config['partnerCodeField']] ?? ''),
                $config['partnerNameField'] => $partner['name'] ?? $this->nullIfBlank($first[$config['partnerNameField']] ?? ''),
                'terms_code' => trim((string) ($first['terms_code'] ?? ($partner['terms_code'] ?? ''))),
                'currency_code' => trim((string) ($first['currency_code'] ?? '')) ?: 'IDR',
                'status' => 'draft',
                'document_status' => 'draft',
                'notes' => trim((string) ($first['notes'] ?? '')),
            ];

            $documentLines = [];
            foreach ($lines as $line) {
                $documentLines[] = $this->linePayload($line, $type);
            }

            if ($type === 'sales') {
                (new SalesOrderService())->create($header, $documentLines, auth()->id());
            } else {
                (new PurchaseOrderService())->create($header, $documentLines, auth()->id());
            }

            $created++;
            $lineCount += count($documentLines);
        }

        return ['documents' => $created, 'lines' => $lineCount];
    }

    private function linePayload(array $line, string $type): array
    {
        $item = $this->lookupItem((string) ($line['item_code'] ?? ''));
        $qty = $this->number($line['qty'] ?? 0);
        if ($qty <= 0) {
            throw new RuntimeException('Qty must be greater than zero on Excel row ' . $line['_excel_row'] . '.');
        }

        $uom = trim((string) ($line['uom_code'] ?? ''));
        if ($uom === '') {
            $uom = (string) ($item[$type === 'sales' ? 'sellinguom' : 'purchaseuom'] ?? $item['stockuom'] ?? 'PCS');
        }

        $price = $this->number($line['unit_price'] ?? '');
        if ($price <= 0 && $item !== null) {
            $price = $this->number($item[$type === 'sales' ? 'sellingprice' : 'purchasep'] ?? $item['item_price'] ?? 0);
        }

        $code = trim((string) ($line['item_code'] ?? '')) ?: (string) ($item['item_code'] ?? $item['code'] ?? '');
        $name = trim((string) ($line['item_name'] ?? '')) ?: (string) ($item['item_name'] ?? $item['name'] ?? $code);

        if ($code === '' && $name === '') {
            throw new RuntimeException('Item code or item name is required on Excel row ' . $line['_excel_row'] . '.');
        }

        return [
            'item_id' => isset($item['id']) ? (int) $item['id'] : null,
            'item_code' => $code !== '' ? $code : null,
            'item_name' => $name !== '' ? $name : $code,
            'qty' => $qty,
            'uom_code' => $uom,
            'unit_price' => $price,
            'discount_amount' => $this->number($line['discount_amount'] ?? 0),
            'tax_amount' => $this->number($line['tax_amount'] ?? 0),
        ];
    }

    private function rowsToRecords(array $rows, array $expectedHeaders): array
    {
        if (count($rows) < 2) {
            throw new RuntimeException('Uploaded file must contain a header row and at least one data row.');
        }

        $headers = array_map(fn ($value): string => $this->normalizeHeader((string) $value), $rows[0]);
        foreach ($expectedHeaders as $header) {
            if (! in_array($header, $headers, true)) {
                throw new RuntimeException('Missing required header: ' . $header);
            }
        }

        $records = [];
        foreach (array_slice($rows, 1) as $index => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $record = ['_excel_row' => $index + 2];
            foreach ($headers as $position => $header) {
                if ($header === '') {
                    continue;
                }

                $record[$header] = trim((string) ($row[$position] ?? ''));
            }
            $records[] = $record;
        }

        return $records;
    }

    private function readRows(string $path, string $extension): array
    {
        if ($extension === 'xlsx') {
            return (new XlsxSheetReader())->readFirstSheet($path);
        }

        $delimiter = $extension === 'tsv' ? "\t" : ',';
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded file.');
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function validateUpload($file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return 'Please upload a valid Excel or CSV file.';
        }

        if ($file->getSize() < 1) {
            return 'Uploaded file is empty.';
        }

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            return 'Uploaded file is too large. Maximum allowed size is 10 MB.';
        }

        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'csv', 'tsv'], true)) {
            return 'Only .xlsx, .csv, or .tsv files are supported.';
        }

        return null;
    }

    private function assertNotDuplicate(string $table, string $documentField, string $documentNo, int $companyId): void
    {
        $db = Database::connect();
        $builder = $db->table($table)
            ->where('company_id', $companyId)
            ->where($documentField, $documentNo);

        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        $exists = $builder->countAllResults() > 0;

        if ($exists) {
            throw new RuntimeException(strtoupper(str_replace('_no', '', $documentField)) . ' number already exists: ' . $documentNo);
        }
    }

    private function lookupPartner(string $type, string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $table = $type === 'sales' ? 'customers' : 'suppliers';
        $legacyCode = $type === 'sales' ? 'customer' : 'supplier';
        $legacyName = $type === 'sales' ? 'customern' : 'supplierna';
        $db = Database::connect();
        $builder = $db->table($table);
        $builder->groupStart()->where($legacyCode, $code)->orWhere('code', $code)->groupEnd();
        $this->scopeBuilder($builder, $table);
        $row = $builder->get()->getRowArray();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'code' => (string) ($row[$legacyCode] ?? $row['code'] ?? ''),
            'name' => (string) ($row[$legacyName] ?? $row['name'] ?? ''),
            'terms_code' => (string) ($row['terms_code'] ?? $row['terms'] ?? ''),
        ];
    }

    private function lookupItem(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $db = Database::connect();
        $builder = $db->table('items');
        $builder->groupStart()->where('item_code', $code)->orWhere('code', $code)->groupEnd();
        $this->scopeBuilder($builder, 'items');

        return $builder->get()->getRowArray() ?: null;
    }

    private function scopeBuilder($builder, string $table): void
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();

        if ($db->fieldExists('company_id', $table) && $tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }

        if ($db->fieldExists('site_id', $table) && $tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }

        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
    }

    private function normalizeDate(mixed $value, int $rowNumber): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new RuntimeException('Document date is required on Excel row ' . $rowNumber . '.');
        }

        if (is_numeric($value) && (float) $value > 20000) {
            return gmdate('Y-m-d', ((int) floor((float) $value) - 25569) * 86400);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('Invalid document date on Excel row ' . $rowNumber . '. Use YYYY-MM-DD.');
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? '';

        return trim($header, '_');
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function number(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', $value);
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function activeCode(string $type): ?string
    {
        $sessionKey = $type === 'company' ? 'active_company_code' : 'active_site_code';
        $code = session($sessionKey);
        if ($code !== null && $code !== '') {
            return (string) $code;
        }

        $tenant = new TenantContext(session());
        $id = $type === 'company' ? $tenant->activeCompanyId() : $tenant->activeSiteId();
        if ($id === null) {
            return null;
        }

        $table = $type === 'company' ? 'companies' : 'sites';
        $row = Database::connect()->table($table)->where('id', $id)->get()->getRowArray();

        return isset($row['code']) ? (string) $row['code'] : null;
    }

    private function config(string $type): array
    {
        if ($type === 'sales') {
            return [
                'title' => 'Import Sales Orders',
                'label' => 'Sales Order',
                'headers' => self::SALES_HEADERS,
                'sampleRows' => [
                    ['SO-IMPORT-001', date('Y-m-d'), 'CUST001', 'PT Contoh Customer', 'NET30', 'IDR', 'Contoh import SO', 'ITEM-0001', 'Kertas A4 80gsm 001', '10', 'PCS', '25000', '0', '0'],
                    ['SO-IMPORT-001', date('Y-m-d'), 'CUST001', 'PT Contoh Customer', 'NET30', 'IDR', 'Contoh import SO', 'ITEM-0002', 'Pulpen Hitam 002', '5', 'PCS', '5000', '0', '0'],
                ],
                'sheetName' => 'Sales Order Import',
                'fileName' => 'sales-order-import-template.xlsx',
                'importUrl' => 'sales/orders/import',
                'templateUrl' => 'sales/orders/import-template',
                'backUrl' => 'sales/orders',
                'table' => 'sales_orders',
                'documentField' => 'so_no',
                'dateField' => 'so_date',
                'partnerIdField' => 'customer_id',
                'partnerLegacyField' => 'customer',
                'partnerCodeField' => 'customer_code',
                'partnerNameField' => 'customer_name',
            ];
        }

        return [
            'title' => 'Import Purchase Orders',
            'label' => 'Purchase Order',
            'headers' => self::PURCHASE_HEADERS,
            'sampleRows' => [
                ['PO-IMPORT-001', date('Y-m-d'), 'SUP001', 'PT Contoh Supplier', 'NET30', 'IDR', 'Contoh import PO', 'ITEM-0001', 'Kertas A4 80gsm 001', '20', 'PCS', '20000', '0', '0'],
                ['PO-IMPORT-001', date('Y-m-d'), 'SUP001', 'PT Contoh Supplier', 'NET30', 'IDR', 'Contoh import PO', 'ITEM-0002', 'Pulpen Hitam 002', '12', 'PCS', '4000', '0', '0'],
            ],
            'sheetName' => 'Purchase Order Import',
            'fileName' => 'purchase-order-import-template.xlsx',
            'importUrl' => 'purchase/orders/import',
            'templateUrl' => 'purchase/orders/import-template',
            'backUrl' => 'purchase/orders',
            'table' => 'purchase_orders',
            'documentField' => 'po_no',
            'dateField' => 'po_date',
            'partnerIdField' => 'supplier_id',
            'partnerLegacyField' => 'supplier',
            'partnerCodeField' => 'supplier_code',
            'partnerNameField' => 'supplier_name',
        ];
    }
}
