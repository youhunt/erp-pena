<?php

namespace App\Services\Sales;

use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;
use Throwable;

class SalesOrderService
{
    public function create(array $header, array $lines, ?int $userId = null): int
    {
        if (empty($header['company_id'])) {
            throw new RuntimeException('Company is required.');
        }

        if (empty($header['so_no'])) {
            throw new RuntimeException('SO number is required.');
        }

        if (empty($header['so_date'])) {
            throw new RuntimeException('SO date is required.');
        }

        if ($lines === []) {
            throw new RuntimeException('At least one SO line is required.');
        }

        $totals = $this->calculateTotals($lines);
        $db = Database::connect();
        $db->transBegin();

        try {
            $soModel = new SalesOrderModel();
            $lineModel = new SalesOrderLineModel();

            $soId = (int) $soModel->insert($header + $totals + [
                'status' => $header['status'] ?? 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ], true);

            $lineNo = 10;
            foreach ($lines as $line) {
                $qty = (float) ($line['qty'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $discount = (float) ($line['discount_amount'] ?? 0);
                $tax = (float) ($line['tax_amount'] ?? 0);
                $lineTotal = ($qty * $unitPrice) - $discount + $tax;

                $lineModel->insert([
                    'sales_order_id' => $soId,
                    'line_no' => $lineNo,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'] ?? null,
                    'item_name' => $line['item_name'] ?? null,
                    'qty' => $qty,
                    'uom_code' => $line['uom_code'] ?? null,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'line_total' => $lineTotal,
                ]);

                $lineNo += 10;
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to create sales order.');
            }

            $db->transCommit();

            (new AuditLogService())->log('sales.so', 'so.create', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'sales_orders',
                'record_id' => $soId,
                'record_code' => $header['so_no'] ?? null,
                'description' => 'Sales order created.',
                'new_values' => ['header' => $header + $totals, 'lines' => $lines],
            ]);

            return $soId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    public function calculateTotals(array $lines): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

        foreach ($lines as $line) {
            $qty = (float) ($line['qty'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $lineDiscount = (float) ($line['discount_amount'] ?? 0);
            $lineTax = (float) ($line['tax_amount'] ?? 0);

            $subtotal += $qty * $unitPrice;
            $discount += $lineDiscount;
            $tax += $lineTax;
        }

        return [
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $subtotal - $discount + $tax,
        ];
    }
}
