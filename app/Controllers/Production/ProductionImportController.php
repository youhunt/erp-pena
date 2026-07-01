<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Libraries\XlsxSheetReader;
use App\Libraries\XlsxSheetWriter;
use App\Models\ProductionBomLineModel;
use App\Models\ProductionBomModel;
use App\Models\ProductionRoutingLineModel;
use App\Models\ProductionRoutingModel;
use App\Models\ProductionWorkCenterModel;
use App\Models\ProductionWorkOrderComponentModel;
use App\Models\ProductionWorkOrderModel;
use App\Models\ProductionWorkOrderRoutingModel;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class ProductionImportController extends BaseController
{
    private const MAX = 10485760;
    private const KEY = 'production_import_preview_';

    public function form(string $resource): string
    {
        $config = $this->cfg($resource);

        return view('production/imports/form', [
            'title' => 'Import ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
        ]);
    }

    public function template(string $resource)
    {
        $config = $this->cfg($resource);

        return $this->xlsx(
            'production-' . $resource . '-template.xlsx',
            [$config['headers'], $config['sample']],
            $config['title'] . ' Import'
        );
    }

    public function import(string $resource)
    {
        $config = $this->cfg($resource);
        $token = trim((string) $this->request->getPost('commit_token'));
        if ($token !== '') {
            return $this->commit($resource, $token);
        }

        $file = $this->request->getFile('excel_file');
        $error = $this->uploadError($file);
        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        try {
            $rows = $this->rows($file->getTempName(), $config['headers']);
            $preview = $this->preview($resource, $rows);
            $token = null;
            if (! $preview['has_errors']) {
                $token = bin2hex(random_bytes(16));
                session()->set(self::KEY . $token, [
                    'resource' => $resource,
                    'rows' => $rows,
                    'at' => time(),
                ]);
            }
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return view('production/imports/preview', [
            'title' => 'Preview Import ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'preview' => $preview,
            'token' => $token,
        ]);
    }

    private function commit(string $resource, string $token)
    {
        $config = $this->cfg($resource);
        $key = self::KEY . $token;
        $preview = session($key);
        if (! is_array($preview) || ($preview['resource'] ?? '') !== $resource || empty($preview['rows'])) {
            return redirect()->to(site_url('production/imports/' . $resource))->with('error', 'Preview session sudah habis. Upload ulang file import.');
        }

        $validation = $this->preview($resource, $preview['rows']);
        if ($validation['has_errors']) {
            return view('production/imports/preview', [
                'title' => 'Preview Import ' . $config['title'],
                'resource' => $resource,
                'config' => $config,
                'preview' => $validation,
                'token' => null,
            ]);
        }

        try {
            $result = match ($resource) {
                'boms' => $this->saveBoms($preview['rows']),
                'work-centers' => $this->saveWorkCenters($preview['rows']),
                'routings' => $this->saveRoutings($preview['rows']),
                'work-orders' => $this->saveWorkOrders($preview['rows']),
                default => throw PageNotFoundException::forPageNotFound(),
            };
        } catch (RuntimeException $e) {
            return redirect()->to(site_url('production/imports/' . $resource))->with('error', $e->getMessage());
        }

        session()->remove($key);

        return redirect()->to(site_url($config['return_to']))->with(
            'message',
            sprintf('%s import committed. %d created, %d updated, %d lines processed.', $config['title'], $result['created'], $result['updated'], $result['lines'])
        );
    }

    private function cfg(string $resource): array
    {
        $map = [
            'boms' => [
                'title' => 'BOM',
                'return_to' => 'production/boms',
                'headers' => ['site_code', 'department_code', 'warehouse_code', 'parent_item_code', 'bom_type', 'qty_batch', 'uom_code', 'ratio_percent', 'description', 'active_date', 'inactive_date', 'line_no', 'child_item_code', 'component_type', 'qty_used', 'line_uom_code', 'factor', 'line_description'],
                'sample' => ['HO', 'PROD', 'MAIN', 'FG-001', 'standard', '1', 'PCS', '100', 'Example BOM', '', '', '10', 'RM-001', 'material', '2', 'PCS', '1', 'Material line'],
                'required' => ['site_code', 'department_code', 'parent_item_code', 'line_no', 'child_item_code'],
                'group' => ['site_code', 'department_code', 'warehouse_code', 'parent_item_code'],
                'line_required' => true,
            ],
            'work-centers' => [
                'title' => 'Work Center',
                'return_to' => 'production/work-centers',
                'headers' => ['site_code', 'department_code', 'warehouse_code', 'work_center_code', 'description', 'machine_code', 'notes', 'speed', 'capacity_percent', 'cost_type', 'cost_amount', 'cost_uom', 'active_date', 'inactive_date'],
                'sample' => ['HO', 'PROD', 'MAIN', 'WC-001', 'Cutting Machine', 'MC-001', '', '100', '100', 'LABOR', '0', 'Hour', '', ''],
                'required' => ['site_code', 'department_code', 'warehouse_code', 'work_center_code'],
                'line_required' => false,
            ],
            'routings' => [
                'title' => 'Routing',
                'return_to' => 'production/routings',
                'headers' => ['site_code', 'department_code', 'warehouse_code', 'item_code', 'description', 'line_no', 'routing_name', 'work_center_code', 'operation_type', 'hour_qty', 'hour_uom', 'std_speed', 'speed_uom', 'notes'],
                'sample' => ['HO', 'PROD', 'MAIN', 'FG-001', 'Routing FG-001', '10', 'Cutting', 'WC-001', 'process', '1', 'Hour', '100', 'Unit/Hour', ''],
                'required' => ['site_code', 'department_code', 'item_code', 'line_no', 'work_center_code'],
                'group' => ['site_code', 'item_code'],
                'line_required' => true,
            ],
            'work-orders' => [
                'title' => 'Work Order',
                'return_to' => 'production/work-orders',
                'headers' => [
                    'wo_code', 'wo_no', 'wo_date', 'site_code', 'department_code', 'warehouse_code', 'work_center_code',
                    'parent_item_code', 'parent_item_name', 'batch_qty', 'wo_qty', 'uom_code', 'std_qty_finished', 'act_qty_finished', 'description',
                    'component_line_no', 'component_item_code', 'component_item_name', 'qty_used', 'component_uom_code', 'component_whs', 'component_loc', 'component_batch_no', 'booking_qty',
                    'routing_line_no', 'routing_name', 'route_work_center_code', 'work_center_name', 'hour_qty', 'route_uom',
                ],
                'sample' => ['WO', 'WO-202607-0001', date('Y-m-d'), 'HO', 'GEN', 'MAIN', 'WC-ASSY', 'ITEM-0001', 'Kertas A4 80gsm 001', '1', '10', 'PCS', '10', '0', 'Import WO header; component/routing boleh kosong karena otomatis dari BOM & Routing', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
                'required' => ['site_code', 'department_code', 'wo_no', 'wo_date', 'parent_item_code', 'wo_qty'],
                'group' => ['wo_no'],
                'line_required' => false,
            ],
        ];

        if (! isset($map[$resource])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $map[$resource];
    }

    private function preview(string $resource, array $rows): array
    {
        $config = $this->cfg($resource);
        $companyId = $this->company();
        $out = [];
        $seen = [];
        $valid = 0;
        $hasErrors = false;

        foreach ($rows as $excelRow => $row) {
            $errors = [];
            $warnings = [];

            foreach ($config['required'] as $field) {
                if (trim((string) ($row[$field] ?? '')) === '') {
                    $errors[] = $field . ' wajib diisi';
                }
            }

            $siteCode = trim((string) ($row['site_code'] ?? ''));
            if ($siteCode !== '' && ! $this->findSite($siteCode, $companyId)) {
                $errors[] = 'site_code ' . $siteCode . ' tidak ditemukan';
            }

            if (! empty($config['line_required'])) {
                $lineNo = (int) ($row['line_no'] ?? 0);
                if ($lineNo < 1) {
                    $errors[] = 'line_no harus > 0';
                }
                $key = $this->g($config['group'] ?? [], $row) . '|' . $lineNo;
                if ($lineNo > 0 && isset($seen[$key])) {
                    $errors[] = 'duplicate line_no ' . $lineNo . ' dalam group yang sama';
                }
                $seen[$key] = true;
            }

            if ($resource === 'work-orders') {
                $this->validateWorkOrderDetailColumns($row, $errors);
                if (! empty($row['wo_no'])) {
                    $existing = (new ProductionWorkOrderModel())->where(['company_id' => $companyId, 'wo_no' => (string) $row['wo_no']])->first();
                    if ($existing && ($existing['status'] ?? 'draft') !== 'draft') {
                        $errors[] = 'WO existing bukan draft';
                    }
                }
            }

            foreach (['parent_item_code', 'child_item_code', 'component_item_code'] as $itemField) {
                if (! empty($row[$itemField]) && ! $this->item((string) $row[$itemField])) {
                    $warnings[] = $itemField . ' belum ada di master item';
                }
            }

            if ($errors !== []) {
                $hasErrors = true;
            } else {
                $valid++;
            }

            $out[] = [
                'row_number' => $excelRow,
                'status' => $errors ? 'error' : 'valid',
                'errors' => $errors,
                'warnings' => $warnings,
                'data' => $row,
            ];
        }

        return [
            'total' => count($out),
            'valid' => $valid,
            'error' => count($out) - $valid,
            'has_errors' => $hasErrors,
            'rows' => $out,
        ];
    }

    private function validateWorkOrderDetailColumns(array $row, array &$errors): void
    {
        if (trim((string) ($row['component_item_code'] ?? '')) !== '') {
            $lineNo = (int) ($row['component_line_no'] ?? $row['line_no'] ?? 0);
            if ($lineNo < 1) {
                $errors[] = 'component_line_no harus > 0 jika component_item_code diisi';
            }
        }

        if (trim((string) ($row['route_work_center_code'] ?? '')) !== '') {
            $lineNo = (int) ($row['routing_line_no'] ?? $row['line_no'] ?? 0);
            if ($lineNo < 1) {
                $errors[] = 'routing_line_no harus > 0 jika route_work_center_code diisi';
            }
        }
    }

    private function saveBoms(array $rows): array
    {
        $company = $this->company();
        $groups = $this->groups($rows, ['site_code', 'department_code', 'warehouse_code', 'parent_item_code']);

        return $this->tx(function () use ($groups, $company): array {
            $created = $updated = $lines = 0;
            foreach ($groups as $group) {
                $header = $group['h'];
                $siteId = $this->siteId($header['site_code'], $company, $group['r']);
                $item = $this->item($header['parent_item_code']);
                $model = new ProductionBomModel();
                $where = [
                    'company_id' => $company,
                    'site_code' => $header['site_code'],
                    'department_code' => $header['department_code'],
                    'warehouse_code' => $header['warehouse_code'] ?? '',
                    'parent_item_code' => $header['parent_item_code'],
                ];
                $data = $where + [
                    'site_id' => $siteId,
                    'parent_item_id' => $item['id'] ?? null,
                    'parent_item_name' => $this->itemName($item, $header['parent_item_code']),
                    'bom_type' => $header['bom_type'] ?? 'standard',
                    'qty_batch' => $this->num($header['qty_batch'] ?? 1),
                    'uom_code' => $header['uom_code'] ?? 'PCS',
                    'ratio_percent' => $this->num($header['ratio_percent'] ?? 100),
                    'description' => $header['description'] ?? '',
                    'active_date' => $this->ndt($header['active_date'] ?? null),
                    'inactive_date' => $this->ndt($header['inactive_date'] ?? null),
                    'is_active' => 1,
                    'updated_by' => auth()->id(),
                ];
                $existing = $model->where($where)->first();
                if ($existing) {
                    $id = (int) $existing['id'];
                    $model->update($id, $data);
                    (new ProductionBomLineModel())->where('production_bom_id', $id)->delete();
                    $updated++;
                } else {
                    $data['created_by'] = auth()->id();
                    $id = (int) $model->insert($data, true);
                    $created++;
                }

                $lineModel = new ProductionBomLineModel();
                foreach ($group['rows'] as $row) {
                    $child = $this->item($row['child_item_code']);
                    $lineModel->insert([
                        'production_bom_id' => $id,
                        'child_no' => (int) $row['line_no'],
                        'child_item_id' => $child['id'] ?? null,
                        'child_item_code' => $row['child_item_code'],
                        'child_item_name' => $this->itemName($child, $row['child_item_code']),
                        'component_type' => $row['component_type'] ?? 'material',
                        'qty_used' => $this->num($row['qty_used'] ?? 0),
                        'uom_code' => $row['line_uom_code'] ?? $header['uom_code'] ?? 'PCS',
                        'factor' => $this->num($row['factor'] ?? 1),
                        'description' => $row['line_description'] ?? '',
                    ]);
                    $lines++;
                }
            }

            return ['created' => $created, 'updated' => $updated, 'lines' => $lines];
        }, 'BOM import failed.');
    }

    private function saveWorkCenters(array $rows): array
    {
        $company = $this->company();

        return $this->tx(function () use ($rows, $company): array {
            $model = new ProductionWorkCenterModel();
            $created = $updated = $lines = 0;
            foreach ($rows as $excelRow => $row) {
                $siteId = $this->siteId($row['site_code'], $company, $excelRow);
                $where = [
                    'company_id' => $company,
                    'site_code' => $row['site_code'],
                    'department_code' => $row['department_code'],
                    'warehouse_code' => $row['warehouse_code'],
                    'work_center_code' => $row['work_center_code'],
                ];
                $data = $where + [
                    'site_id' => $siteId,
                    'description' => $row['description'] ?? '',
                    'machine_code' => $row['machine_code'] ?? '',
                    'notes' => $row['notes'] ?? '',
                    'speed' => $this->num($row['speed'] ?? 0),
                    'capacity_percent' => $this->num($row['capacity_percent'] ?? 100),
                    'cost_type' => $row['cost_type'] ?? '',
                    'cost_amount' => $this->num($row['cost_amount'] ?? 0),
                    'cost_uom' => $row['cost_uom'] ?? '',
                    'active_date' => $this->nd($row['active_date'] ?? null),
                    'inactive_date' => $this->nd($row['inactive_date'] ?? null),
                    'is_active' => 1,
                    'updated_by' => auth()->id(),
                ];
                $existing = $model->where($where)->first();
                if ($existing) {
                    $model->update((int) $existing['id'], $data);
                    $updated++;
                } else {
                    $data['created_by'] = auth()->id();
                    $model->insert($data);
                    $created++;
                }
                $lines++;
            }

            return ['created' => $created, 'updated' => $updated, 'lines' => $lines];
        }, 'Work Center import failed.');
    }

    private function saveRoutings(array $rows): array
    {
        $company = $this->company();
        $groups = $this->groups($rows, ['site_code', 'item_code']);

        return $this->tx(function () use ($groups, $company): array {
            $created = $updated = $lines = 0;
            foreach ($groups as $group) {
                $header = $group['h'];
                $siteId = $this->siteId($header['site_code'], $company, $group['r']);
                $item = $this->item($header['item_code']);
                $model = new ProductionRoutingModel();
                $where = [
                    'company_id' => $company,
                    'site_code' => $header['site_code'],
                    'item_code' => $header['item_code'],
                ];
                $data = $where + [
                    'site_id' => $siteId,
                    'department_code' => $header['department_code'],
                    'warehouse_code' => $header['warehouse_code'] ?? '',
                    'item_id' => $item['id'] ?? null,
                    'description' => $header['description'] ?? '',
                    'is_active' => 1,
                    'updated_by' => auth()->id(),
                ];
                $existing = $model->where($where)->first();
                if ($existing) {
                    $id = (int) $existing['id'];
                    $model->update($id, $data);
                    (new ProductionRoutingLineModel())->where('production_routing_id', $id)->delete();
                    $updated++;
                } else {
                    $data['created_by'] = auth()->id();
                    $id = (int) $model->insert($data, true);
                    $created++;
                }

                $lineModel = new ProductionRoutingLineModel();
                foreach ($group['rows'] as $row) {
                    $lineModel->insert([
                        'production_routing_id' => $id,
                        'route_no' => (string) (int) $row['line_no'],
                        'routing_name' => $row['routing_name'] ?? '',
                        'work_center_code' => $row['work_center_code'],
                        'operation_type' => $row['operation_type'] ?? 'process',
                        'hour_qty' => $this->num($row['hour_qty'] ?? 0),
                        'hour_uom' => $row['hour_uom'] ?? 'Hour',
                        'std_speed' => $this->num($row['std_speed'] ?? 0),
                        'speed_uom' => $row['speed_uom'] ?? 'Unit/Hour',
                        'notes' => $row['notes'] ?? '',
                    ]);
                    $lines++;
                }
            }

            return ['created' => $created, 'updated' => $updated, 'lines' => $lines];
        }, 'Routing import failed.');
    }

    private function saveWorkOrders(array $rows): array
    {
        $company = $this->company();
        $groups = $this->groups($rows, ['wo_no']);

        return $this->tx(function () use ($groups, $company): array {
            $created = $updated = $lines = 0;
            foreach ($groups as $woNo => $group) {
                $header = $group['h'];
                $siteId = $this->siteId($header['site_code'], $company, $group['r']);
                $item = $this->item($header['parent_item_code']);
                $bom = $this->bom($company, $header['site_code'], $header['parent_item_code']);
                if ($bom === null && ! $this->hasManualComponents($group['rows'])) {
                    throw new RuntimeException('Row ' . $group['r'] . ': BOM tidak ditemukan untuk parent item ' . $header['parent_item_code'] . '. Isi BOM master dulu atau isi component line di file import.');
                }
                $routing = $this->routing($company, $header['site_code'], $header['parent_item_code']);
                $batchQty = $this->num($header['batch_qty'] ?? ($bom['qty_batch'] ?? 1));
                $batchQty = $batchQty > 0 ? $batchQty : 1.0;
                $woQty = $this->num($header['wo_qty'] ?? 0);
                $scale = $woQty / $batchQty;

                $model = new ProductionWorkOrderModel();
                $existing = $model->where(['company_id' => $company, 'wo_no' => $woNo])->first();
                $data = [
                    'company_id' => $company,
                    'site_id' => $siteId,
                    'company' => session('active_company_code') ?: null,
                    'site' => $header['site_code'],
                    'wo_code' => $header['wo_code'] ?? 'WO',
                    'wo_no' => $woNo,
                    'wo_date' => $this->date($header['wo_date'] ?? date('Y-m-d')),
                    'site_code' => $header['site_code'],
                    'department_code' => $header['department_code'],
                    'warehouse_code' => $header['warehouse_code'] ?? '',
                    'work_center_code' => $header['work_center_code'] ?? '',
                    'parent_item_id' => $item['id'] ?? null,
                    'parent_item_code' => $header['parent_item_code'],
                    'parent_item_name' => $header['parent_item_name'] ?? $this->itemName($item, $header['parent_item_code']),
                    'bom_id' => $bom['id'] ?? null,
                    'routing_id' => $routing['id'] ?? null,
                    'batch_qty' => $batchQty,
                    'wo_qty' => $woQty,
                    'std_qty_finished' => $this->num($header['std_qty_finished'] ?? $woQty),
                    'act_qty_finished' => $this->num($header['act_qty_finished'] ?? 0),
                    'uom_code' => $header['uom_code'] ?? ($bom['uom_code'] ?? 'PCS'),
                    'description' => $header['description'] ?? '',
                    'status' => 'draft',
                    'updated_by' => auth()->id(),
                ];

                if ($existing) {
                    if (($existing['status'] ?? 'draft') !== 'draft') {
                        throw new RuntimeException('WO ' . $woNo . ' bukan draft.');
                    }
                    $id = (int) $existing['id'];
                    $model->update($id, $data);
                    (new ProductionWorkOrderComponentModel())->where('production_work_order_id', $id)->delete();
                    (new ProductionWorkOrderRoutingModel())->where('production_work_order_id', $id)->delete();
                    $updated++;
                } else {
                    $data['created_by'] = auth()->id();
                    $id = (int) $model->insert($data, true);
                    $created++;
                }

                $lines += $this->saveWorkOrderComponents($id, $group['rows'], $bom, $scale, $header);
                $lines += $this->saveWorkOrderRoutings($id, $group['rows'], $routing, $scale, $company);
            }

            return ['created' => $created, 'updated' => $updated, 'lines' => $lines];
        }, 'Work Order import failed.');
    }

    private function saveWorkOrderComponents(int $workOrderId, array $rows, ?array $bom, float $scale, array $header): int
    {
        $model = new ProductionWorkOrderComponentModel();
        $count = 0;
        if ($this->hasManualComponents($rows)) {
            foreach ($rows as $row) {
                $code = trim((string) ($row['component_item_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $item = $this->item($code);
                $qty = $this->num($row['qty_used'] ?? 0);
                $model->insert([
                    'production_work_order_id' => $workOrderId,
                    'line_no' => (int) ($row['component_line_no'] ?? $row['line_no'] ?? ($count + 1)),
                    'component_item_id' => $item['id'] ?? null,
                    'component_item_code' => $code,
                    'component_item_name' => $row['component_item_name'] ?? $this->itemName($item, $code),
                    'qty_used' => $qty,
                    'uom_code' => $row['component_uom_code'] ?? $row['line_uom_code'] ?? 'PCS',
                    'warehouse_code' => $row['component_whs'] ?? $row['warehouse_code'] ?? $header['warehouse_code'] ?? null,
                    'location_code' => $row['component_loc'] ?? null,
                    'batch_no' => $row['component_batch_no'] ?? null,
                    'booking_qty' => $this->num($row['booking_qty'] ?? $qty),
                    'allocated_qty' => 0,
                    'issued_qty' => 0,
                    'line_status' => 'open',
                ]);
                $count++;
            }

            return $count;
        }

        if ($bom === null) {
            return 0;
        }

        foreach ((new ProductionBomLineModel())->where('production_bom_id', $bom['id'])->orderBy('child_no', 'ASC')->findAll() as $line) {
            $qty = round((float) ($line['qty_used'] ?? 0) * $scale, 12);
            $model->insert([
                'production_work_order_id' => $workOrderId,
                'line_no' => (int) $line['child_no'],
                'component_item_id' => $line['child_item_id'] ?? null,
                'component_item_code' => $line['child_item_code'],
                'component_item_name' => $line['child_item_name'] ?? null,
                'qty_used' => $qty,
                'uom_code' => $line['uom_code'] ?? 'PCS',
                'warehouse_code' => $header['warehouse_code'] ?? $bom['warehouse_code'] ?? null,
                'location_code' => null,
                'batch_no' => null,
                'booking_qty' => $qty,
                'allocated_qty' => 0,
                'issued_qty' => 0,
                'line_status' => 'open',
            ]);
            $count++;
        }

        return $count;
    }

    private function saveWorkOrderRoutings(int $workOrderId, array $rows, ?array $routing, float $scale, int $company): int
    {
        $model = new ProductionWorkOrderRoutingModel();
        $count = 0;
        if ($this->hasManualRoutings($rows)) {
            foreach ($rows as $row) {
                $workCenterCode = trim((string) ($row['route_work_center_code'] ?? ''));
                if ($workCenterCode === '') {
                    continue;
                }
                $workCenter = $this->workCenter($company, $workCenterCode);
                $model->insert([
                    'production_work_order_id' => $workOrderId,
                    'line_no' => (int) ($row['routing_line_no'] ?? $row['line_no'] ?? ($count + 1)),
                    'routing_name' => $row['routing_name'] ?? '',
                    'work_center_code' => $workCenterCode,
                    'work_center_name' => $row['work_center_name'] ?? ($workCenter['description'] ?? $workCenterCode),
                    'hour_qty' => $this->num($row['hour_qty'] ?? 0),
                    'uom_code' => $row['route_uom'] ?? 'Hour',
                ]);
                $count++;
            }

            return $count;
        }

        if ($routing === null) {
            return 0;
        }

        foreach ((new ProductionRoutingLineModel())->where('production_routing_id', $routing['id'])->orderBy('route_no', 'ASC')->findAll() as $line) {
            $workCenter = $this->workCenter($company, (string) $line['work_center_code']);
            $model->insert([
                'production_work_order_id' => $workOrderId,
                'line_no' => (int) $line['route_no'],
                'routing_name' => $line['routing_name'] ?? null,
                'work_center_code' => $line['work_center_code'],
                'work_center_name' => $workCenter['description'] ?? $line['work_center_code'],
                'hour_qty' => round((float) ($line['hour_qty'] ?? 0) * $scale, 8),
                'uom_code' => $line['hour_uom'] ?? 'Hour',
            ]);
            $count++;
        }

        return $count;
    }

    private function hasManualComponents(array $rows): bool
    {
        foreach ($rows as $row) {
            if (trim((string) ($row['component_item_code'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function hasManualRoutings(array $rows): bool
    {
        foreach ($rows as $row) {
            if (trim((string) ($row['route_work_center_code'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function rows(string $path, array $allowed): array
    {
        $content = file_get_contents($path);
        if (! $content) {
            throw new RuntimeException('Uploaded file is empty.');
        }

        $raw = str_starts_with($content, 'PK') ? (new XlsxSheetReader())->readFirstSheet($path) : $this->csv($content);
        if (! $raw) {
            throw new RuntimeException('Uploaded file has no rows.');
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader($value), $raw[0]);
        $allowedLookup = array_flip($allowed + ['line_no']);
        $out = [];
        foreach (array_slice($raw, 1) as $index => $rawRow) {
            $row = [];
            foreach ($headers as $position => $name) {
                if ($name === '' || ! isset($allowedLookup[$name])) {
                    continue;
                }
                $row[$name] = $this->normalizeValue($name, $rawRow[$position] ?? '');
            }
            $row = $this->normalizeWorkOrderLegacyLineColumns($row);
            if (array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                $out[$index + 2] = $row;
            }
        }

        if (! $out) {
            throw new RuntimeException('Uploaded file has no data rows.');
        }

        return $out;
    }

    private function normalizeHeader(mixed $value): string
    {
        $header = strtolower(trim((string) $value));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        $header = trim($header, '_');

        $aliases = [
            'site' => 'site_code',
            'dept' => 'department_code',
            'department' => 'department_code',
            'warehouse' => 'warehouse_code',
            'whs' => 'warehouse_code',
            'work_center' => 'work_center_code',
            'workcenter' => 'work_center_code',
            'wo_number' => 'wo_no',
            'work_order_no' => 'wo_no',
            'work_order_number' => 'wo_no',
            'parent_item' => 'parent_item_code',
            'item_parent' => 'parent_item_code',
            'qty_wo' => 'wo_qty',
            'quantity_wo' => 'wo_qty',
            'std_finished_qty' => 'std_qty_finished',
            'standard_finished_qty' => 'std_qty_finished',
            'actual_finished_qty' => 'act_qty_finished',
            'act_finished_qty' => 'act_qty_finished',
            'uom' => 'uom_code',
            'component' => 'component_item_code',
            'component_code' => 'component_item_code',
            'component_name' => 'component_item_name',
            'component_uom' => 'component_uom_code',
            'component_uom_code' => 'component_uom_code',
            'component_whs' => 'component_whs',
            'component_warehouse' => 'component_whs',
            'component_loc' => 'component_loc',
            'component_location' => 'component_loc',
            'component_batch' => 'component_batch_no',
            'component_batch_no' => 'component_batch_no',
            'no' => 'line_no',
            'line' => 'line_no',
            'routing_work_center' => 'route_work_center_code',
            'route_work_center' => 'route_work_center_code',
            'routing_work_center_code' => 'route_work_center_code',
            'work_center_name' => 'work_center_name',
            'hour' => 'hour_qty',
            'hour_uom' => 'route_uom',
        ];

        return $aliases[$header] ?? $header;
    }

    private function normalizeWorkOrderLegacyLineColumns(array $row): array
    {
        if (isset($row['line_no'])) {
            if (! isset($row['component_line_no']) && ! empty($row['component_item_code'])) {
                $row['component_line_no'] = $row['line_no'];
            }
            if (! isset($row['routing_line_no']) && ! empty($row['route_work_center_code'])) {
                $row['routing_line_no'] = $row['line_no'];
            }
        }

        return $row;
    }

    private function normalizeValue(string $field, mixed $value): string
    {
        $value = trim((string) $value);
        if (in_array($field, ['wo_date', 'active_date', 'inactive_date'], true)) {
            return $this->date($value);
        }

        return $value;
    }

    private function csv(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($content, "\xEF\xBB\xBF"));
        $rows = [];
        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $delimiter = substr_count($line, "\t") >= substr_count($line, ',') ? "\t" : ',';
            $rows[] = str_getcsv((string) $line, $delimiter);
        }

        return $rows;
    }

    private function uploadError($file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return 'Please upload a valid Excel file.';
        }
        if ($file->getSize() < 1) {
            return 'Uploaded file is empty.';
        }
        if ($file->getSize() > self::MAX) {
            return 'File is too large. Maximum 10 MB.';
        }

        return in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'tsv', 'txt', 'csv'], true)
            ? null
            : 'Gunakan file Excel .xlsx, .xls, .csv, .tsv, atau .txt.';
    }

    private function tx(callable $fn, string $error): array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $result = $fn();
            if ($db->transStatus() === false) {
                throw new RuntimeException($error);
            }
            $db->transCommit();

            return $result;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function groups(array $rows, array $keys): array
    {
        $groups = [];
        foreach ($rows as $excelRow => $row) {
            $key = $this->g($keys, $row);
            $groups[$key]['h'] ??= $row;
            $groups[$key]['r'] ??= $excelRow;
            $groups[$key]['rows'][] = $row;
        }

        return $groups;
    }

    private function g(array $keys, array $row): string
    {
        return implode('|', array_map(fn ($key) => trim((string) ($row[$key] ?? '')), $keys));
    }

    private function company(): int
    {
        $id = (new TenantContext(session()))->activeCompanyId();
        if (! $id) {
            throw new RuntimeException('Active company is required before import.');
        }

        return (int) $id;
    }

    private function findSite(string $code, int $company): ?array
    {
        $db = Database::connect();
        $builder = $db->table('sites')->where('company_id', $company);
        $builder->groupStart();
        $db->fieldExists('code', 'sites') ? $builder->where('code', $code) : $builder->where('id', -1);
        if ($db->fieldExists('site_code', 'sites')) {
            $builder->orWhere('site_code', $code);
        }
        $builder->groupEnd();

        return $builder->get()->getRowArray();
    }

    private function siteId(string $code, int $company, int $row): int
    {
        $site = $this->findSite($code, $company);
        if (! $site) {
            throw new RuntimeException('Row ' . $row . ': site_code ' . $code . ' tidak ditemukan.');
        }

        return (int) $site['id'];
    }

    private function item(string $code): ?array
    {
        if ($code === '') {
            return null;
        }
        $db = Database::connect();
        $builder = $db->table('items');
        $builder->groupStart();
        foreach (['item_code', 'code', 'item_coded'] as $index => $field) {
            if (! $db->fieldExists($field, 'items')) {
                continue;
            }
            $index === 0 ? $builder->where($field, $code) : $builder->orWhere($field, $code);
        }
        $builder->groupEnd();
        if ($db->fieldExists('deleted_at', 'items')) {
            $builder->where('deleted_at', null);
        }

        return $builder->get()->getRowArray();
    }

    private function itemName(?array $item, string $fallback): string
    {
        return $item ? (string) ($item['item_name'] ?? $item['name'] ?? $item['code'] ?? $fallback) : $fallback;
    }

    private function bom(int $company, string $siteCode, string $parentItemCode): ?array
    {
        return (new ProductionBomModel())
            ->where('company_id', $company)
            ->where('site_code', $siteCode)
            ->where('parent_item_code', $parentItemCode)
            ->first();
    }

    private function routing(int $company, string $siteCode, string $itemCode): ?array
    {
        return (new ProductionRoutingModel())
            ->where('company_id', $company)
            ->where('site_code', $siteCode)
            ->where('item_code', $itemCode)
            ->first();
    }

    private function workCenter(int $company, string $workCenterCode): ?array
    {
        return (new ProductionWorkCenterModel())
            ->where('company_id', $company)
            ->where('work_center_code', $workCenterCode)
            ->first();
    }

    private function num(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return (float) $value;
    }

    private function nd(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $this->date($value) : null;
    }

    private function ndt(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? str_replace('T', ' ', $value) : null;
    }

    private function date(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (is_numeric($value)) {
            $serial = (int) floor((float) $value);
            if ($serial > 25569 && $serial < 60000) {
                return gmdate('Y-m-d', ($serial - 25569) * 86400);
            }
        }
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y', 'j M Y', 'd M Y', 'j F Y', 'd F Y'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }
        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : $value;
    }

    private function xlsx(string $name, array $rows, string $sheet)
    {
        $path = (new XlsxSheetWriter())->writeFirstSheet($rows, $sheet);
        $content = file_get_contents($path) ?: '';
        @unlink($path);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($content);
    }

    private function audit(string $module, string $action, string $description): void
    {
        (new AuditLogService())->log($module, $action, [
            'company_id' => (new TenantContext(session()))->activeCompanyId(),
            'site_id' => (new TenantContext(session()))->activeSiteId(),
            'user_id' => auth()->id(),
            'description' => $description,
        ]);
    }
}
