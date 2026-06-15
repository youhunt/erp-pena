<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Libraries\XlsxSheetReader;
use App\Libraries\XlsxSheetWriter;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\Purchase\PurchaseReceiptService;
use App\Services\Sales\SalesDeliveryService;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;
use Throwable;

class FulfillmentImportController extends BaseController
{
    private const SESSION_KEY = 'fulfillment_import_previews';
    private const MAX_UPLOAD_BYTES = 10485760;

    private const RECEIPT_HEADERS = [
        'receipt_no', 'receipt_date', 'po_no', 'warehouse_code', 'location_code',
        'item_code', 'qty_received', 'batch_no', 'notes',
    ];

    private const DELIVERY_HEADERS = [
        'delivery_no', 'delivery_date', 'so_no', 'warehouse_code', 'location_code',
        'item_code', 'qty_delivered', 'batch_no', 'notes',
    ];

    public function purchaseReceiptForm(): string
    {
        return $this->form('purchase_receipt');
    }

    public function salesDeliveryForm(): string
    {
        return $this->form('sales_delivery');
    }

    public function purchaseReceiptTemplate()
    {
        return $this->template('purchase_receipt');
    }

    public function salesDeliveryTemplate()
    {
        return $this->template('sales_delivery');
    }

    public function importPurchaseReceipt()
    {
        return $this->import('purchase_receipt');
    }

    public function importSalesDelivery()
    {
        return $this->import('sales_delivery');
    }

    public function commitPurchaseReceipt()
    {
        return $this->commit('purchase_receipt');
    }

    public function commitSalesDelivery()
    {
        return $this->commit('sales_delivery');
    }

