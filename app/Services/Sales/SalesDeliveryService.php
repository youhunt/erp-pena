<?php

namespace App\Services\Sales;

use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Models\InventoryStockMovementModel;
use App\Services\AuditLogService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\PostingProfileService;
use App\Services\Inventory\InventoryStockService;
use Config\Database;
use RuntimeException;
use Throwable;

class SalesDeliveryService
{
    public function post(array $header, array $lines, ?int $userId = null): int
    {
        if (empty($header['company_id']) || empty($header['sales_order_id']) || empty($header['delivery_no'])) {
            throw new RuntimeException('Company, SO, and delivery number are required.');
        }
        if ($lines === []) {
            throw new RuntimeException('At least one delivery line is required.');
        }

        $soModel = new SalesOrderModel();
        $so = $soModel->find((int) $header['sales_order_id']);
        if ($so === null) {
            throw new RuntimeException('Sales order not found.');
        }

        $soStatus = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if (! in_array($soStatus, ['approved', 'reserved', 'partial_delivered'], true)) {
            throw new RuntimeException('Only approved, reserved, or partially delivered SO can be delivered. Current status: ' . $soStatus);
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $deliveryModel = new SalesDeliveryModel();
            $deliveryLineModel = new SalesDeliveryLineModel();
            $soLineModel = new SalesOrderLineModel();
            $stock = new InventoryStockService();
            $movementModel = new InventoryStockMovementModel();
            $totalCogs = 0.0;
            $postedLineCount = 0;

            $deliveryId = (int) $deliveryModel->insert($header + [
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ], true);

            foreach ($lines as $line) {
                $soLine = $soLineModel->find((int) $line['sales_order_line_id']);
                if ($soLine === null) {
                    throw new RuntimeException('SO line not found.');
                }

                $qtyDeliver = (float) ($line['qty_delivered'] ?? 0);
                $outstanding = (float) ($soLine['qty_outstanding'] ?? $soLine['qty'] ?? 0);
                if ($qtyDeliver <= 0) {
                    continue;
                }
                if ($qtyDeliver > $outstanding) {
                    throw new RuntimeException('Delivery qty cannot exceed outstanding qty for item ' . ($soLine['item_code'] ?? '-'));
                }

                $deliveryLineModel->insert([
                    'sales_delivery_id' => $deliveryId,
                    'sales_order_id' => $so['id'],
                    'sales_order_line_id' => $soLine['id'],
                    'line_no' => $soLine['line_no'],
                    'item_id' => $soLine['item_id'] ?? null,
                    'item_code' => $soLine['item_code'] ?? null,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $soLine['item_name'] ?? null,
                    'qty_delivered' => $qtyDeliver,
                    'uom_code' => $soLine['uom_code'] ?? 'PCS',
                    'unit_price' => $soLine['unit_price'] ?? 0,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'location_id' => $header['location_id'] ?? null,
                ]);
                $postedLineCount++;

                $newDelivered = (float) ($soLine['qty_delivered'] ?? 0) + $qtyDeliver;
                $newOutstanding = max(0, $outstanding - $qtyDeliver);
                $newReserved = max(0, (float) ($soLine['qty_reserved'] ?? 0) - $qtyDeliver);

                $soLineModel->update($soLine['id'], [
                    'qty_delivered' => $newDelivered,
                    'qty_outstanding' => $newOutstanding,
                    'qty_reserved' => $newReserved,
                    'line_status' => $newOutstanding <= 0 ? 'delivered' : 'partial_delivered',
                ]);

                if ((float) ($soLine['qty_reserved'] ?? 0) > 0) {
                    $stock->releaseReservation([
                        'company_id' => $header['company_id'],
                        'site_id' => $header['site_id'] ?? null,
                        'warehouse_id' => null,
                        'location_id' => null,
                        'item_id' => $soLine['item_id'] ?? null,
                        'item_code' => $soLine['item_code'] ?? '',
                        'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                        'uom_code' => $soLine['uom_code'] ?? 'PCS',
                        'qty' => $qtyDeliver,
                    ], $userId);
                }

                $movementId = $stock->stockOut([
                    'company_id' => $header['company_id'],
                    'site_id' => $header['site_id'] ?? null,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'location_id' => $header['location_id'] ?? null,
                    'item_id' => $soLine['item_id'] ?? null,
                    'item_code' => $soLine['item_code'] ?? '',
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $soLine['item_name'] ?? null,
                    'uom_code' => $soLine['uom_code'] ?? 'PCS',
                    'qty' => $qtyDeliver,
                    'unit_cost' => 0,
                    'movement_type' => 'sales_delivery',
                    'reference_type' => 'sales_delivery',
                    'reference_id' => $deliveryId,
                    'reference_no' => $header['delivery_no'],
                    'notes' => 'Stock out from SO ' . ($so['so_no'] ?? ''),
                ], $userId);

                $movement = $movementModel->find($movementId);
                $totalCogs += (float) ($movement['stock_value'] ?? 0);
            }

            if ($postedLineCount < 1) {
                throw new RuntimeException('No delivery line can be posted.');
            }

            $glEntryId = $this->postCogsGl($header, $deliveryId, round($totalCogs, 2), $userId);
            if ($glEntryId !== null) {
                $deliveryModel->update($deliveryId, ['gl_entry_id' => $glEntryId]);
            }

            $this->refreshSoStatus((int) $so['id'], $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post sales delivery.');
            }
            $db->transCommit();

            (new AuditLogService())->log('sales.delivery', 'delivery.post', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'sales_deliveries',
                'record_id' => $deliveryId,
                'record_code' => $header['delivery_no'],
                'description' => 'Sales delivery posted, stock decreased, and COGS GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'cogs_amount' => $totalCogs, 'gl_entry_id' => $glEntryId],
            ]);

            return $deliveryId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function postCogsGl(array $header, int $deliveryId, float $cogsAmount, ?int $userId): ?int
    {
        if ($cogsAmount <= 0) {
            return null;
        }

        $companyId = (int) $header['company_id'];
        $profile = new PostingProfileService();

        return (new GeneralLedgerService())->post([
            'company_id' => $companyId,
            'site_id' => $header['site_id'] ?? null,
            'journal_no' => 'GL-' . $header['delivery_no'],
            'journal_date' => $header['delivery_date'] ?? date('Y-m-d'),
            'source_module' => 'sales',
            'source_type' => 'sales_delivery_cogs',
            'source_id' => $deliveryId,
            'source_no' => $header['delivery_no'],
            'description' => 'COGS posting for delivery ' . $header['delivery_no'],
            'currency_code' => $header['currency_code'] ?? 'IDR',
        ], [
            [
                'account_no' => $profile->account($companyId, 'sales', 'cogs', '5000'),
                'description' => 'Cost of Goods Sold',
                'debit' => $cogsAmount,
                'credit' => 0,
            ],
            [
                'account_no' => $profile->account($companyId, 'sales', 'inventory', '1300'),
                'description' => 'Inventory',
                'debit' => 0,
                'credit' => $cogsAmount,
            ],
        ], $userId);
    }

    private function refreshSoStatus(int $soId, ?int $userId = null): void
    {
        $lineModel = new SalesOrderLineModel();
        $lines = $lineModel->where('sales_order_id', $soId)->findAll();
        $totalOutstanding = 0.0;
        $totalDelivered = 0.0;
        foreach ($lines as $line) {
            $totalOutstanding += (float) ($line['qty_outstanding'] ?? 0);
            $totalDelivered += (float) ($line['qty_delivered'] ?? 0);
        }

        $status = $totalOutstanding <= 0 ? 'delivered' : ($totalDelivered > 0 ? 'partial_delivered' : 'approved');
        (new SalesOrderModel())->update($soId, [
            'status' => $status,
            'document_status' => $status,
            'updated_by' => $userId,
        ]);
    }
}
