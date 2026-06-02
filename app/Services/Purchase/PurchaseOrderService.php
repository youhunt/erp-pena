<?php

namespace App\Services\Purchase;

use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;
use Throwable;

class PurchaseOrderService
{
    public function create(array $header, array $lines, ?int $userId = null): int
    {
        if (empty($header['company_id'])) {
            throw new RuntimeException('Company is required.');
        }

        if (empty($header['po_no'])) {
            throw new RuntimeException('PO number is required.');
        }

        if (empty($header['po_date'])) {
            throw new RuntimeException('PO date is required.');
        }

        if ($lines === []) {
            throw new RuntimeException('At least one PO line is required.');
        }

        $totals = $this->calculateTotals($lines);
        $db = Database::connect();
        $db->transBegin();

        try {
            $poModel = new PurchaseOrderModel();
            $lineModel = new PurchaseOrderLineModel();

            $poId = (int) $poModel->insert($header + $totals + [
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
                    'purchase_order_id' => $poId,
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
                throw new RuntimeException('Failed to create purchase order.');
            }

            $db->transCommit();

            (new AuditLogService())->log('purchase.po', 'po.create', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'purchase_orders',
                'record_id' => $poId,
                'record_code' => $header['po_no'] ?? null,
                'description' => 'Purchase order created.',
                'new_values' => [
                    'header' => $header + $totals,
                    'lines' => $lines,
                ],
            ]);

            return $poId;
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