    private function form(string $type): string
    {
        $config = $this->config($type);

        return view('system/fulfillment_import/form', [
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
        if (($tenant->activeCompanyId() ?? 0) < 1) {
            return redirect()->to(site_url($config['importUrl']))->with('error', 'Active company is required before importing.');
        }

        $file = $this->request->getFile('import_file');
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
            $result = $this->postDocuments($type, $preview['documents_payload'] ?? []);
        } catch (Throwable $exception) {
            return redirect()->to(site_url($config['importUrl'] . '?preview=' . $token))->with('error', $exception->getMessage());
        }

        $this->clearPreview($token);

        return redirect()
            ->to(site_url($config['backUrl']))
            ->with('message', 'Import selesai. ' . $result['documents'] . ' dokumen diposting, ' . $result['lines'] . ' line diproses.');
    }

    private function buildPreview(string $type, array $records, TenantContext $tenant, string $filename): array
    {
        if ($records === []) {
            throw new RuntimeException('No valid rows found in the uploaded file.');
        }

        $config = $this->config($type);
        $groups = [];
        $errors = [];
        foreach ($records as $record) {
            $documentNo = trim((string) ($record[$config['documentField']] ?? ''));
            if ($documentNo === '') {
                $errors[] = $this->errorRow($record, '-', 'Document number is required.', $config);
                continue;
            }
            $groups[$documentNo][] = $record;
        }

        $validRows = [];
        $documentsPayload = [];

        foreach ($groups as $documentNo => $lines) {
            $consistencyError = $this->documentConsistencyError($lines, $config);
            if ($consistencyError !== null) {
                foreach ($lines as $line) {
                    $errors[] = $this->errorRow($line, $documentNo, $consistencyError, $config);
                }
                continue;
            }

            $first = $lines[0];
            try {
                $source = $this->sourceDocument($type, (string) ($first[$config['sourceField']] ?? ''), $tenant);
                $this->assertSourceStatus($type, $source);
                $this->assertNotDuplicate($config['targetTable'], $config['documentField'], $documentNo, (int) $tenant->activeCompanyId());
                $date = $this->normalizeDate($first[$config['dateField']] ?? '', (int) $first['_excel_row']);
                $warehouse = $this->lookupMaster('warehouses', (string) ($first['warehouse_code'] ?? ''), $tenant);
                $location = $this->lookupMaster('locations', (string) ($first['location_code'] ?? ''), $tenant);
                $this->assertLocationBelongsToWarehouse($location, $warehouse);
            } catch (Throwable $exception) {
                foreach ($lines as $line) {
                    $errors[] = $this->errorRow($line, $documentNo, $exception->getMessage(), $config);
                }
                continue;
            }

            $linePayload = [];
            foreach ($lines as $line) {
                try {
                    $sourceLine = $this->sourceLine($type, (int) $source['id'], (string) ($line['item_code'] ?? ''));
                    $qty = $this->number($line[$config['qtyField']] ?? 0);
                    $outstanding = (float) ($sourceLine['qty_outstanding'] ?? $sourceLine['qty'] ?? 0);
                    if ($qty <= 0) {
                        throw new RuntimeException('Qty must be greater than zero.');
                    }
                    if ($qty > $outstanding) {
                        throw new RuntimeException('Qty cannot exceed outstanding qty for item ' . ($sourceLine['item_code'] ?? '-') . '. Outstanding: ' . $outstanding);
                    }

                    $linePayload[] = [
                        $config['lineIdField'] => (int) $sourceLine['id'],
                        $config['qtyField'] => $qty,
                        'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    ];
                    $validRows[] = [
                        'excel_row' => $line['_excel_row'],
                        'document_no' => $documentNo,
                        'source_no' => $source[$config['sourceField']] ?? $first[$config['sourceField']] ?? '',
                        'item_code' => $sourceLine['item_code'] ?? '',
                        'item_name' => $sourceLine['item_name'] ?? '',
                        'qty' => $qty,
                        'uom_code' => $sourceLine['uom_code'] ?? '',
                        'warehouse_code' => $warehouse['code'] ?? ($first['warehouse_code'] ?? ''),
                        'location_code' => $location['code'] ?? ($first['location_code'] ?? ''),
                    ];
                } catch (Throwable $exception) {
                    $errors[] = $this->errorRow($line, $documentNo, $exception->getMessage(), $config);
                }
            }

            if ($linePayload !== []) {
                $documentsPayload[] = [
                    'header' => $this->documentHeader($type, $source, $first, $documentNo, $date, $warehouse, $location),
                    'lines' => $linePayload,
                ];
            }
        }

        $message = $errors === []
            ? 'Preview valid. ' . count($documentsPayload) . ' dokumen dan ' . count($validRows) . ' line siap diposting.'
            : 'Preview menemukan ' . count($errors) . ' error. Perbaiki file lalu upload ulang.';

        return [
            'type' => $type,
            'filename' => $filename,
            'company_id' => $tenant->activeCompanyId(),
            'site_id' => $tenant->activeSiteId(),
            'documents' => count($groups),
            'valid_documents' => count($documentsPayload),
            'lines' => count($records),
            'valid_lines' => count($validRows),
            'valid_rows' => $validRows,
            'errors' => $errors,
            'documents_payload' => $documentsPayload,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function postDocuments(string $type, array $documents): array
    {
        if ($documents === []) {
            throw new RuntimeException('No valid documents to post.');
        }

        $posted = 0;
        $lines = 0;
        foreach ($documents as $document) {
            if ($type === 'purchase_receipt') {
                (new PurchaseReceiptService())->post($document['header'], $document['lines'], auth()->id());
            } else {
                (new SalesDeliveryService())->post($document['header'], $document['lines'], auth()->id());
            }
            $posted++;
            $lines += count($document['lines']);
        }

        return ['documents' => $posted, 'lines' => $lines];
    }

    private function documentHeader(string $type, array $source, array $first, string $documentNo, string $date, ?array $warehouse, ?array $location): array
    {
        if ($type === 'purchase_receipt') {
            return [
                'company_id' => $source['company_id'],
                'site_id' => $source['site_id'] ?? null,
                'company' => $source['company'] ?? session('active_company_code'),
                'site' => $source['site'] ?? session('active_site_code'),
                'receipt_no' => $documentNo,
                'receipt_date' => $date,
                'purchase_order_id' => $source['id'],
                'po_no' => $source['po_no'],
                'supplier_id' => $source['supplier_id'] ?? null,
                'supplier_code' => $source['supplier_code'] ?? $source['supplier'] ?? null,
                'supplier_name' => $source['supplier_name'] ?? null,
                'warehouse_id' => $warehouse['id'] ?? null,
                'location_id' => $location['id'] ?? null,
                'notes' => trim((string) ($first['notes'] ?? '')),
            ];
        }

        return [
            'company_id' => $source['company_id'],
            'site_id' => $source['site_id'] ?? null,
            'company' => $source['company'] ?? session('active_company_code'),
            'site' => $source['site'] ?? session('active_site_code'),
            'delivery_no' => $documentNo,
            'delivery_date' => $date,
            'sales_order_id' => $source['id'],
            'so_no' => $source['so_no'],
            'customer_id' => $source['customer_id'] ?? null,
            'customer_code' => $source['customer_code'] ?? $source['customer'] ?? null,
            'customer_name' => $source['customer_name'] ?? null,
            'warehouse_id' => $warehouse['id'] ?? null,
            'location_id' => $location['id'] ?? null,
            'notes' => trim((string) ($first['notes'] ?? '')),
        ];
    }

    private function sourceDocument(string $type, string $number, TenantContext $tenant): array
    {
        $number = trim($number);
        if ($number === '') {
            throw new RuntimeException('Source document number is required.');
        }

        $model = $type === 'purchase_receipt' ? new PurchaseOrderModel() : new SalesOrderModel();
        $field = $type === 'purchase_receipt' ? 'po_no' : 'so_no';
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        $source = $model->where($field, $number)->first();
        if ($source === null) {
            throw new RuntimeException('Source document not found: ' . $number);
        }

        return $source;
    }

    private function sourceLine(string $type, int $sourceId, string $itemCode): array
    {
        $itemCode = trim($itemCode);
        if ($itemCode === '') {
            throw new RuntimeException('Item code is required.');
        }

        $model = $type === 'purchase_receipt' ? new PurchaseOrderLineModel() : new SalesOrderLineModel();
        $sourceField = $type === 'purchase_receipt' ? 'purchase_order_id' : 'sales_order_id';
        $line = $model
            ->where($sourceField, $sourceId)
            ->where('item_code', $itemCode)
            ->where('qty_outstanding >', 0)
            ->orderBy('line_no', 'ASC')
            ->first();
        if ($line === null) {
            throw new RuntimeException('Outstanding source line not found for item ' . $itemCode . '.');
        }

        return $line;
    }

    private function assertSourceStatus(string $type, array $source): void
    {
        $status = (string) ($source['document_status'] ?? $source['status'] ?? 'draft');
        $allowed = $type === 'purchase_receipt' ? ['approved', 'partial_received'] : ['approved', 'reserved', 'partial_delivered'];
        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException('Source document status is not allowed: ' . $status);
        }
    }

    private function assertNotDuplicate(string $table, string $documentField, string $documentNo, int $companyId): void
    {
        $db = Database::connect();
        $builder = $db->table($table)->where('company_id', $companyId)->where($documentField, $documentNo);
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($builder->countAllResults() > 0) {
            throw new RuntimeException('Document number already exists: ' . $documentNo);
        }
    }

    private function lookupMaster(string $table, string $code, TenantContext $tenant): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $db = Database::connect();
        $builder = $db->table($table)->where('code', $code);
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        $row = $builder->get()->getRowArray();
        if ($row === null) {
            throw new RuntimeException(ucfirst(str_replace('_', ' ', rtrim($table, 's'))) . ' not found: ' . $code);
        }

        return $row;
    }

    private function assertLocationBelongsToWarehouse(?array $location, ?array $warehouse): void
    {
        if ($location === null || $warehouse === null || ! isset($location['warehouse_id'])) {
            return;
        }

        if ((int) $location['warehouse_id'] !== (int) $warehouse['id']) {
            throw new RuntimeException('Location ' . ($location['code'] ?? '-') . ' does not belong to warehouse ' . ($warehouse['code'] ?? '-') . '.');
        }
    }

    private function documentConsistencyError(array $lines, array $config): ?string
    {
        if ($lines === []) {
            return null;
        }

        $first = $lines[0];
        foreach ([$config['dateField'], $config['sourceField'], 'warehouse_code', 'location_code'] as $field) {
            foreach ($lines as $line) {
                $firstValue = trim((string) ($first[$field] ?? ''));
                $lineValue = trim((string) ($line[$field] ?? ''));
                if ($lineValue !== '' && $firstValue !== '' && $firstValue !== $lineValue) {
                    return 'Rows with the same document number must use the same ' . $field . '.';
                }
            }
        }

        return null;
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
                if ($header !== '') {
                    $record[$header] = trim((string) ($row[$position] ?? ''));
                }
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

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded file.');
        }

        $rows = [];
        $delimiter = $extension === 'tsv' ? "\t" : ',';
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

    private function errorRow(array $row, string $documentNo, string $message, array $config): array
    {
        return [
            'excel_row' => $row['_excel_row'] ?? '-',
            'document_no' => $documentNo,
            'source_no' => $row[$config['sourceField']] ?? '',
            'item_code' => $row['item_code'] ?? '',
            'message' => $message,
        ];
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

        return $value === '' ? 0.0 : (float) str_replace(',', '', $value);
    }

    private function previewFromRequest(string $type): ?array
    {
        $token = trim((string) $this->request->getGet('preview'));
        if ($token === '') {
            return null;
        }
        $preview = $this->getPreview($token);

        return $preview !== null && ($preview['type'] ?? '') === $type ? $preview : null;
    }

    private function storePreview(string $token, array $preview): void
    {
        $previews = session(self::SESSION_KEY) ?? [];
        $previews[$token] = $preview;
        session()->set(self::SESSION_KEY, $previews);
    }

    private function getPreview(string $token): ?array
    {
        $previews = session(self::SESSION_KEY) ?? [];

        return is_array($previews[$token] ?? null) ? $previews[$token] : null;
    }

    private function clearPreview(string $token): void
    {
        $previews = session(self::SESSION_KEY) ?? [];
        unset($previews[$token]);
        session()->set(self::SESSION_KEY, $previews);
    }

    private function config(string $type): array
    {
        if ($type === 'purchase_receipt') {
            return [
                'title' => 'Import Purchase Receipts',
                'label' => 'Purchase Receipt',
                'headers' => self::RECEIPT_HEADERS,
                'sampleRows' => [
                    ['PR-IMPORT-001', date('Y-m-d'), 'PO-IMPORT-001', 'MAIN', 'A01', 'ITEM-0001', '10', 'BATCH-001', 'Contoh receipt import'],
                    ['PR-IMPORT-001', date('Y-m-d'), 'PO-IMPORT-001', 'MAIN', 'A01', 'ITEM-0002', '5', 'BATCH-002', 'Contoh receipt import'],
                ],
                'sheetName' => 'Purchase Receipt Import',
                'fileName' => 'purchase-receipt-import-template.xlsx',
                'importUrl' => 'purchase/receipts/import',
                'commitUrl' => 'purchase/receipts/import/commit',
                'templateUrl' => 'purchase/receipts/import-template',
                'backUrl' => 'purchase/receipts',
                'targetTable' => 'purchase_receipts',
                'documentField' => 'receipt_no',
                'dateField' => 'receipt_date',
                'sourceField' => 'po_no',
                'qtyField' => 'qty_received',
                'lineIdField' => 'purchase_order_line_id',
            ];
        }

        return [
            'title' => 'Import Delivery Orders',
            'label' => 'Delivery Order',
            'headers' => self::DELIVERY_HEADERS,
            'sampleRows' => [
                ['DO-IMPORT-001', date('Y-m-d'), 'SO-IMPORT-001', 'MAIN', 'A01', 'ITEM-0001', '4', 'BATCH-001', 'Contoh delivery import'],
                ['DO-IMPORT-001', date('Y-m-d'), 'SO-IMPORT-001', 'MAIN', 'A01', 'ITEM-0002', '2', 'BATCH-002', 'Contoh delivery import'],
            ],
            'sheetName' => 'Delivery Order Import',
            'fileName' => 'delivery-order-import-template.xlsx',
            'importUrl' => 'sales/deliveries/import',
            'commitUrl' => 'sales/deliveries/import/commit',
            'templateUrl' => 'sales/deliveries/import-template',
            'backUrl' => 'sales/deliveries',
            'targetTable' => 'sales_deliveries',
            'documentField' => 'delivery_no',
            'dateField' => 'delivery_date',
            'sourceField' => 'so_no',
            'qtyField' => 'qty_delivered',
            'lineIdField' => 'sales_order_line_id',
        ];
    }
}
