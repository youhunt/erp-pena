<?php

namespace App\Services\Sales;

use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryStockService;
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
        $lines = $this->normalizeLines($lines);

        $totals = $this->calculateTotals($lines);
        $db = Database::connect();
        $db->transBegin();

        try {
            $soModel = new SalesOrderModel();
            $lineModel = new SalesOrderLineModel();
            $status = $header['status'] ?? 'draft';
            $header['document_no'] = $header['document_no'] ?? $header['so_no'];
            $header['document_date'] = $header['document_date'] ?? $header['so_date'];

            $soModel->insert($header + $totals + [
                'status' => $status,
                'document_status' => $status,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $soId = (int) $soModel->getInsertID();
            if ($soId < 1) {
                throw new RuntimeException('Failed to create sales order header.');
            }

            foreach ($lines as $line) {
                $lineNo = (int) $line['so_line'];
                $qty = (float) ($line['qty'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $discount = (float) ($line['discount_amount'] ?? 0);
                $tax = (float) ($line['tax_amount'] ?? 0);
                $lineTotal = ($qty * $unitPrice) - $discount + $tax;

                $lineModel->insert([
                    'sales_order_id' => $soId,
                    'line_no' => $lineNo,
                    'so_line' => $lineNo,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'] ?? null,
                    'item_name' => $line['item_name'] ?? null,
                    'qty' => $qty,
                    'qty_ordered' => $qty,
                    'qty_reserved' => 0,
                    'qty_delivered' => 0,
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
                throw new RuntimeException('Failed to create sales order.');
            }

            $db->transCommit();
            $this->audit('so.create', $soId, $header, ['header' => $header + $totals, 'lines' => $lines], $userId, 'Sales order created.');

            return $soId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    public function submit(int $soId, ?int $userId = null): void
    {
        $this->transition($soId, ['draft'], 'submitted', ['submitted_at' => date('Y-m-d H:i:s'), 'submitted_by' => $userId], $userId, 'so.submit', 'Sales order submitted.');
    }

    public function approve(int $soId, ?int $userId = null): void
    {
        $this->transition($soId, ['submitted'], 'approved', ['approved_at' => date('Y-m-d H:i:s'), 'approved_by' => $userId], $userId, 'so.approve', 'Sales order approved.');
    }

    public function reserve(int $soId, ?int $userId = null): void
    {
        $soModel = new SalesOrderModel();
        $lineModel = new SalesOrderLineModel();
        $stock = new InventoryStockService();
        $so = $soModel->find($soId);
        if ($so === null) {
            throw new RuntimeException('Sales order not found.');
        }
        $status = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if (! in_array($status, ['approved', 'partial_reserved'], true)) {
            throw new RuntimeException('Only approved or partially reserved SO can be reserved. Current status: ' . $status);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $lines = $lineModel->where('sales_order_id', $soId)->findAll();
            $reservedAny = false;
            foreach ($lines as $line) {
                $ordered = (float) ($line['qty_ordered'] ?? $line['qty'] ?? 0);
                $reserved = (float) ($line['qty_reserved'] ?? 0);
                $toReserve = max(0, $ordered - $reserved);
                if ($toReserve <= 0) {
                    continue;
                }

                $stock->reserve([
                    'company_id' => $so['company_id'],
                    'site_id' => $so['site_id'] ?? null,
                    'warehouse_id' => null,
                    'location_id' => null,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'] ?? '',
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'qty' => $toReserve,
                ], $userId);

                $lineModel->update($line['id'], [
                    'qty_reserved' => $reserved + $toReserve,
                    'line_status' => 'reserved',
                ]);
                $reservedAny = true;
            }

            if (! $reservedAny) {
                throw new RuntimeException('No outstanding SO line can be reserved.');
            }

            $soModel->update($soId, [
                'status' => 'reserved',
                'document_status' => 'reserved',
                'reserved_at' => date('Y-m-d H:i:s'),
                'reserved_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to reserve sales order stock.');
            }
            $db->transCommit();
            $this->audit('so.reserve', $soId, $so, ['status' => 'reserved'], $userId, 'Sales order stock reserved.');
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    public function cancel(int $soId, string $reason = '', ?int $userId = null): void
    {
        $this->transition($soId, ['draft', 'submitted'], 'cancelled', ['cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => $userId, 'cancel_reason' => $reason], $userId, 'so.cancel', 'Sales order cancelled.');
    }

    private function transition(int $soId, array $allowedFrom, string $toStatus, array $extra, ?int $userId, string $action, string $description): void
    {
        $model = new SalesOrderModel();
        $so = $model->find($soId);
        if ($so === null) {
            throw new RuntimeException('Sales order not found.');
        }
        $current = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if (! in_array($current, $allowedFrom, true)) {
            throw new RuntimeException('SO status ' . $current . ' cannot be changed to ' . $toStatus . '.');
        }
        $model->update($soId, $extra + ['status' => $toStatus, 'document_status' => $toStatus, 'updated_by' => $userId]);
        $this->audit($action, $soId, $so, ['to_status' => $toStatus] + $extra, $userId, $description);
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
            $displayLine = (int) ($line['so_line'] ?? $line['line_no'] ?? $autoLine);
            if ($displayLine < 1) {
                throw new RuntimeException('SO line number must be greater than zero.');
            }
            if (isset($seen[$displayLine])) {
                throw new RuntimeException('Duplicate SO line number: ' . $displayLine);
            }

            $seen[$displayLine] = true;
            $line['so_line'] = $displayLine;
            $line['line_no'] = $displayLine;
            $normalized[] = $line;
            $autoLine++;
        }

        usort($normalized, static fn (array $left, array $right): int => (int) $left['so_line'] <=> (int) $right['so_line']);

        $expected = 1;
        foreach ($normalized as $line) {
            if ((int) $line['so_line'] !== $expected) {
                throw new RuntimeException('SO line numbers must be sequential starting from 1. Expected line ' . $expected . ', got ' . $line['so_line'] . '.');
            }
            $expected++;
        }

        return $normalized;
    }

    private function audit(string $action, int $soId, array $header, array $payload, ?int $userId, string $description): void
    {
        (new AuditLogService())->log('sales.so', $action, [
            'company_id' => $header['company_id'] ?? null,
            'site_id' => $header['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'sales_orders',
            'record_id' => $soId,
            'record_code' => $header['so_no'] ?? null,
            'description' => $description,
            'new_values' => $payload,
        ]);
    }
}
