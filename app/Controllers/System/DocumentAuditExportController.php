<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseReceiptLineModel;
use App\Models\PurchaseReceiptModel;
use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class DocumentAuditExportController extends BaseController
{
    public function purchaseOrders()
    {
        return $this->exportList(new PurchaseOrderModel(), 'Purchase Orders', 'purchase-orders');
    }

    public function purchaseOrder(int $id)
    {
        return $this->exportDetail(new PurchaseOrderModel(), new PurchaseOrderLineModel(), 'purchase_order_id', $id, 'Purchase Order', 'purchase-order');
    }

    public function purchaseReceipts()
    {
        return $this->exportList(new PurchaseReceiptModel(), 'Purchase Receipts', 'purchase-receipts');
    }

    public function purchaseReceipt(int $id)
    {
        return $this->exportDetail(new PurchaseReceiptModel(), new PurchaseReceiptLineModel(), 'purchase_receipt_id', $id, 'Purchase Receipt', 'purchase-receipt');
    }

    public function salesOrders()
    {
        return $this->exportList(new SalesOrderModel(), 'Sales Orders', 'sales-orders');
    }

    public function salesOrder(int $id)
    {
        return $this->exportDetail(new SalesOrderModel(), new SalesOrderLineModel(), 'sales_order_id', $id, 'Sales Order', 'sales-order');
    }

    public function salesDeliveries()
    {
        return $this->exportList(new SalesDeliveryModel(), 'Sales Deliveries', 'sales-deliveries');
    }

    public function salesDelivery(int $id)
    {
        return $this->exportDetail(new SalesDeliveryModel(), new SalesDeliveryLineModel(), 'sales_delivery_id', $id, 'Sales Delivery', 'sales-delivery');
    }

    private function exportList($model, string $title, string $slug)
    {
        $tenant = new TenantContext(session());
        $this->scope($model, $tenant);
        $this->applyFilters($model);
        $rows = $model->orderBy('id', 'DESC')->findAll(10000);

        return $this->xlsxWorkbookResponse($slug . '-' . date('Y-m-d') . '.xlsx', [
            'Summary' => $this->summaryRows($title, $rows),
            'Detail' => $this->arrayRows($rows),
        ]);
    }

    private function exportDetail($headerModel, $lineModel, string $foreignKey, int $id, string $title, string $slug)
    {
        $tenant = new TenantContext(session());
        $this->scope($headerModel, $tenant);
        $header = $headerModel->find($id);
        if ($header === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $lines = $lineModel->where($foreignKey, $id)->orderBy('line_no', 'ASC')->findAll(10000);
        $documentNo = $this->documentNo($header, $id);

        return $this->xlsxWorkbookResponse($slug . '-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $documentNo) . '.xlsx', [
            'Summary' => $this->singleSummaryRows($title, $header, $lines),
            'Header' => $this->arrayRows([$header]),
            'Lines' => $this->arrayRows($lines),
        ]);
    }

    private function scope($model, TenantContext $tenant): void
    {
        $table = $this->tableName($model);
        $db = Database::connect();
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $model->where('site_id', $tenant->activeSiteId());
        }
    }

    private function applyFilters($model): void
    {
        $db = Database::connect();
        $table = $this->tableName($model);
        $status = trim((string) $this->request->getGet('status'));
        $q = trim((string) $this->request->getGet('q'));

        if ($status !== '') {
            if ($db->fieldExists('document_status', $table)) {
                $model->where('document_status', $status);
            } elseif ($db->fieldExists('status', $table)) {
                $model->where('status', $status);
            }
        }

        if ($q !== '') {
            $searchFields = array_values(array_filter([
                'po_no',
                'receipt_no',
                'so_no',
                'delivery_no',
                'supplier_code',
                'supplier_name',
                'customer_code',
                'customer_name',
            ], static fn (string $field): bool => $db->fieldExists($field, $table)));

            if ($searchFields !== []) {
                $model->groupStart();
                foreach ($searchFields as $index => $field) {
                    $index === 0 ? $model->like($field, $q) : $model->orLike($field, $q);
                }
                $model->groupEnd();
            }
        }
    }

    private function tableName($model): string
    {
        return method_exists($model, 'getTable') ? $model->getTable() : $model->table;
    }

    private function summaryRows(string $title, array $rows): array
    {
        $statusCounts = [];
        $amountTotal = 0.0;
        foreach ($rows as $row) {
            $status = (string) ($row['document_status'] ?? $row['status'] ?? 'unknown');
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            foreach (['grand_total', 'total_amount', 'net_total', 'subtotal'] as $amountField) {
                if (array_key_exists($amountField, $row)) {
                    $amountTotal += (float) $row[$amountField];
                    break;
                }
            }
        }

        $summary = [
            ['Metric', 'Value'],
            ['Report', $title],
            ['Rows', count($rows)],
            ['Amount Total', $amountTotal],
            ['Generated At', date('Y-m-d H:i:s')],
            ['', ''],
            ['Status', 'Count'],
        ];
        foreach ($statusCounts as $status => $count) {
            $summary[] = [$status, $count];
        }

        return $summary;
    }

    private function singleSummaryRows(string $title, array $header, array $lines): array
    {
        return [
            ['Metric', 'Value'],
            ['Report', $title],
            ['Document No', $this->documentNo($header, (int) ($header['id'] ?? 0))],
            ['Document Date', $this->documentDate($header)],
            ['Status', (string) ($header['document_status'] ?? $header['status'] ?? '-')],
            ['Partner Code', (string) ($header['supplier_code'] ?? $header['customer_code'] ?? '-')],
            ['Partner Name', (string) ($header['supplier_name'] ?? $header['customer_name'] ?? '-')],
            ['Line Count', count($lines)],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function arrayRows(array $rows): array
    {
        if ($rows === []) {
            return [['No Data']];
        }

        $headers = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $headers[$key] = true;
            }
        }
        $headers = array_keys($headers);
        $exportRows = [$headers];

        foreach ($rows as $row) {
            $exportRows[] = array_map(static fn (string $key) => $row[$key] ?? '', $headers);
        }

        return $exportRows;
    }

    private function documentNo(array $row, int $id): string
    {
        foreach (['po_no', 'receipt_no', 'so_no', 'delivery_no', 'invoice_no', 'payment_no'] as $field) {
            if (! empty($row[$field])) {
                return (string) $row[$field];
            }
        }

        return (string) $id;
    }

    private function documentDate(array $row): string
    {
        foreach (['po_date', 'receipt_date', 'so_date', 'delivery_date', 'invoice_date', 'payment_date'] as $field) {
            if (! empty($row[$field])) {
                return (string) $row[$field];
            }
        }

        return '-';
    }

    /**
     * @param array<string, array<int, array<int|string, mixed>>> $sheets
     */
    private function xlsxWorkbookResponse(string $filename, array $sheets)
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new RuntimeException('PhpSpreadsheet is required to generate Excel files. Run composer install.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetIndex = 0;
        foreach ($sheets as $sheetTitle => $rows) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(substr((string) $sheetTitle, 0, 31));
            $sheet->fromArray($rows, null, 'A1');
            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestRow();
            if ($highestRow >= 1) {
                $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
                $sheet->setAutoFilter('A1:' . $highestColumn . max(1, $highestRow));
            }
            foreach (range('A', $highestColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheetIndex++;
        }
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean() ?: '';
        $spreadsheet->disconnectWorksheets();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }
}
