<?php

namespace App\Services\Purchase;

use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Services\AuditLogService;
use App\Services\Finance\PeriodCloseService;
use Config\Database;
use RuntimeException;
use Throwable;

class PurchaseOrderService
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
                $lineModel->insert($this->linePayload($poId, $line, $status));
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

    public function update(int $poId, array $header, array $lines, ?int $userId = null): void
    {
        $this->validateHeader($header, $lines);

        $poModel = new PurchaseOrderModel();
        $lineModel = new PurchaseOrderLineModel();
        $po = $poModel->find($poId);
        if ($po === null) {
            throw new RuntimeException('Purchase order not found.');
        }
        $this->assertPeriodOpen($header + $po);

        $status = (string) ($po['document_status'] ?? $po['status'] ?? 'draft');
        if (! in_array($status, ['draft', 'submitted', 'approved'], true)) {
            throw new RuntimeException('PO status ' . $status . ' cannot be edited.');
        }

        $existingLines = $lineModel->where('purchase_order_id', $poId)->findAll();
        foreach ($existingLines as $existingLine) {
            if ((float) ($existingLine['qty_received'] ?? 0) > 0) {
                throw new RuntimeException('PO cannot be edited because one or more lines have already been received.');
            }
        }

        $lines = $this->normalizeLines($lines);
        $totals = $this->calculateTotals($lines, $header);
        $header['document_no'] = $header['document_no'] ?? $header['po_no'];
        $header['document_date'] = $header['document_date'] ?? $header['po_date'];

        $db = Database::connect();
        $db->transBegin();

        try {
            $poModel->update($poId, $header + $totals + [
                'updated_by' => $userId,
            ]);

            $lineModel->where('purchase_order_id', $poId)->delete();
            foreach ($lines as $line) {
                $lineModel->insert($this->linePayload($poId, $line, $status));
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to update purchase order.');
            }

            $db->transCommit();
            $this->audit('po.update', $poId, $header, $lines, $userId, 'Purchase order updated.');
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
        $this->transition($poId, ['received'], 'closed', ['closed_at' => date('Y-m-d H:i:s'), 'closed_by' => $userId], $userId, 'po.close', 'Purchase order closed.');
    }

    public function cancel(int $poId, string $reason = '', ?int $userId = null): void
    {
        $this->transition($poId, ['draft', 'submitted'], 'cancelled', ['cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => $userId, 'cancel_reason' => $reason], $userId, 'po.cancel', 'Purchase order cancelled.');
    }

    private function validateHeader(array $header, array $lines): void
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
        $this->assertPeriodOpen($po);

        $model->update($poId, $extra + ['status' => $toStatus, 'document_status' => $toStatus, 'updated_by' => $userId]);
        $this->audit($action, $poId, $po, ['to_status' => $toStatus] + $extra, $userId, $description);
    }

    private function assertPeriodOpen(array $document): void
    {
        (new PeriodCloseService())->assertOpen(
            'purchase',
            (int) ($document['company_id'] ?? 0),
            (string) ($document['po_date'] ?? $document['document_date'] ?? date('Y-m-d')),
            ! empty($document['site_id']) ? (int) $document['site_id'] : null
        );
    }

    public function calculateTotals(array $lines, array $header = []): array
    {
        $subtotal = 0.0;
        $lineDiscount = 0.0;
        $lineVat = 0.0;
        $lineWht = 0.0;

        foreach ($lines as $line) {
            $qty = (float) ($line['qty'] ?? $line['qty_ordered'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $subtotal += $qty * $unitPrice;
            $lineDiscount += (float) ($line['discount_amount'] ?? 0);
            $lineVat += (float) ($line['vat_amount'] ?? 0);
            $lineWht += (float) ($line['wht_amount'] ?? 0);
        }

        $discountPercent = (float) ($header['discount_percent'] ?? 0);
        $manualDiscountAmount = (float) ($header['discount_amount'] ?? 0);
        $discountPercentAmount = round($subtotal * $discountPercent / 100, 2);
        $totalDiscountAmount = round($discountPercentAmount + $manualDiscountAmount + $lineDiscount, 2);

        $freight = (float) ($header['freight_amount'] ?? 0);
        $other = (float) ($header['other_amount'] ?? 0);
        $special = (float) ($header['special_charge_amount'] ?? 0);
        $vat = (float) ($header['vat_amount'] ?? 0) + $lineVat;
        $wht = (float) ($header['wht_amount'] ?? 0) + $lineWht;
        $total = $subtotal - $totalDiscountAmount + $freight + $other + $special + $vat - $wht;

        return [
            'subtotal_amount' => round($subtotal, 2),
            'discount_percent' => $discountPercent,
            'discount_amount' => round($manualDiscountAmount, 2),
            'freight_amount' => round($freight, 2),
            'other_amount' => round($other, 2),
            'special_charge_amount' => round($special, 2),
            'vat_amount' => round($vat, 2),
            'wht_amount' => round($wht, 2),
            'tax_amount' => round($vat - $wht, 2),
            'total_amount' => round($total, 2),
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

            $qty = (float) ($line['qty'] ?? $line['qty_ordered'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $gross = round($qty * $unitPrice, 2);
            $discountPercent = (float) ($line['discount_percent'] ?? 0);
            $discountAmount = (float) ($line['discount_amount'] ?? 0);
            if ($discountPercent > 0) {
                $discountAmount += round($gross * $discountPercent / 100, 2);
            }
            $vatAmount = (float) ($line['vat_amount'] ?? 0);
            $whtAmount = (float) ($line['wht_amount'] ?? 0);

            $line['po_line'] = $displayLine;
            $line['line_no'] = $displayLine;
            $line['discount_percent'] = $discountPercent;
            $line['discount_amount'] = $discountAmount;
            $line['vat_amount'] = $vatAmount;
            $line['wht_amount'] = $whtAmount;
            $line['tax_amount'] = $vatAmount - $whtAmount;
            $line['line_total'] = $gross - $discountAmount + $vatAmount - $whtAmount;

            $seen[$displayLine] = true;
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

    private function linePayload(int $poId, array $line, string $status = 'draft'): array
    {
        $lineNo = (int) $line['po_line'];
        $qty = (float) ($line['qty'] ?? 0);

        return [
            'purchase_order_id' => $poId,
            'line_no' => $lineNo,
            'po_line' => $lineNo,
            'item_id' => $line['item_id'] ?? null,
            'item_code' => $line['item_code'] ?? null,
            'item_name' => $line['item_name'] ?? null,
            'description' => $line['description'] ?? null,
            'qty' => $qty,
            'qty_ordered' => $qty,
            'qty_received' => 0,
            'qty_outstanding' => $qty,
            'uom_code' => $line['uom_code'] ?? null,
            'unit_price' => (float) ($line['unit_price'] ?? 0),
            'discount_percent' => (float) ($line['discount_percent'] ?? 0),
            'discount_amount' => (float) ($line['discount_amount'] ?? 0),
            'vat_amount' => (float) ($line['vat_amount'] ?? 0),
            'wht_amount' => (float) ($line['wht_amount'] ?? 0),
            'tax_amount' => (float) ($line['tax_amount'] ?? 0),
            'line_total' => (float) ($line['line_total'] ?? 0),
            'line_status' => $status === 'approved' ? 'approved' : 'open',
        ];
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
