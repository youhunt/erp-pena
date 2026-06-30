<?php

namespace App\Services\Sales;

use App\Services\AuditLogService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Inventory\InventoryStockService;
use Config\Database;
use RuntimeException;
use Throwable;

class SalesOrderActivationService
{
    public function activate(int $soId, ?int $userId = null): void
    {
        $db = Database::connect();
        $so = $db->table('sales_orders')->where('id', $soId)->get(1)->getRowArray();
        if ($so === null) {
            throw new RuntimeException('Sales order not found.');
        }

        $current = strtolower((string) ($so['document_status'] ?? $so['status'] ?? 'draft'));
        if ($current === 'draft') {
            throw new RuntimeException('SO is already draft.');
        }
        if (in_array($current, ['delivered', 'partial_delivered', 'invoiced'], true)) {
            throw new RuntimeException('SO status ' . $current . ' cannot be returned directly to draft. Cancel A/R receipt, cancel invoice, then reverse delivery first.');
        }

        $this->assertNoOpenDownstreamDocuments($soId);
        $this->assertPeriodOpen($so);

        $db->transBegin();
        try {
            $allocationLines = $db->table('allocationline')
                ->where('sales_order_id', $soId)
                ->get()
                ->getResultArray();

            $stock = new InventoryStockService();
            foreach ($allocationLines as $allocationLine) {
                $qty = (float) ($allocationLine['allocateqty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $stock->releaseReservation([
                    'company_id' => $so['company_id'],
                    'site_id' => $so['site_id'] ?? null,
                    'warehouse_id' => $this->warehouseId((int) ($so['company_id'] ?? 0), $so['site_id'] ?? null, (string) ($allocationLine['whs'] ?? '')),
                    'location_id' => $this->locationId((int) ($so['company_id'] ?? 0), $so['site_id'] ?? null, (string) ($allocationLine['loc'] ?? '')),
                    'item_code' => (string) ($allocationLine['itemcode'] ?? ''),
                    'batch_no' => trim((string) ($allocationLine['batchno'] ?? '')),
                    'uom_code' => (string) ($allocationLine['allocateuom'] ?? 'PCS'),
                    'qty' => $qty,
                ], $userId);
            }

            if ($allocationLines === []) {
                $lines = $db->table('sales_order_lines')->where('sales_order_id', $soId)->get()->getResultArray();
                foreach ($lines as $line) {
                    $reserved = (float) ($line['qty_reserved'] ?? 0);
                    if ($reserved <= 0) {
                        continue;
                    }
                    $stock->releaseReservation([
                        'company_id' => $so['company_id'],
                        'site_id' => $so['site_id'] ?? null,
                        'warehouse_id' => null,
                        'location_id' => null,
                        'item_id' => $line['item_id'] ?? null,
                        'item_code' => (string) ($line['item_code'] ?? ''),
                        'uom_code' => (string) ($line['uom_code'] ?? 'PCS'),
                        'qty' => $reserved,
                    ], $userId);
                }
            }

            if ($db->tableExists('allocationorder')) {
                $payload = $this->filterPayload('allocationorder', [
                    'status' => 'cancelled',
                    'updated_by' => (string) ($userId ?? 'system'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if ($payload !== []) {
                    $db->table('allocationorder')->where('sales_order_id', $soId)->update($payload);
                }
            }

            if ($db->tableExists('allocationline')) {
                $payload = $this->filterPayload('allocationline', [
                    'active' => 0,
                    'updated_by' => (string) ($userId ?? 'system'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if ($payload !== []) {
                    $db->table('allocationline')->where('sales_order_id', $soId)->update($payload);
                }
            }

            $this->resetSalesOrderLines($soId, $userId);
            $this->resetSalesOrderHeader($soId, $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to return SO to draft.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }

        (new AuditLogService())->log('sales.order', 'so.activate', [
            'company_id' => $so['company_id'] ?? null,
            'site_id' => $so['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'sales_orders',
            'record_id' => $soId,
            'record_code' => $so['so_no'] ?? $so['document_no'] ?? (string) $soId,
            'description' => 'Sales order returned to draft and open allocation reservations were released.',
            'old_values' => ['status' => $current],
            'new_values' => ['status' => 'draft'],
        ]);
    }

    private function assertNoOpenDownstreamDocuments(int $soId): void
    {
        $db = Database::connect();

        if ($db->tableExists('sales_invoices')) {
            $invoice = $db->table('sales_invoices')
                ->where('sales_order_id', $soId)
                ->whereNotIn('status', ['cancelled', 'canceled', 'reversed'])
                ->get(1)
                ->getRowArray();
            if ($invoice !== null) {
                throw new RuntimeException('SO already has active Sales Invoice ' . ($invoice['invoice_no'] ?? '#' . $invoice['id']) . '. Cancel invoice first before returning SO to draft.');
            }
        }

        if ($db->tableExists('sales_deliveries')) {
            $delivery = $db->table('sales_deliveries')
                ->where('sales_order_id', $soId)
                ->whereNotIn('status', ['reversed', 'cancelled', 'canceled'])
                ->get(1)
                ->getRowArray();
            if ($delivery !== null) {
                throw new RuntimeException('SO already has active Delivery Order ' . ($delivery['delivery_no'] ?? '#' . $delivery['id']) . '. Reverse delivery first before returning SO to draft.');
            }
        }
    }

    private function assertPeriodOpen(array $so): void
    {
        (new PeriodCloseService())->assertOpen(
            'sales',
            (int) ($so['company_id'] ?? 0),
            (string) ($so['so_date'] ?? $so['document_date'] ?? date('Y-m-d')),
            ! empty($so['site_id']) ? (int) $so['site_id'] : null
        );
    }

    private function resetSalesOrderLines(int $soId, ?int $userId): void
    {
        $db = Database::connect();
        $lines = $db->table('sales_order_lines')->where('sales_order_id', $soId)->get()->getResultArray();
        foreach ($lines as $line) {
            if ((float) ($line['qty_delivered'] ?? 0) > 0) {
                throw new RuntimeException('SO cannot be returned to draft because line ' . ($line['line_no'] ?? $line['so_line'] ?? '#') . ' has delivered qty. Reverse delivery first.');
            }

            $ordered = (float) ($line['qty_ordered'] ?? $line['qty'] ?? 0);
            $payload = $this->filterPayload('sales_order_lines', [
                'qty_reserved' => 0,
                'allocation_qty' => 0,
                'allocationqty' => 0,
                'allocated_qty' => 0,
                'available_so_qty' => $ordered,
                'availablesoqty' => $ordered,
                'available_soqqty' => $ordered,
                'qty_outstanding' => $ordered,
                'line_status' => 'open',
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table('sales_order_lines')->where('id', (int) $line['id'])->update($payload);
        }
    }

    private function resetSalesOrderHeader(int $soId, ?int $userId): void
    {
        $payload = $this->filterPayload('sales_orders', [
            'status' => 'draft',
            'document_status' => 'draft',
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'reserved_at' => null,
            'reserved_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancel_reason' => null,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Database::connect()->table('sales_orders')->where('id', $soId)->update($payload);
    }

    private function warehouseId(int $companyId, mixed $siteId, string $code): ?int
    {
        if ($code === '') {
            return null;
        }
        $db = Database::connect();
        if (! $db->tableExists('warehouses')) {
            return null;
        }
        $builder = $db->table('warehouses')->where('code', $code);
        if ($db->fieldExists('company_id', 'warehouses')) {
            $builder->where('company_id', $companyId);
        }
        if ($db->fieldExists('site_id', 'warehouses')) {
            empty($siteId) ? $builder->where('site_id', null) : $builder->where('site_id', (int) $siteId);
        }
        $row = $builder->get(1)->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function locationId(int $companyId, mixed $siteId, string $code): ?int
    {
        if ($code === '') {
            return null;
        }
        $db = Database::connect();
        if (! $db->tableExists('locations')) {
            return null;
        }
        $builder = $db->table('locations')->where('code', $code);
        if ($db->fieldExists('company_id', 'locations')) {
            $builder->where('company_id', $companyId);
        }
        if ($db->fieldExists('site_id', 'locations')) {
            empty($siteId) ? $builder->where('site_id', null) : $builder->where('site_id', (int) $siteId);
        }
        $row = $builder->get(1)->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    /** @param array<string,mixed> $payload */
    private function filterPayload(string $table, array $payload): array
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return [];
        }

        return array_intersect_key($payload, array_flip($db->getFieldNames($table)));
    }
}
