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
        $lines = $this->normalizeLines($lines);

        $totals = $this->calculateTotals($lines);
        $db = Database::connect();
        $db->transBegin();

        try {
            $poModel = new PurchaseOrderModel();
            $lineModel = new PurchaseOrderLineModel();
            $status = $header['status'] ?? 'draft';
            $header['document_no'] = $header['document_no'] ?? $header['po_no'];
            $header['document_date'] = $header['document_date'] ?? $header['po_date'];

            $poModel->insert($header + $totals + [
                'status' => $status,
                'document_status' => $status,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $poId = (int) $poModel->getInsertID();
            if ($poId < 1) {
                throw new RuntimeException('Failed to create purchase order header.');
            }

            foreach ($lines as $line) {
                $lineNo = (int) $line['po_line'];
                $qty = (float) ($line['qty'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $discount = (float) ($line['discount_amount'] ?? 0);
                $tax = (float) ($line['tax_amount'] ?? 0);
                $lineTotal = ($qty * $unitPrice) - $discount + $tax;

                $lineModel->insert([
                    'purchase_order_id' => $poId,
                    'line_no' => $lineNo,
                    'po_line' => $lineNo,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'] ?? null,
                    'item_name' => $line['item_name'] ?? null,
                    'qty' => $qty,
                    'qty_ordered' => $qty,
                    'qty_received' => 0,
                    'qty_outstanding' => $qty,
                    'uom_code' => $line['uom_code'] ?? null,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'line_total' => $lineTotal,
                    'line_status' => 'open',
                ]);

            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to create purchase order.');
            }

            $db->transCommit();
            $this->audit('po.create', $poId, $header, $lines, $userId, 'Purchase order created.');

            return $poId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    public function submit(int $poId, ?int $userId = null): void
    {
        $this->transition($poId, ['draft'], 'submitted', ['submitted_at' => date('Y-m-d H:i:s'), 'submitted_by' => $userId], $userId, 'po.submit', 'Purchase order submitted.');
    }

    public function approve(int $poId, ?int $userId = null): void
    {
        $this->transition($poId, ['submitted'], 'approved', ['approved_at' => date('Y-m-d H:i:s'), 'approved_by' => $userId], $userId, 'po.approve', 'Purchase order approved.');
    }

    public function close(int $poId, ?int $userId = null): void
    {
        $this->transition($poId, ['approved', 'partial_received', 'received'], 'closed', ['closed_at' => date('Y-m-d H:i:s'), 'closed_by' => $userId], $userId, 'po.close', 'Purchase order closed.');
    }

    public function cancel(int $poId, string $reason = '', ?int $userId = null): void
    {
        $this->transition($poId, ['draft', 'submitted'], 'cancelled', ['cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => $userId, 'cancel_reason' => $reason], $userId, 'po.cancel', 'Purchase order cancelled.');
    }

    private function transition(int $poId, array $allowedFrom, string $toStatus, array $extra, ?int $userId, string $action, string $description): void
    {
        $model = new PurchaseOrderModel();
        $po = $model->find($poId);
        if ($po === null) {
            throw new RuntimeException('Purchase order not found.');
        }

        $current = (string) ($po['document_status'] ?? $po['status'] ?? 'draft');
        if (! in_array($current, $allowedFrom, true)) {
            throw new RuntimeException('PO status ' . $current . ' cannot be changed to ' . $toStatus . '.');
        }

        $model->update($poId, $extra + ['status' => $toStatus, 'document_status' => $toStatus, 'updated_by' => $userId]);
        $this->audit($action, $poId, $po, ['to_status' => $toStatus] + $extra, $userId, $description);
    }

    public function calculateTotals(array $lines): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

        foreach ($lines as $line) {
            $qty = (float) ($line['qty'] ?? $line['qty_ordered'] ?? 0);
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

    private function normalizeLines(array $lines): array
    {
        $normalized = [];
        $seen = [];
        $autoLine = 1;

        foreach ($lines as $line) {
            $displayLine = (int) ($line['po_line'] ?? $line['line_no'] ?? $autoLine);
            if ($displayLine < 1) {
                throw new RuntimeException('PO line number must be greater than zero.');
            }
            if (isset($seen[$displayLine])) {
                throw new RuntimeException('Duplicate PO line number: ' . $displayLine);
            }

            $seen[$displayLine] = true;
            $line['po_line'] = $displayLine;
            $line['line_no'] = $displayLine;
            $normalized[] = $line;
            $autoLine++;
        }

        usort($normalized, static fn (array $left, array $right): int => (int) $left['po_line'] <=> (int) $right['po_line']);

        $expected = 1;
        foreach ($normalized as $line) {
            if ((int) $line['po_line'] !== $expected) {
                throw new RuntimeException('PO line numbers must be sequential starting from 1. Expected line ' . $expected . ', got ' . $line['po_line'] . '.');
            }
            $expected++;
        }

        return $normalized;
    }

    private function audit(string $action, int $poId, array $header, array $payload, ?int $userId, string $description): void
    {
        (new AuditLogService())->log('purchase.po', $action, [
            'company_id' => $header['company_id'] ?? null,
            'site_id' => $header['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'purchase_orders',
            'record_id' => $poId,
            'record_code' => $header['po_no'] ?? null,
            'description' => $description,
            'new_values' => $payload,
        ]);
    }
}
