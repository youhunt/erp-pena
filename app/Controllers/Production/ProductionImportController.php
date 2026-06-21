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
    private const SESSION_PREFIX = 'production_import_preview_';

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

        return $this->xlsxResponse(
            'production-' . $resource . '-template.xlsx',
            [$config['headers'], $config['sample']],
            $config['title'] . ' Import'
        );
    }

    /**
     * Standard import flow:
     * upload -> preview/validate -> commit.
     *
     * The same POST endpoint is used for preview and commit so existing route remains simple:
     * POST /production/imports/{resource}
     */
    public function import(string $resource)
    {
        $config = $this->config($resource);
        $commitToken = trim((string) $this->request->getPost('commit_token'));

        if ($commitToken !== '') {
            return $this->commit($resource, $commitToken);
        }

        $file = $this->request->getFile('excel_file');
        $uploadError = $this->validateUpload($file);
        if ($uploadError !== null) {
            return redirect()->back()->with('error', $uploadError);
        }

        try {
            $rows = $this->uploadedRows($file->getTempName(), $config['headers']);
            $preview = $this->previewRows($resource, $rows);
            $token = null;
            if (! $preview['has_errors']) {
                $token = bin2hex(random_bytes(16));
                session()->set(self::SESSION_PREFIX . $token, [
                    'resource' => $resource,
                    'rows' => $rows,
                    'created_at' => time(),
                ]);
            }
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
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
        $config = $this->config($resource);
        $sessionKey = self::SESSION_PREFIX . $token;
        $payload = session($sessionKey);

        if (! is_array($payload) || ($payload['resource'] ?? '') !== $resource || empty($payload['rows']) || ! is_array($payload['rows'])) {
            return redirect()->to(site_url('production/imports/' . $resource))->with('error', 'Preview session sudah habis atau tidak valid. Upload ulang file import.');
        }

        try {
            $preview = $this->previewRows($resource, $payload['rows']);
            if ($preview['has_errors']) {
                return view('production/imports/preview', [
                    'title' => 'Preview Import ' . $config['title'],
                    'resource' => $resource,
                    'config' => $config,
                    'preview' => $preview,
                    'token' => null,
                ]);
            }

            $result = match ($resource) {
                'boms' => $this->importBoms($payload['rows']),
                'work-centers' => $this->importWorkCenters($payload['rows']),
                'routings' => $this->importRoutings($payload['rows']),
                'work-orders' => $this->importWorkOrders($payload['rows']),
                default => throw PageNotFoundException::forPageNotFound(),
            };
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('production/imports/' . $resource))->with('error', $exception->getMessage());
        }

        session()->remove($sessionKey);

        return redirect()->to(site_url($config['return_to']))->with('message', sprintf(
            '%s import committed. %d created, %d updated, %d lines processed.',
            $config['title'],
            $result['created'],
            $result['updated'],
            $result['lines']
        ));
    }

    private function configs(): array
    {
        return [
            'boms' => [
                'title' => 'BOM',
                'return_to' => 'production/boms',
                'headers' => ['site_code', 'department_code', 'warehouse_code', 'parent_item_code', 'bom_type', 'qty_batch', 'uom_code', 'ratio_percent', 'description', 'active_date', 'inactive_date', 'line_no', 'child_item_code', 'component_type', 'qty_used', 'line_uom_code', 'factor', 'line_description'],
                'sample' => ['HO', 'PROD', 'MAIN', 'FG-001', 'standard', '1', 'PCS', '100', 'Example BOM', '', '', '10', 'RM-001', 'material', '2', 'PCS', '1', 'Material line'],
                'required' => ['site_code', 'department_code', 'parent_item_code', 'line_no', 'child_item_code'],
                'group_key' => ['site_code', 'department_code', 'warehouse_code', 'parent_item_code'],
            ],
            'work-centers' => [
                'title' => 'Work Center',
                'return_to' => 'production/work-centers',
                'headers' => ['site_code', 'department_code', 'warehouse_code', 'work_center_code', 'description', 'machine_code', 'notes', 'speed', 'capacity_percent', 'max_length', 'length_uom', 'max_width', 'width_uom', 'max_height', 'height_uom', 'max_volume', 'volume_uom', 'qty_labor', 'working_hour', 'cost_type', 'cost_amount', 'cost_uom', 'active_date', 'inactive_date'],
                'sample' => ['HO', 'PROD', 'MAIN', 'WC-001', 'Cutting Machine', 'MC-001', '', '100', '100', '0', '', '0', '', '0', '', '0', '', '1', '8', 'LABOR', '0', 'Hour', '', ''],
                'required' => ['site_code', 'department_code', 'warehouse_code', 'work_center_code'],
            ],
            'routings' => [
                'title' => 'Routing',
                'return_to' => 'production/routings',
                'headers' => ['site_code', 'department_code', 'warehouse_code', 'item_code', 'description', 'line_no', 'routing_name', 'work_center_code', 'operation_type', 'hour_qty', 'hour_uom', 'std_speed', 'speed_uom', 'notes'],
                'sample' => ['HO', 'PROD', 'MAIN', 'FG-001', 'Routing FG-001', '10', 'Cutting', 'WC-001', 'process', '1', 'Hour', '100', 'Unit/Hour', ''],
                'required' => ['site_code', 'department_code', 'item_code', 'line_no', 'work_center_code'],
                'group_key' => ['site_code', 'item_code'],
            ],
            'work-orders' => [
                'title' => 'Work Order',
                'return_to' => 'production/work-orders',
                'headers' => ['site_code', 'department_code', 'warehouse_code', 'work_center_code', 'wo_no', 'wo_date', 'parent_item_code', 'parent_item_name', 'wo_qty', 'uom_code', 'description', 'line_no', 'component_item_code', 'component_item_name', 'qty_used', 'line_uom_code', 'route_work_center_code', 'routing_name', 'hour_qty', 'route_uom'],
                'sample' => ['HO', 'PROD', 'MAIN', 'WC-001', 'WO-001', date('Y-m-d'), 'FG-001', 'Finished Good', '10', 'PCS', 'Example WO', '10', 'RM-001', 'Raw Material', '20', 'PCS', 'WC-001', 'Cutting', '1', 'Hour'],
                'required' => ['site_code', 'department_code', 'wo_no', 'wo_date', 'parent_item_code', 'wo_qty', 'line_no'],
                'group_key' => ['wo_no'],
            ],
        ];
    }

    private function config(string $resource): array
    {
        $configs = $this->configs();
        if (! isset($configs[$resource])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $configs[$resource];
    }

    private function previewRows(string $resource, array $rows): array
    {
        $config = $this->config($resource);
        $tenant = new TenantContext(session());
        $companyId = $this->activeCompanyId($tenant);
        $previewRows = [];
        $duplicateTracker = [];
        $hasErrors = false;
        $validCount = 0;

        foreach ($rows as $rowNumber => $row) {
            $errors = [];
            $warnings = [];

            foreach ($config['required'] as $field) {
                if (! isset($row[$field]) || trim((string) $row[$field]) === '') {
                    $errors[] = $field . ' wajib diisi';
                }
            }

            $siteCode = trim((string) ($row['site_code'] ?? ''));
            if ($siteCode !== '' && $this->findSite($siteCode, $companyId) === null) {
                $errors[] = 'site_code ' . $siteCode . ' tidak ditemukan untuk active company';
            }

            if (in_array($resource, ['boms', 'routings', 'work-orders'], true)) {
                $lineNo = (int) ($row['line_no'] ?? 0);
                if ($lineNo < 1) {
                    $errors[] = 'line_no harus angka lebih besar dari 0';
                }

                $groupKey = $this->groupKey($config['group_key'] ?? [], $row);
                $duplicateKey = $groupKey . '|line:' . $lineNo;
                if ($lineNo > 0) {
                    if (isset($duplicateTracker[$duplicateKey])) {
                        $errors[] = 'duplicate line_no ' . $lineNo . ' dalam group yang sama';
                    }
                    $duplicateTracker[$duplicateKey] = true;
                }
            }

            if ($resource === 'work-orders' && ! empty($row['wo_no'])) {
                $existing = (new ProductionWorkOrderModel())
                    ->where('company_id', $companyId)
                    ->where('wo_no', (string) $row['wo_no'])
                    ->first();
                if ($existing !== null && ($existing['status'] ?? 'draft') !== 'draft') {
                    $errors[] = 'WO existing status ' . ($existing['status'] ?? '-') . ', hanya draft yang boleh di-update';
                }
            }

            if (! empty($row['parent_item_code']) && $this->itemByCode((string) $row['parent_item_code']) === null) {
                $warnings[] = 'parent_item_code belum ditemukan di master item, nama fallback akan dipakai';
            }
            if (! empty($row['child_item_code']) && $this->itemByCode((string) $row['child_item_code']) === null) {
                $warnings[] = 'child_item_code belum ditemukan di master item, nama fallback akan dipakai';
            }
            if (! empty($row['component_item_code']) && $this->itemByCode((string) $row['component_item_code']) === null) {
                $warnings[] = 'component_item_code belum ditemukan di master item, nama fallback akan dipakai';
            }

            if ($errors === []) {
                $validCount++;
            } else {
                $hasErrors = true;
            }

            $previewRows[] = [
                'row_number' => $rowNumber,
                'status' => $errors === [] ? 'valid' : 'error',
                'errors' => $errors,
                'warnings' => $warnings,
                'data' => $row,
            ];
        }

        return [
            'total' => count($previewRows),
            'valid' => $validCount,
            'error' => count($previewRows) - $validCount,
            'has_errors' => $hasErrors,
            'rows' => $previewRows,
        ];
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
            $groups[$this->groupKey(['site_code', 'department_code', 'warehouse_code', 'parent_item_code'], $row)]['header'] = $row;
            $groups[$this->groupKey(['site_code', 'department_code', 'warehouse_code', 'parent_item_code'], $row)]['lines'][] = $row + ['_row' => $rowNumber];
        }

        $db = Database::connect();
        $db->transBegin();
        $created = $updated = $lineCount = 0;
        try {
            foreach ($groups as $group) {
                $header = $group['header'];
                $siteId = $this->siteId((string) $header['site_code'], $companyId, (int) ($group['lines'][0]['_row'] ?? 0));
                $parentItem = $this->itemByCode((string) $header['parent_item_code']);
                $model = new ProductionBomModel();
                $where = [
                    'company_id' => $companyId,
                    'site_code' => (string) $header['site_code'],
                    'department_code' => (string) $header['department_code'],
                    'warehouse_code' => (string) ($header['warehouse_code'] ?? ''),
                    'parent_item_code' => (string) $header['parent_item_code'],
                ];
                $payload = $where + [
                    'site_id' => $siteId,
                    'parent_item_id' => $parentItem['id'] ?? null,
                    'parent_item_name' => $this->itemName($parentItem, (string) $header['parent_item_code']),
                    'bom_type' => (string) ($header['bom_type'] ?? 'standard'),
                    'qty_batch' => $this->decimal($header['qty_batch'] ?? 1),
                    'uom_code' => (string) ($header['uom_code'] ?? 'PCS'),
                    'ratio_percent' => $this->decimal($header['ratio_percent'] ?? 100),
                    'description' => (string) ($header['description'] ?? ''),
                    'active_date' => $this->nullableDateTime($header['active_date'] ?? null),
                    'inactive_date' => $this->nullableDateTime($header['inactive_date'] ?? null),
                    'is_active' => 1,
                    'updated_by' => auth()->id(),
                ];
                $existing = $model->where($where)->first();
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
                    $childItem = $this->itemByCode((string) $line['child_item_code']);
                    $lineModel->insert([
                        'production_bom_id' => $bomId,
                        'child_no' => $lineNo,
                        'child_item_id' => $childItem['id'] ?? null,
                        'child_item_code' => (string) $line['child_item_code'],
                        'child_item_name' => $this->itemName($childItem, (string) $line['child_item_code']),
                        'component_type' => (string) ($line['component_type'] ?? 'material'),
                        'qty_used' => $this->decimal($line['qty_used'] ?? 0),
                        'uom_code' => (string) ($line['line_uom_code'] ?? $header['uom_code'] ?? 'PCS'),
                        'factor' => $this->decimal($line['factor'] ?? 1),
                        'description' => (string) ($line['line_description'] ?? ''),
                    ]);
                    $lineCount++;
                }
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('BOM import failed. No data was saved.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

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
                $siteId = $this->siteId((string) $row['site_code'], $companyId, $rowNumber);
                $where = [
                    'company_id' => $companyId,
                    'site_code' => (string) $row['site_code'],
                    'department_code' => (string) $row['department_code'],
                    'warehouse_code' => (string) $row['warehouse_code'],
                    'work_center_code' => (string) $row['work_center_code'],
                ];
                $payload = $where + [
                    'site_id' => $siteId,
                    'description' => (string) ($row['description'] ?? ''),
                    'machine_code' => (string) ($row['machine_code'] ?? ''),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'speed' => $this->decimal($row['speed'] ?? 0),
                    'capacity_percent' => $this->decimal($row['capacity_percent'] ?? 100),
                    'max_length' => $this->decimal($row['max_length'] ?? 0),
                    'length_uom' => (string) ($row['length_uom'] ?? ''),
                    'max_width' => $this->decimal($row['max_width'] ?? 0),
                    'width_uom' => (string) ($row['width_uom'] ?? ''),
                    'max_height' => $this->decimal($row['max_height'] ?? 0),
                    'height_uom' => (string) ($row['height_uom'] ?? ''),
                    'max_volume' => $this->decimal($row['max_volume'] ?? 0),
                    'volume_uom' => (string) ($row['volume_uom'] ?? ''),
                    'qty_labor' => $this->decimal($row['qty_labor'] ?? 0),
                    'working_hour' => $this->decimal($row['working_hour'] ?? 0),
                    'cost_type' => (string) ($row['cost_type'] ?? ''),
                    'cost_amount' => $this->decimal($row['cost_amount'] ?? 0),
                    'cost_uom' => (string) ($row['cost_uom'] ?? ''),
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
            if ($db->transStatus() === false) {
                throw new RuntimeException('Work Center import failed. No data was saved.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

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
            $groups[$this->groupKey(['site_code', 'item_code'], $row)]['header'] = $row;
            $groups[$this->groupKey(['site_code', 'item_code'], $row)]['lines'][] = $row + ['_row' => $rowNumber];
        }

        $db = Database::connect();
        $db->transBegin();
        $created = $updated = $lineCount = 0;
        try {
            foreach ($groups as $group) {
                $header = $group['header'];
                $siteId = $this->siteId((string) $header['site_code'], $companyId, (int) ($group['lines'][0]['_row'] ?? 0));
                $item = $this->itemByCode((string) $header['item_code']);
                $model = new ProductionRoutingModel();
                $where = ['company_id' => $companyId, 'site_code' => (string) $header['site_code'], 'item_code' => (string) $header['item_code']];
                $payload = $where + [
                    'site_id' => $siteId,
                    'department_code' => (string) $header['department_code'],
                    'warehouse_code' => (string) ($header['warehouse_code'] ?? ''),
                    'item_id' => $item['id'] ?? null,
                    'description' => (string) ($header['description'] ?? ''),
                    'is_active' => 1,
                    'updated_by' => auth()->id(),
                ];
                $existing = $model->where($where)->first();
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
                        'routing_name' => (string) ($line['routing_name'] ?? ''),
                        'work_center_code' => (string) $line['work_center_code'],
                        'operation_type' => (string) ($line['operation_type'] ?? 'process'),
                        'hour_qty' => $this->decimal($line['hour_qty'] ?? 0),
                        'hour_uom' => (string) ($line['hour_uom'] ?? 'Hour'),
                        'std_speed' => $this->decimal($line['std_speed'] ?? 0),
                        'speed_uom' => (string) ($line['speed_uom'] ?? 'Unit/Hour'),
                        'notes' => (string) ($line['notes'] ?? ''),
                    ]);
                    $lineCount++;
                }
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Routing import failed. No data was saved.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

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
            $groups[(string) $row['wo_no']]['header'] = $row;
            $groups[(string) $row['wo_no']]['lines'][] = $row + ['_row' => $rowNumber];
        }

        $db = Database::connect();
        $db->transBegin();
        $created = $updated = $lineCount = 0;
        try {
            foreach ($groups as $woNo => $group) {
                $header = $group['header'];
                $siteId = $this->siteId((string) $header['site_code'], $companyId, (int) ($group['lines'][0]['_row'] ?? 0));
                $parentItem = $this->itemByCode((string) $header['parent_item_code']);
                $model = new ProductionWorkOrderModel();
                $existing = $model->where(['company_id' => $companyId, 'wo_no' => $woNo])->first();
                $payload = [
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                    'company' => session('active_company_code') ?: null,
                    'site' => (string) $header['site_code'],
                    'wo_code' => 'WO',
                    'wo_no' => (string) $woNo,
                    'wo_date' => (string) $header['wo_date'],
                    'site_code' => (string) $header['site_code'],
                    'department_code' => (string) $header['department_code'],
                    'warehouse_code' => (string) ($header['warehouse_code'] ?? ''),
                    'work_center_code' => (string) ($header['work_center_code'] ?? ''),
                    'parent_item_id' => $parentItem['id'] ?? null,
                    'parent_item_code' => (string) $header['parent_item_code'],
                    'parent_item_name' => (string) ($header['parent_item_name'] ?? $this->itemName($parentItem, (string) $header['parent_item_code'])),
                    'wo_qty' => $this->decimal($header['wo_qty']),
                    'std_qty_finished' => $this->decimal($header['wo_qty']),
                    'act_qty_finished' => 0,
                    'uom_code' => (string) ($header['uom_code'] ?? 'PCS'),
                    'description' => (string) ($header['description'] ?? ''),
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
                        $item = $this->itemByCode((string) $line['component_item_code']);
                        $componentModel->insert([
                            'production_work_order_id' => $woId,
                            'line_no' => $lineNo,
                            'component_item_id' => $item['id'] ?? null,
                            'component_item_code' => (string) $line['component_item_code'],
                            'component_item_name' => (string) ($line['component_item_name'] ?? $this->itemName($item, (string) $line['component_item_code'])),
                            'qty_used' => $this->decimal($line['qty_used'] ?? 0),
                            'uom_code' => (string) ($line['line_uom_code'] ?? $header['uom_code'] ?? 'PCS'),
                            'warehouse_code' => (string) ($line['warehouse_code'] ?? $header['warehouse_code'] ?? ''),
                            'location_code' => '',
                            'batch_no' => '',
                            'booking_qty' => $this->decimal($line['qty_used'] ?? 0),
                            'allocated_qty' => 0,
                            'issued_qty' => 0,
                            'line_status' => 'open',
                        ]);
                    }
                    $routeWorkCenter = (string) ($line['route_work_center_code'] ?? '');
                    if ($routeWorkCenter !== '') {
                        $routingModel->insert([
                            'production_work_order_id' => $woId,
                            'line_no' => $lineNo,
                            'routing_name' => (string) ($line['routing_name'] ?? ''),
                            'work_center_code' => $routeWorkCenter,
                            'work_center_name' => $routeWorkCenter,
                            'hour_qty' => $this->decimal($line['hour_qty'] ?? 0),
                            'uom_code' => (string) ($line['route_uom'] ?? 'Hour'),
                        ]);
                    }
                    $lineCount++;
                }
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Work Order import failed. No data was saved.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $this->audit('production.wo', 'wo.import', 'Work order import completed.');
        return ['created' => $created, 'updated' => $updated, 'lines' => $lineCount];
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
        $headers = array_map(static fn ($value): string => strtolower(trim((string) $value)), $rawRows[0]);
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

    private function groupKey(array $fields, array $row): string
    {
        return implode('|', array_map(static fn (string $field): string => trim((string) ($row[$field] ?? '')), $fields));
    }

    private function activeCompanyId(TenantContext $tenant): int
    {
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('Active company is required before import.');
        }
        return (int) $companyId;
    }

    private function findSite(string $siteCode, int $companyId): ?array
    {
        return Database::connect()->table('sites')
            ->where('company_id', $companyId)
            ->where('code', $siteCode)
            ->get()
            ->getRowArray();
    }

    private function siteId(string $siteCode, int $companyId, int $rowNumber): int
    {
        $site = $this->findSite($siteCode, $companyId);
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
