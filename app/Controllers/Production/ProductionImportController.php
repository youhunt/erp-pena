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
use RuntimeException;

class ProductionImportController extends BaseController
{
    private const MAX_UPLOAD_BYTES = 10485760;

    private array $configs = [
        'boms' => [
            'title' => 'BOM',
            'return_to' => 'production/boms',
            'headers' => ['site_code', 'department_code', 'warehouse_code', 'parent_item_code', 'bom_type', 'qty_batch', 'uom_code', 'ratio_percent', 'description', 'active_date', 'inactive_date', 'line_no', 'child_item_code', 'component_type', 'qty_used', 'line_uom_code', 'factor', 'line_description'],
            'sample' => ['HO', 'PROD', 'MAIN', 'FG-001', 'standard', '1', 'PCS', '100', 'Example BOM', '', '', '10', 'RM-001', 'material', '2', 'PCS', '1', 'Material line'],
        ],
        'work-centers' => [
            'title' => 'Work Center',
            'return_to' => 'production/work-centers',
            'headers' => ['site_code', 'department_code', 'warehouse_code', 'work_center_code', 'description', 'machine_code', 'notes', 'speed', 'capacity_percent', 'max_length', 'length_uom', 'max_width', 'width_uom', 'max_height', 'height_uom', 'max_volume', 'volume_uom', 'qty_labor', 'working_hour', 'cost_type', 'cost_amount', 'cost_uom', 'active_date', 'inactive_date'],
            'sample' => ['HO', 'PROD', 'MAIN', 'WC-001', 'Cutting Machine', 'MC-001', '', '100', '100', '0', '', '0', '', '0', '', '0', '', '1', '8', 'LABOR', '0', 'Hour', '', ''],
        ],
        'routings' => [
            'title' => 'Routing',
            'return_to' => 'production/routings',
            'headers' => ['site_code', 'department_code', 'warehouse_code', 'item_code', 'description', 'line_no', 'routing_name', 'work_center_code', 'operation_type', 'hour_qty', 'hour_uom', 'std_speed', 'speed_uom', 'notes'],
            'sample' => ['HO', 'PROD', 'MAIN', 'FG-001', 'Routing FG-001', '10', 'Cutting', 'WC-001', 'process', '1', 'Hour', '100', 'Unit/Hour', ''],
        ],
        'work-orders' => [
            'title' => 'Work Order',
            'return_to' => 'production/work-orders',
            'headers' => ['site_code', 'department_code', 'warehouse_code', 'work_center_code', 'wo_no', 'wo_date', 'parent_item_code', 'parent_item_name', 'wo_qty', 'uom_code', 'description', 'line_no', 'component_item_code', 'component_item_name', 'qty_used', 'line_uom_code', 'route_work_center_code', 'routing_name', 'hour_qty', 'route_uom'],
            'sample' => ['HO', 'PROD', 'MAIN', 'WC-001', 'WO-001', date('Y-m-d'), 'FG-001', 'Finished Good', '10', 'PCS', 'Example WO', '10', 'RM-001', 'Raw Material', '20', 'PCS', 'WC-001', 'Cutting', '1', 'Hour'],
        ],
    ];

    public function form(string $resource): string
    {
        $config = $this->config($resource);
        return view('production/imports/form', [
            'title' => 'Import ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
        ]);
    }

    public function template(string $resource)
    {
        $config = $this->config($resource);
        $rows = [$config['headers'], $config['sample']];
        return $this->xlsxResponse('production-' . $resource . '-template.xlsx', $rows, $config['title'] . ' Import');
    }

