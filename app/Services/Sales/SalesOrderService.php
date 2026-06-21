<?php

namespace App\Services\Sales;

use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\AuditLogService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Inventory\InventoryStockService;
use App\Services\Support\TransactionDocumentGuard;
use Config\Database;
use RuntimeException;
use Throwable;

class SalesOrderService
{
    public function create(array $header, array $lines, ?int $userId = null): int
    {
        $this->validateHeader($header, $lines);
        $this->assertPeriodOpen($header);
        $lines = $this->normalizeLines($lines);
        $totals = $this->calculateTotals($lines, $header);
        $db = Database::connect();
        $db->transBegin();

        try {
            $soModel = new SalesOrderModel();
            $lineModel = new SalesOrderLineModel();
            $status = 'draft';
            $header['status'] = $status;
            $header['document_status'] = $status;
            $header['document_no'] = $header['document_no'] ?? $header['so_no'];
            $header['document_date'] = $header['document_date'] ?? $header['so_date'];

            $soModel->insert(array_replace($header, $totals, [
                'status' => $status,
                'document_status' => $status,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
            $soId = (int) $soModel->getInsertID();
            if ($soId < 1) {
                throw new RuntimeException('Failed to create sales order header.');
            }

            foreach ($lines as $line) {
                $lineModel->insert($this->linePayload($soId, $line, 'open'));
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to create sales order.');
            }

            $db->transCommit();
            $this->audit('so.create', $soId, $header, ['header' => array_replace($header, $totals), 'lines' => $lines], $userId, 'Sales order created.');

            return $soId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    public function update(int $soId, array $header, array $lines, ?int $userId = null): void
    {
        $this->validateHeader($header, $lines);

        $soModel = new SalesOrderModel();
        $lineModel = new SalesOrderLineModel();
        $so = $soModel->find($soId);
        if ($so === null) {
            throw new RuntimeException('Sales order not found.');
        }
        (new TransactionDocumentGuard())->assertSameTenant($so, $header, 'Sales order');
        $header = array_replace($header, [
            'company_id' => $so['company_id'],
            'site_id' => $so['site_id'] ?? null,
        ]);

        $status = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if ($status !== 'draft') {
            throw new RuntimeException('Only draft sales order can be edited. Current status: ' . $status . '.');
        }

        $existingLines = $lineModel->where('sales_order_id', $soId)->findAll();
        foreach ($existingLines as $existingLine) {
            if ((float) ($existingLine['qty_reserved'] ?? 0) > 0 || (float) ($existingLine['qty_delivered'] ?? 0) > 0) {
                throw new RuntimeException('SO cannot be edited because one or more lines have already been reserved or delivered.');
            }
        }

        $this->assertPeriodOpen($header + $so);
        $lines = $this->normalizeLines($lines);
        $totals = $this->calculateTotals($lines, $header);
        $header['document_no'] = $header['document_no'] ?? $header['so_no'];
        $header['document_date'] = $header['document_date'] ?? $header['so_date'];

        $db = Database::connect();
        $db->transBegin();

        try {
            $soModel->update($soId, array_replace($header, $totals, ['updated_by' => $userId]));

            $lineModel->where('sales_order_id', $soId)->delete();
            foreach ($lines as $line) {
                $lineModel->insert($this->linePayload($soId, $line, 'open'));
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to update sales order.');
            }

            $db->transCommit();
            $this->audit('so.update', $soId, $header, ['header' => array_replace($header, $totals), 'lines' => $lines], $userId, 'Sales order updated.');
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
        $this->assertPeriodOpen($so);
        (new PeriodCloseService())->assertOpen(
            'inventory',
            (int) ($so['company_id'] ?? 0),
            (string) ($so['so_date'] ?? $so['document_date'] ?? date('Y-m-d')),
            ! empty($so['site_id']) ? (int) $so['site_id'] : null
        );

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

    public function reopen(int $soId, ?int $userId = null): void
    {
        $this->transition($soId, ['cancelled'], 'draft', [
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancel_reason' => null,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
        ], $userId, 'so.reopen', 'Cancelled sales order reopened as draft.');
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
        $this->assertPeriodOpen($so);
        $model->update($soId, array_replace($extra, ['status' => $toStatus, 'document_status' => $toStatus, 'updated_by' => $userId]));
        $this->audit($action, $soId, $so, ['to_status' => $toStatus] + $extra, $userId, $description);
    }

    private function assertPeriodOpen(array $document): void
    {
        (new PeriodCloseService())->assertOpen(
            'sales',
            (int) ($document['company_id'] ?? 0),
            (string) ($document['so_date'] ?? $document['document_date'] ?? date('Y-m-d')),
            ! empty($document['site_id']) ? (int) $document['site_id'] : null
        );
    }

    private function validateHeader(array $header, array $lines): void
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
    }

    public function calculateTotals(array $lines, array $header = []): array
    {
        $subtotal = 0.0;
        $lineDiscount = 0.0;
        $lineCharges = 0.0;
        $tax = 0.0;

        foreach ($lines as $line) {
            $qty = (float) ($line['qty'] ?? $line['qty_ordered'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $gross = $qty * $unitPrice;
            $discountPercent = (float) ($line['discount_percent'] ?? 0);
            $discountAmount = (float) ($line['discount_amount'] ?? 0);
            if ($discountPercent > 0) {
                $discountAmount += round($gross * $discountPercent / 100, 2);
            }
            $subtotal += $gross;
            $lineDiscount += $discountAmount;
            $lineCharges += (float) ($line['freight_amount'] ?? 0) + (float) ($line['special_charge_amount'] ?? 0) + (float) ($line['other_amount'] ?? 0);
            $tax += (float) ($line['tax_amount'] ?? 0);
        }

        $headerDiscountPercent = (float) ($header['discount_percent'] ?? 0);
        $headerDiscountAmount = (float) ($header['discount_amount'] ?? 0);
        $headerDiscount = round($subtotal * $headerDiscountPercent / 100, 2) + $headerDiscountAmount;
        $headerCharges = (float) ($header['freight_amount'] ?? 0) + (float) ($header['other_amount'] ?? 0);
        $totalDiscount = round($headerDiscount + $lineDiscount, 2);
        $total = $subtotal - $totalDiscount + $headerCharges + $lineCharges + $tax;

        return [
            'subtotal_amount' => round($subtotal, 2),
            'discount_percent' => $headerDiscountPercent,
            'discount_amount' => round($headerDiscountAmount, 2),
            'freight_amount' => round((float) ($header['freight_amount'] ?? 0), 2),
            'other_amount' => round((float) ($header['other_amount'] ?? 0), 2),
            'tax_amount' => round($tax, 2),
            'total_amount' => round($total, 2),
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

            $qty = (float) ($line['qty'] ?? $line['qty_ordered'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $gross = round($qty * $unitPrice, 2);
            $discountPercent = (float) ($line['discount_percent'] ?? 0);
            $discountAmount = (float) ($line['discount_amount'] ?? 0);
            if ($discountPercent > 0) {
                $discountAmount += round($gross * $discountPercent / 100, 2);
            }
            $freight = (float) ($line['freight_amount'] ?? 0);
            $special = (float) ($line['special_charge_amount'] ?? 0);
            $other = (float) ($line['other_amount'] ?? 0);
            $tax = (float) ($line['tax_amount'] ?? 0);
            $itemCode = trim((string) ($line['item_code'] ?? ''));
            $itemName = trim((string) ($line['item_name'] ?? ''));
            if ($itemCode === '') {
                throw new RuntimeException('Item code is required on SO line ' . $displayLine . '. Please reselect the item before saving.');
            }
            if ($itemName === '') {
                $itemName = $itemCode;
            }

            $seen[$displayLine] = true;
            $line['so_line'] = $displayLine;
            $line['line_no'] = $displayLine;
            $line['item_code'] = $itemCode;
            $line['item_name'] = $itemName;
            $line['discount_percent'] = $discountPercent;
            $line['discount_amount'] = $discountAmount;
            $line['freight_amount'] = $freight;
            $line['special_charge_amount'] = $special;
            $line['other_amount'] = $other;
            $line['tax_amount'] = $tax;
            $line['line_total'] = $gross - $discountAmount + $freight + $special + $other + $tax;
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

    private function linePayload(int $soId, array $line, string $status): array
    {
        $lineNo = (int) $line['so_line'];
        $qty = (float) ($line['qty'] ?? 0);

        return [
            'sales_order_id' => $soId,
            'line_no' => $lineNo,
            'so_line' => $lineNo,
            'item_id' => $line['item_id'] ?? null,
            'item_code' => $line['item_code'] ?? null,
            'item_name' => $line['item_name'] ?? null,
            'description' => $line['description'] ?? null,
            'qty' => $qty,
            'qty_ordered' => $qty,
            'qty_reserved' => 0,
            'qty_delivered' => 0,
            'qty_outstanding' => $qty,
            'uom_code' => $line['uom_code'] ?? null,
            'unit_price' => (float) ($line['unit_price'] ?? 0),
            'discount_percent' => (float) ($line['discount_percent'] ?? 0),
            'discount_amount' => (float) ($line['discount_amount'] ?? 0),
            'freight_amount' => (float) ($line['freight_amount'] ?? 0),
            'special_charge_amount' => (float) ($line['special_charge_amount'] ?? 0),
            'other_amount' => (float) ($line['other_amount'] ?? 0),
            'tax_amount' => (float) ($line['tax_amount'] ?? 0),
            'line_total' => (float) ($line['line_total'] ?? 0),
            'line_status' => $status,
        ];
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
