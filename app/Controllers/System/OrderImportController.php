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
    private const SESSION_KEY = 'order_import_previews';

    private const SALES_HEADERS = [
        'so_no', 'so_line', 'so_date', 'customer_code', 'customer_name',
        'terms_code', 'currency_code', 'notes',
        'item_code', 'item_name', 'qty', 'uom_code', 'unit_price', 'discount_amount', 'tax_amount',
    ];

    private const PURCHASE_HEADERS = [
        'po_no', 'po_line', 'po_date', 'delivery_date', 'arrive_date',
        'supplier_code', 'supplier_name', 'terms_code', 'currency_code', 'notes', 'remarks',
        'discount_percent', 'discount_amount', 'freight_amount', 'other_amount',
        'special_charge_amount', 'vat_amount', 'wht_amount',
        'item_code', 'item_name', 'description', 'qty', 'uom_code', 'unit_price',
    ];

    public function salesForm(): string { return $this->form('sales'); }
    public function purchaseForm(): string { return $this->form('purchase'); }
    public function salesTemplate() { return $this->template('sales'); }
    public function purchaseTemplate() { return $this->template('purchase'); }
    public function importSales() { return $this->import('sales'); }
    public function importPurchase() { return $this->import('purchase'); }
    public function commitSales() { return $this->commit('sales'); }
    public function commitPurchase() { return $this->commit('purchase'); }

    private function form(string $type): string
    {
        $config = $this->config($type);

        return view('system/order_import/form', [
            'title' => $config['title'],
            'typeLabel' => $config['label'],
            'headers' => $config['headers'],
            'sampleRows' => $config['sampleRows'],
            'importUrl' => $config['importUrl'],
            'commitUrl' => $config['commitUrl'],
            'templateUrl' => $config['templateUrl'],
            'backUrl' => $config['backUrl'],
            'previewToken' => $this->request->getGet('preview'),
            'preview' => $this->previewFromRequest($type),
        ]);
    }

    private function template(string $type)
    {
        $config = $this->config($type);
        $path = (new XlsxSheetWriter())->writeFirstSheet(array_merge([$config['headers']], $config['sampleRows']), $config['sheetName']);
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
            $preview = $this->buildPreview($type, $records, $tenant, $file->getClientName());
            $token = bin2hex(random_bytes(16));
            $this->storePreview($token, $preview);
        } catch (Throwable $exception) {
            return redirect()->to(site_url($config['importUrl']))->with('error', $exception->getMessage());
        }

        return redirect()
            ->to(site_url($config['importUrl'] . '?preview=' . $token))
            ->with($preview['errors'] === [] ? 'message' : 'error', $preview['message']);
    }

    private function commit(string $type)
    {
        $config = $this->config($type);
        $token = trim((string) $this->request->getPost('preview_token'));
        $preview = $this->getPreview($token);
        if ($preview === null || ($preview['type'] ?? '') !== $type) {
            return redirect()->to(site_url($config['importUrl']))->with('error', 'Import preview was not found or has expired. Please upload the file again.');
        }

        if (($preview['errors'] ?? []) !== []) {
            return redirect()->to(site_url($config['importUrl'] . '?preview=' . $token))->with('error', 'Cannot post import while preview still has errors.');
        }

        $tenant = new TenantContext(session());
        if ((int) ($preview['company_id'] ?? 0) !== (int) $tenant->activeCompanyId()
            || (string) ($preview['site_id'] ?? '') !== (string) $tenant->activeSiteId()) {
            return redirect()->to(site_url($config['importUrl']))->with('error', 'Active company/site changed after preview. Please preview the file again.');
        }

        try {
            $result = $this->createDocuments($type, $preview['records'] ?? [], $tenant);
        } catch (Throwable $exception) {
            return redirect()->to(site_url($config['importUrl'] . '?preview=' . $token))->with('error', $exception->getMessage());
        }

        $this->clearPreview($token);

        return redirect()
            ->to(site_url($config['backUrl']))
            ->with('message', 'Import selesai. ' . $result['documents'] . ' dokumen dibuat, ' . $result['lines'] . ' line diproses.');
    }

    private function buildPreview(string $type, array $records, TenantContext $tenant, string $filename): array
    {
        if ($records === []) {
            throw new RuntimeException('No valid order rows found in the uploaded file.');
        }

        $config = $this->config($type);
        $groups = [];
        $errors = [];
        $validRows = [];

        foreach ($records as $record) {
            $documentNo = trim((string) ($record[$config['documentField']] ?? ''));
            if ($documentNo === '') {
                $errors[] = ['excel_row' => $record['_excel_row'] ?? '-', 'document_no' => '-', 'item_code' => $record['item_code'] ?? '', 'message' => 'Document number is required.'];
                continue;
            }
            $groups[$documentNo][] = $record;
        }

        foreach ($groups as $documentNo => $lines) {
            $consistencyError = $this->documentConsistencyError($lines, $config);
            if ($consistencyError !== null) {
                foreach ($lines as $line) {
                    $errors[] = ['excel_row' => $line['_excel_row'] ?? '-', 'document_no' => $documentNo, 'item_code' => $line['item_code'] ?? '', 'message' => $consistencyError];
                }
                continue;
            }

            try {
                $this->assertNotDuplicate($config['table'], $config['documentField'], $documentNo, (int) $tenant->activeCompanyId());
                $this->normalizeDate($lines[0][$config['dateField']] ?? '', (int) $lines[0]['_excel_row']);
                if ($type === 'purchase') {
                    $this->normalizeOptionalDate($lines[0]['delivery_date'] ?? '', (int) $lines[0]['_excel_row'], 'delivery_date');
                    $this->normalizeOptionalDate($lines[0]['arrive_date'] ?? '', (int) $lines[0]['_excel_row'], 'arrive_date');
                }
            } catch (Throwable $exception) {
                foreach ($lines as $line) {
                    $errors[] = ['excel_row' => $line['_excel_row'] ?? '-', 'document_no' => $documentNo, 'item_code' => $line['item_code'] ?? '', 'message' => $exception->getMessage()];
                }
                continue;
            }

            foreach ($lines as $line) {
                try {
                    $payload = $this->linePayload($line, $type);
                    $validRows[] = [
                        'excel_row' => $line['_excel_row'] ?? '-',
                        'document_no' => $documentNo,
                        'line' => $payload[$config['lineField']] ?? '',
                        'partner_code' => $line[$config['partnerCodeField']] ?? '',
                        'partner_name' => $line[$config['partnerNameField']] ?? '',
                        'item_code' => $payload['item_code'] ?? '',
                        'item_name' => $payload['item_name'] ?? '',
                        'qty' => $payload['qty'] ?? 0,
                        'uom_code' => $payload['uom_code'] ?? '',
                        'unit_price' => $payload['unit_price'] ?? 0,
                    ];
                } catch (Throwable $exception) {
                    $errors[] = ['excel_row' => $line['_excel_row'] ?? '-', 'document_no' => $documentNo, 'item_code' => $line['item_code'] ?? '', 'message' => $exception->getMessage()];
                }
            }
        }

        $validDocuments = count(array_unique(array_column($validRows, 'document_no')));
        $message = $errors === []
            ? 'Preview valid. ' . $validDocuments . ' dokumen dan ' . count($validRows) . ' line siap diposting.'
            : 'Preview menemukan ' . count($errors) . ' error. Perbaiki file lalu upload ulang.';

        return [
            'type' => $type,
            'filename' => $filename,
            'company_id' => $tenant->activeCompanyId(),
            'site_id' => $tenant->activeSiteId(),
            'records' => $records,
            'documents' => count($groups),
            'valid_documents' => $validDocuments,
            'lines' => count($records),
            'valid_lines' => count($validRows),
            'valid_rows' => $validRows,
            'errors' => $errors,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function documentConsistencyError(array $lines, array $config): ?string
    {
        if ($lines === []) {
            return null;
        }

        $first = $lines[0];
        $checks = [
            $config['dateField'] => 'document date',
            $config['partnerCodeField'] => 'partner code',
            $config['partnerNameField'] => 'partner name',
            'currency_code' => 'currency',
            'terms_code' => 'terms',
        ];
        foreach (($config['headerFields'] ?? []) as $field => $label) {
            $checks[$field] = $label;
        }

        foreach ($lines as $line) {
            foreach ($checks as $field => $label) {
                $firstValue = trim((string) ($first[$field] ?? ''));
                $lineValue = trim((string) ($line[$field] ?? ''));
                if ($lineValue !== '' && $firstValue !== '' && $firstValue !== $lineValue) {
                    return 'Rows with the same document number must use the same ' . $label . '.';
                }
            }
        }

        $lineNumbers = [];
        foreach ($lines as $line) {
            $lineNo = (int) ($line[$config['lineField']] ?? 0);
            if ($lineNo < 1) {
                return strtoupper($config['lineField']) . ' must be a positive number.';
            }
            if (isset($lineNumbers[$lineNo])) {
                return 'Duplicate ' . strtoupper($config['lineField']) . ': ' . $lineNo . '.';
            }
            $lineNumbers[$lineNo] = true;
        }

        $expected = 1;
        foreach (array_keys($lineNumbers) as $lineNo) {
            if ((int) $lineNo !== $expected) {
                return strtoupper($config['lineField']) . ' must be sequential starting from 1. Expected line ' . $expected . ', got ' . $lineNo . '.';
            }
            $expected++;
        }

        return null;
    }

    private function createDocuments(string $type, array $records, TenantContext $tenant): array
    {
        if ($records === []) {
            throw new RuntimeException('No valid order rows found in the uploaded file.');
        }

        $config = $this->config($type);
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

            if ($type === 'purchase') {
                $header += [
                    'delivery_date' => $this->normalizeOptionalDate($first['delivery_date'] ?? '', (int) $first['_excel_row'], 'delivery_date'),
                    'arrive_date' => $this->normalizeOptionalDate($first['arrive_date'] ?? '', (int) $first['_excel_row'], 'arrive_date'),
                    'remarks' => trim((string) ($first['remarks'] ?? '')),
                    'discount_percent' => $this->number($first['discount_percent'] ?? 0),
                    'discount_amount' => $this->number($first['discount_amount'] ?? 0),
                    'freight_amount' => $this->number($first['freight_amount'] ?? 0),
                    'other_amount' => $this->number($first['other_amount'] ?? 0),
                    'special_charge_amount' => $this->number($first['special_charge_amount'] ?? 0),
                    'vat_amount' => $this->number($first['vat_amount'] ?? 0),
                    'wht_amount' => $this->number($first['wht_amount'] ?? 0),
                ];
            }

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

        $payload = [
            $type === 'sales' ? 'so_line' : 'po_line' => (int) ($line[$type === 'sales' ? 'so_line' : 'po_line'] ?? 0),
            'item_id' => isset($item['id']) ? (int) $item['id'] : null,
            'item_code' => $code !== '' ? $code : null,
            'item_name' => $name !== '' ? $name : $code,
            'description' => trim((string) ($line['description'] ?? '')),
            'qty' => $qty,
            'uom_code' => $uom,
            'unit_price' => $price,
        ];

        if ($type === 'sales') {
            $payload['discount_amount'] = $this->number($line['discount_amount'] ?? 0);
            $payload['tax_amount'] = $this->number($line['tax_amount'] ?? 0);
        }

        return $payload;
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
        if ($file === null || ! $file->isValid()) return 'Please upload a valid Excel or CSV file.';
        if ($file->getSize() < 1) return 'Uploaded file is empty.';
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) return 'Uploaded file is too large. Maximum allowed size is 10 MB.';
        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'csv', 'tsv'], true)) return 'Only .xlsx, .csv, or .tsv files are supported.';
        return null;
    }

    private function assertNotDuplicate(string $table, string $documentField, string $documentNo, int $companyId): void
    {
        $db = Database::connect();
        $builder = $db->table($table)->where('company_id', $companyId)->groupStart()->where($documentField, $documentNo);
        if ($documentField !== 'document_no' && $db->fieldExists('document_no', $table)) {
            $builder->orWhere('document_no', $documentNo);
        }
        $builder->groupEnd();
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($builder->countAllResults() > 0) {
            throw new RuntimeException(strtoupper(str_replace('_no', '', $documentField)) . ' number already exists: ' . $documentNo);
        }
    }

    private function lookupPartner(string $type, string $code): ?array
    {
        $code = trim($code);
        if ($code === '') return null;

        $table = $type === 'sales' ? 'customers' : 'suppliers';
        $legacyCode = $type === 'sales' ? 'customer' : 'supplier';
        $legacyName = $type === 'sales' ? 'customern' : 'supplierna';
        $db = Database::connect();
        $builder = $db->table($table);
        $builder->groupStart()->where($legacyCode, $code)->orWhere('code', $code)->groupEnd();
        $this->scopeBuilder($builder, $table);
        $row = $builder->get()->getRowArray();
        if ($row === null) return null;

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
        if ($code === '') return null;

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
        if ($db->fieldExists('company_id', $table) && $tenant->activeCompanyId() !== null) $builder->where('company_id', $tenant->activeCompanyId());
        if ($db->fieldExists('site_id', $table) && $tenant->activeSiteId() !== null) $builder->where('site_id', $tenant->activeSiteId());
        if ($db->fieldExists('deleted_at', $table)) $builder->where('deleted_at', null);
    }

    private function normalizeDate(mixed $value, int $rowNumber): string
    {
        $value = trim((string) $value);
        if ($value === '') throw new RuntimeException('Document date is required on Excel row ' . $rowNumber . '.');
        if (is_numeric($value) && (float) $value > 20000) return gmdate('Y-m-d', ((int) floor((float) $value) - 25569) * 86400);
        $timestamp = strtotime($value);
        if ($timestamp === false) throw new RuntimeException('Invalid document date on Excel row ' . $rowNumber . '. Use YYYY-MM-DD.');
        return date('Y-m-d', $timestamp);
    }

    private function normalizeOptionalDate(mixed $value, int $rowNumber, string $label): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (is_numeric($value) && (float) $value > 20000) return gmdate('Y-m-d', ((int) floor((float) $value) - 25569) * 86400);
        $timestamp = strtotime($value);
        if ($timestamp === false) throw new RuntimeException('Invalid ' . $label . ' on Excel row ' . $rowNumber . '. Use YYYY-MM-DD.');
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
            if (trim((string) $value) !== '') return false;
        }
        return true;
    }

    private function number(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') return 0.0;
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
        if ($code !== null && $code !== '') return (string) $code;
        $tenant = new TenantContext(session());
        $id = $type === 'company' ? $tenant->activeCompanyId() : $tenant->activeSiteId();
        if ($id === null) return null;
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
                    ['SO-IMPORT-001', '1', date('Y-m-d'), 'CUST001', 'PT Contoh Customer', 'NET30', 'IDR', 'Contoh import SO', 'ITEM-0001', 'Kertas A4 80gsm 001', '10', 'PCS', '25000', '0', '0'],
                    ['SO-IMPORT-001', '2', date('Y-m-d'), 'CUST001', 'PT Contoh Customer', 'NET30', 'IDR', 'Contoh import SO', 'ITEM-0002', 'Pulpen Hitam 002', '5', 'PCS', '5000', '0', '0'],
                ],
                'sheetName' => 'Sales Order Import',
                'fileName' => 'sales-order-import-template.xlsx',
                'importUrl' => 'sales/orders/import',
                'commitUrl' => 'sales/orders/import/commit',
                'templateUrl' => 'sales/orders/import-template',
                'backUrl' => 'sales/orders',
                'table' => 'sales_orders',
                'documentField' => 'so_no',
                'lineField' => 'so_line',
                'dateField' => 'so_date',
                'partnerIdField' => 'customer_id',
                'partnerLegacyField' => 'customer',
                'partnerCodeField' => 'customer_code',
                'partnerNameField' => 'customer_name',
                'headerFields' => [],
            ];
        }

        return [
            'title' => 'Import Purchase Orders',
            'label' => 'Purchase Order',
            'headers' => self::PURCHASE_HEADERS,
            'sampleRows' => [
                ['PO-IMPORT-001', '1', date('Y-m-d'), date('Y-m-d', strtotime('+3 days')), date('Y-m-d', strtotime('+5 days')), 'SUP001', 'PT Contoh Supplier', 'NET30', 'IDR', 'Contoh import PO', 'Header remarks', '2.5', '0', '15000', '0', '5000', '11000', '0', 'ITEM-0001', 'Kertas A4 80gsm 001', 'Description line 1', '20', 'PCS', '20000'],
                ['PO-IMPORT-001', '2', date('Y-m-d'), date('Y-m-d', strtotime('+3 days')), date('Y-m-d', strtotime('+5 days')), 'SUP001', 'PT Contoh Supplier', 'NET30', 'IDR', 'Contoh import PO', 'Header remarks', '2.5', '0', '15000', '0', '5000', '11000', '0', 'ITEM-0002', 'Pulpen Hitam 002', 'Description line 2', '12', 'PCS', '4000'],
            ],
            'sheetName' => 'Purchase Order Import',
            'fileName' => 'purchase-order-import-template.xlsx',
            'importUrl' => 'purchase/orders/import',
            'commitUrl' => 'purchase/orders/import/commit',
            'templateUrl' => 'purchase/orders/import-template',
            'backUrl' => 'purchase/orders',
            'table' => 'purchase_orders',
            'documentField' => 'po_no',
            'lineField' => 'po_line',
            'dateField' => 'po_date',
            'partnerIdField' => 'supplier_id',
            'partnerLegacyField' => 'supplier',
            'partnerCodeField' => 'supplier_code',
            'partnerNameField' => 'supplier_name',
            'headerFields' => [
                'delivery_date' => 'delivery date',
                'arrive_date' => 'arrive date',
                'remarks' => 'remarks',
                'discount_percent' => 'discount percent',
                'discount_amount' => 'discount amount',
                'freight_amount' => 'freight amount',
                'other_amount' => 'other amount',
                'special_charge_amount' => 'special charge amount',
                'vat_amount' => 'VAT amount',
                'wht_amount' => 'WHT amount',
            ],
        ];
    }

    private function previewFromRequest(string $type): ?array
    {
        $token = trim((string) $this->request->getGet('preview'));
        if ($token === '') return null;
        $preview = $this->getPreview($token);
        if ($preview === null || ($preview['type'] ?? '') !== $type) return null;
        return $preview;
    }

    private function storePreview(string $token, array $preview): void
    {
        $previews = session(self::SESSION_KEY) ?? [];
        $previews[$token] = $preview;
        session()->set(self::SESSION_KEY, $previews);
    }

    private function getPreview(string $token): ?array
    {
        if ($token === '') return null;
        $previews = session(self::SESSION_KEY) ?? [];
        return is_array($previews[$token] ?? null) ? $previews[$token] : null;
    }

    private function clearPreview(string $token): void
    {
        $previews = session(self::SESSION_KEY) ?? [];
        unset($previews[$token]);
        session()->set(self::SESSION_KEY, $previews);
    }
}