    public function import(string $resource)
    {
        $config = $this->config($resource);
        $file = $this->request->getFile('excel_file');
        $uploadError = $this->validateUpload($file);
        if ($uploadError !== null) {
            return redirect()->back()->with('error', $uploadError);
        }

        try {
            $rows = $this->uploadedRows($file->getTempName(), $config['headers']);
            $result = match ($resource) {
                'boms' => $this->importBoms($rows),
                'work-centers' => $this->importWorkCenters($rows),
                'routings' => $this->importRoutings($rows),
                'work-orders' => $this->importWorkOrders($rows),
                default => throw PageNotFoundException::forPageNotFound(),
            };
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(site_url($config['return_to']))->with('message', sprintf(
            '%s import completed. %d created, %d updated, %d lines processed.',
            $config['title'],
            $result['created'],
            $result['updated'],
            $result['lines']
        ));
    }

    private function importBoms(array $rows): array
    {
        $tenant = new TenantContext(session());
        $companyId = $this->activeCompanyId($tenant);
        $groups = [];
        foreach ($rows as $rowNumber => $row) {
            foreach (['site_code', 'department_code', 'parent_item_code', 'line_no', 'child_item_code'] as $field) {
                $this->requireValue($row, $field, $rowNumber);
            }
            $key = implode('|', [$row['site_code'], $row['department_code'], $row['warehouse_code'] ?? '', $row['parent_item_code']]);
            $groups[$key]['header'] = $row;
            $groups[$key]['lines'][] = $row + ['_row' => $rowNumber];
        }

        $db = Database::connect();
        $db->transBegin();
        $created = $updated = $lineCount = 0;
        try {
            foreach ($groups as $group) {
                $header = $group['header'];
                $siteId = $this->siteId($header['site_code'], $companyId, (int) ($group['lines'][0]['_row'] ?? 0));
                $parentItem = $this->itemByCode($header['parent_item_code']);
                $model = new ProductionBomModel();
                $existing = $model->where([
                    'company_id' => $companyId,
                    'site_code' => $header['site_code'],
                    'department_code' => $header['department_code'],
                    'warehouse_code' => $header['warehouse_code'] ?? '',
                    'parent_item_code' => $header['parent_item_code'],
                ])->first();

                $payload = [
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                    'site_code' => $header['site_code'],
                    'department_code' => $header['department_code'],
                    'warehouse_code' => $header['warehouse_code'] ?? '',
                    'parent_item_id' => $parentItem['id'] ?? null,
                    'parent_item_code' => $header['parent_item_code'],
                    'parent_item_name' => $this->itemName($parentItem, $header['parent_item_code']),
                    'bom_type' => $header['bom_type'] ?? 'standard',
                    'qty_batch' => $this->decimal($header['qty_batch'] ?? 1),
                    'uom_code' => $header['uom_code'] ?? 'PCS',
                    'ratio_percent' => $this->decimal($header['ratio_percent'] ?? 100),
                    'description' => $header['description'] ?? '',
                    'active_date' => $this->nullableDateTime($header['active_date'] ?? null),
                    'inactive_date' => $this->nullableDateTime($header['inactive_date'] ?? null),
                    'is_active' => 1,
                    'updated_by' => auth()->id(),
                ];

                if ($existing !== null) {
                    $bomId = (int) $existing['id'];
                    $model->update($bomId, $payload);
                    (new ProductionBomLineModel())->where('production_bom_id', $bomId)->delete();
                    $updated++;
                } else {
                    $payload['created_by'] = auth()->id();
                    $bomId = (int) $model->insert($payload, true);
                    $created++;
                }

                $seen = [];
                $lineModel = new ProductionBomLineModel();
                foreach ($group['lines'] as $line) {
                    $lineNo = (int) $line['line_no'];
                    if (isset($seen[$lineNo])) {
                        throw new RuntimeException('Duplicate BOM line_no ' . $lineNo . ' for parent item ' . $header['parent_item_code'] . '.');
                    }
                    $seen[$lineNo] = true;
                    $childItem = $this->itemByCode($line['child_item_code']);
                    $lineModel->insert([
                        'production_bom_id' => $bomId,
                        'child_no' => $lineNo,
                        'child_item_id' => $childItem['id'] ?? null,
                        'child_item_code' => $line['child_item_code'],
                        'child_item_name' => $this->itemName($childItem, $line['child_item_code']),
                        'component_type' => $line['component_type'] ?? 'material',
                        'qty_used' => $this->decimal($line['qty_used'] ?? 0),
                        'uom_code' => $line['line_uom_code'] ?? $header['uom_code'] ?? 'PCS',
                        'factor' => $this->decimal($line['factor'] ?? 1),
                        'description' => $line['line_description'] ?? '',
                    ]);
                    $lineCount++;
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('BOM import failed. No data was saved.');
        }
        $db->transCommit();
        $this->audit('production.bom', 'bom.import', 'BOM import completed.');
        return ['created' => $created, 'updated' => $updated, 'lines' => $lineCount];
    }

    private function importWorkCenters(array $rows): array
    {
        $tenant = new TenantContext(session());
        $companyId = $this->activeCompanyId($tenant);
        $db = Database::connect();
        $db->transBegin();
        $created = $updated = $lineCount = 0;
        try {
            $model = new ProductionWorkCenterModel();
            foreach ($rows as $rowNumber => $row) {
                foreach (['site_code', 'department_code', 'warehouse_code', 'work_center_code'] as $field) {
                    $this->requireValue($row, $field, $rowNumber);
                }
                $siteId = $this->siteId($row['site_code'], $companyId, $rowNumber);
                $where = [
                    'company_id' => $companyId,
                    'site_code' => $row['site_code'],
                    'department_code' => $row['department_code'],
                    'warehouse_code' => $row['warehouse_code'],
                    'work_center_code' => $row['work_center_code'],
                ];
                $payload = $where + [
                    'site_id' => $siteId,
                    'description' => $row['description'] ?? '',
                    'machine_code' => $row['machine_code'] ?? '',
                    'notes' => $row['notes'] ?? '',
                    'speed' => $this->decimal($row['speed'] ?? 0),
                    'capacity_percent' => $this->decimal($row['capacity_percent'] ?? 100),
                    'max_length' => $this->decimal($row['max_length'] ?? 0),
                    'length_uom' => $row['length_uom'] ?? '',
                    'max_width' => $this->decimal($row['max_width'] ?? 0),
                    'width_uom' => $row['width_uom'] ?? '',
                    'max_height' => $this->decimal($row['max_height'] ?? 0),
                    'height_uom' => $row['height_uom'] ?? '',
                    'max_volume' => $this->decimal($row['max_volume'] ?? 0),
                    'volume_uom' => $row['volume_uom'] ?? '',
                    'qty_labor' => $this->decimal($row['qty_labor'] ?? 0),
                    'working_hour' => $this->decimal($row['working_hour'] ?? 0),
                    'cost_type' => $row['cost_type'] ?? '',
                    'cost_amount' => $this->decimal($row['cost_amount'] ?? 0),
                    'cost_uom' => $row['cost_uom'] ?? '',
                    'active_date' => $this->nullableDate($row['active_date'] ?? null),
                    'inactive_date' => $this->nullableDate($row['inactive_date'] ?? null),
                    'is_active' => 1,
                    'updated_by' => auth()->id(),
                ];

                $existing = $model->where($where)->first();
                if ($existing !== null) {
                    $model->update((int) $existing['id'], $payload);
                    $updated++;
                } else {
                    $payload['created_by'] = auth()->id();
                    $model->insert($payload);
                    $created++;
                }
                $lineCount++;
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('Work Center import failed. No data was saved.');
        }
        $db->transCommit();
        $this->audit('production.work_center', 'work_center.import', 'Work center import completed.');
        return ['created' => $created, 'updated' => $updated, 'lines' => $lineCount];
    }

    private function importRoutings(array $rows): array
    {
        $tenant = new TenantContext(session());
        $companyId = $this->activeCompanyId($tenant);
        $groups = [];
        foreach ($rows as $rowNumber => $row) {
            foreach (['site_code', 'department_code', 'item_code', 'line_no', 'work_center_code'] as $field) {
                $this->requireValue($row, $field, $rowNumber);
            }
            $key = implode('|', [$row['site_code'], $row['item_code']]);
            $groups[$key]['header'] = $row;
            $groups[$key]['lines'][] = $row + ['_row' => $rowNumber];
        }

        $db = Database::connect();
        $db->transBegin();
        $created = $updated = $lineCount = 0;
        try {
            foreach ($groups as $group) {
                $header = $group['header'];
                $siteId = $this->siteId($header['site_code'], $companyId, (int) ($group['lines'][0]['_row'] ?? 0));
                $item = $this->itemByCode($header['item_code']);
                $model = new ProductionRoutingModel();
                $where = ['company_id' => $companyId, 'site_code' => $header['site_code'], 'item_code' => $header['item_code']];
                $existing = $model->where($where)->first();
                $payload = $where + [
                    'site_id' => $siteId,
                    'department_code' => $header['department_code'],
                    'warehouse_code' => $header['warehouse_code'] ?? '',
                    'item_id' => $item['id'] ?? null,
                    'description' => $header['description'] ?? '',
                    'is_active' => 1,
                    'updated_by' => auth()->id(),
                ];
                if ($existing !== null) {
                    $routingId = (int) $existing['id'];
                    $model->update($routingId, $payload);
                    (new ProductionRoutingLineModel())->where('production_routing_id', $routingId)->delete();
                    $updated++;
                } else {
                    $payload['created_by'] = auth()->id();
                    $routingId = (int) $model->insert($payload, true);
                    $created++;
                }

                $seen = [];
                $lineModel = new ProductionRoutingLineModel();
                foreach ($group['lines'] as $line) {
                    $lineNo = (int) $line['line_no'];
                    if (isset($seen[$lineNo])) {
                        throw new RuntimeException('Duplicate Routing line_no ' . $lineNo . ' for item ' . $header['item_code'] . '.');
                    }
                    $seen[$lineNo] = true;
                    $lineModel->insert([
                        'production_routing_id' => $routingId,
                        'route_no' => (string) $lineNo,
                        'routing_name' => $line['routing_name'] ?? '',
                        'work_center_code' => $line['work_center_code'],
                        'operation_type' => $line['operation_type'] ?? 'process',
                        'hour_qty' => $this->decimal($line['hour_qty'] ?? 0),
                        'hour_uom' => $line['hour_uom'] ?? 'Hour',
                        'std_speed' => $this->decimal($line['std_speed'] ?? 0),
                        'speed_uom' => $line['speed_uom'] ?? 'Unit/Hour',
                        'notes' => $line['notes'] ?? '',
                    ]);
                    $lineCount++;
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('Routing import failed. No data was saved.');
        }
        $db->transCommit();
        $this->audit('production.routing', 'routing.import', 'Routing import completed.');
        return ['created' => $created, 'updated' => $updated, 'lines' => $lineCount];
    }

    private function importWorkOrders(array $rows): array
    {
        $tenant = new TenantContext(session());
        $companyId = $this->activeCompanyId($tenant);
        $groups = [];
        foreach ($rows as $rowNumber => $row) {
            foreach (['site_code', 'department_code', 'wo_no', 'wo_date', 'parent_item_code', 'wo_qty', 'line_no'] as $field) {
                $this->requireValue($row, $field, $rowNumber);
            }
            $groups[$row['wo_no']]['header'] = $row;
            $groups[$row['wo_no']]['lines'][] = $row + ['_row' => $rowNumber];
        }

        $db = Database::connect();
        $db->transBegin();
        $created = $updated = $lineCount = 0;
        try {
            foreach ($groups as $woNo => $group) {
                $header = $group['header'];
                $siteId = $this->siteId($header['site_code'], $companyId, (int) ($group['lines'][0]['_row'] ?? 0));
                $parentItem = $this->itemByCode($header['parent_item_code']);
                $model = new ProductionWorkOrderModel();
                $existing = $model->where(['company_id' => $companyId, 'wo_no' => $woNo])->first();
                $payload = [
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                    'company' => session('active_company_code') ?: null,
                    'site' => $header['site_code'],
                    'wo_code' => 'WO',
                    'wo_no' => $woNo,
                    'wo_date' => $header['wo_date'],
                    'site_code' => $header['site_code'],
                    'department_code' => $header['department_code'],
                    'warehouse_code' => $header['warehouse_code'] ?? '',
                    'work_center_code' => $header['work_center_code'] ?? '',
                    'parent_item_id' => $parentItem['id'] ?? null,
                    'parent_item_code' => $header['parent_item_code'],
                    'parent_item_name' => $header['parent_item_name'] ?? $this->itemName($parentItem, $header['parent_item_code']),
                    'wo_qty' => $this->decimal($header['wo_qty']),
                    'std_qty_finished' => $this->decimal($header['wo_qty']),
                    'act_qty_finished' => 0,
                    'uom_code' => $header['uom_code'] ?? 'PCS',
                    'description' => $header['description'] ?? '',
                    'status' => 'draft',
                    'updated_by' => auth()->id(),
                ];
                if ($existing !== null) {
                    if (($existing['status'] ?? 'draft') !== 'draft') {
                        throw new RuntimeException('Work Order ' . $woNo . ' cannot be updated because status is ' . ($existing['status'] ?? '-') . '.');
                    }
                    $woId = (int) $existing['id'];
                    $model->update($woId, $payload);
                    (new ProductionWorkOrderComponentModel())->where('production_work_order_id', $woId)->delete();
                    (new ProductionWorkOrderRoutingModel())->where('production_work_order_id', $woId)->delete();
                    $updated++;
                } else {
                    $payload['created_by'] = auth()->id();
                    $woId = (int) $model->insert($payload, true);
                    $created++;
                }

                $seen = [];
                $componentModel = new ProductionWorkOrderComponentModel();
                $routingModel = new ProductionWorkOrderRoutingModel();
                foreach ($group['lines'] as $line) {
                    $lineNo = (int) $line['line_no'];
                    if (isset($seen[$lineNo])) {
                        throw new RuntimeException('Duplicate Work Order line_no ' . $lineNo . ' for WO ' . $woNo . '.');
                    }
                    $seen[$lineNo] = true;

                    if (! empty($line['component_item_code'])) {
                        $item = $this->itemByCode($line['component_item_code']);
                        $componentModel->insert([
                            'production_work_order_id' => $woId,
                            'line_no' => $lineNo,
                            'component_item_id' => $item['id'] ?? null,
                            'component_item_code' => $line['component_item_code'],
                            'component_item_name' => $line['component_item_name'] ?? $this->itemName($item, $line['component_item_code']),
                            'qty_used' => $this->decimal($line['qty_used'] ?? 0),
                            'uom_code' => $line['line_uom_code'] ?? $header['uom_code'] ?? 'PCS',
                            'warehouse_code' => $line['warehouse_code'] ?? $header['warehouse_code'] ?? '',
                            'location_code' => '',
                            'batch_no' => '',
                            'booking_qty' => $this->decimal($line['qty_used'] ?? 0),
                            'allocated_qty' => 0,
                            'issued_qty' => 0,
                            'line_status' => 'open',
                        ]);
                    }

                    $routeWorkCenter = $line['route_work_center_code'] ?? '';
                    if ($routeWorkCenter !== '') {
                        $routingModel->insert([
                            'production_work_order_id' => $woId,
                            'line_no' => $lineNo,
                            'routing_name' => $line['routing_name'] ?? '',
                            'work_center_code' => $routeWorkCenter,
                            'work_center_name' => $routeWorkCenter,
                            'hour_qty' => $this->decimal($line['hour_qty'] ?? 0),
                            'uom_code' => $line['route_uom'] ?? 'Hour',
                        ]);
                    }
                    $lineCount++;
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('Work Order import failed. No data was saved.');
        }
        $db->transCommit();
        $this->audit('production.wo', 'wo.import', 'Work order import completed.');
        return ['created' => $created, 'updated' => $updated, 'lines' => $lineCount];
    }

    private function config(string $resource): array
    {
        if (! isset($this->configs[$resource])) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->configs[$resource];
    }

    private function uploadedRows(string $path, array $allowedHeaders): array
    {
        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            throw new RuntimeException('Uploaded file is empty.');
        }
        $rawRows = str_starts_with($content, 'PK') ? (new XlsxSheetReader())->readFirstSheet($path) : $this->readDelimitedRows($content);
        if ($rawRows === [] || ! isset($rawRows[0])) {
            throw new RuntimeException('Uploaded file has no rows.');
        }
        $headers = array_map(fn ($value): string => strtolower(trim((string) $value)), $rawRows[0]);
        $rows = [];
        foreach (array_slice($rawRows, 1) as $index => $raw) {
            $rowNumber = $index + 2;
            $row = [];
            foreach ($headers as $col => $header) {
                if ($header === '' || ! in_array($header, $allowedHeaders, true)) {
                    continue;
                }
                $value = trim((string) ($raw[$col] ?? ''));
                $row[$header] = $value;
            }
            if (array_filter($row, static fn ($value): bool => trim((string) $value) !== '') === []) {
                continue;
            }
            $rows[$rowNumber] = $row;
        }
        if ($rows === []) {
            throw new RuntimeException('Uploaded file has no data rows.');
        }
        return $rows;
    }

    private function readDelimitedRows(string $content): array
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

    private function validateUpload($file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return 'Please upload a valid Excel file.';
        }
        if ($file->getSize() < 1) {
            return 'Uploaded file is empty.';
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            return 'File is too large. Maximum 10 MB.';
        }
        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'tsv', 'txt', 'csv'], true)) {
            return 'Gunakan file Excel .xlsx, .xls, .csv, .tsv, atau .txt.';
        }
        return null;
    }

    private function requireValue(array $row, string $field, int $rowNumber): void
    {
        if (! isset($row[$field]) || trim((string) $row[$field]) === '') {
            throw new RuntimeException('Row ' . $rowNumber . ': ' . $field . ' is required.');
        }
    }

    private function activeCompanyId(TenantContext $tenant): int
    {
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('Active company is required before import.');
        }
        return (int) $companyId;
    }

    private function siteId(string $siteCode, int $companyId, int $rowNumber): int
    {
        $site = Database::connect()->table('sites')
            ->where('company_id', $companyId)
            ->where('code', $siteCode)
            ->get()
            ->getRowArray();
        if ($site === null) {
            throw new RuntimeException('Row ' . $rowNumber . ': site_code ' . $siteCode . ' not found for active company.');
        }
        return (int) $site['id'];
    }

    private function itemByCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }
        $db = Database::connect();
        $builder = $db->table('items');
        $db->fieldExists('item_code', 'items') ? $builder->where('item_code', $code) : $builder->where('code', $code);
        return $builder->get()->getRowArray();
    }

    private function itemName(?array $item, string $fallback): string
    {
        if ($item === null) {
            return $fallback;
        }
        return (string) ($item['item_name'] ?? $item['name'] ?? $item['code'] ?? $fallback);
    }

    private function decimal(mixed $value): float
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

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function nullableDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? str_replace('T', ' ', $value) : null;
    }

    private function xlsxResponse(string $filename, array $rows, string $sheetName)
    {
        $path = (new XlsxSheetWriter())->writeFirstSheet($rows, $sheetName);
        $content = file_get_contents($path) ?: '';
        @unlink($path);
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
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
