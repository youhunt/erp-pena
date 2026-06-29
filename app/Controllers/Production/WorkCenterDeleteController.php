<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Models\ProductionWorkCenterModel;
use App\Models\WorkCenterCostModel;
use App\Models\WorkCenterMachineModel;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;
use Throwable;

class WorkCenterDeleteController extends BaseController
{
    public function delete(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new ProductionWorkCenterModel();

        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        $workCenter = $model->find($id);
        if ($workCenter === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $this->assertNotUsed($workCenter);
            $this->deleteWorkCenter($id, $workCenter);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/work-centers')->with('message', 'Work Center deleted.');
    }

    private function deleteWorkCenter(int $id, array $workCenter): void
    {
        $db = Database::connect();
        $db->transBegin();

        try {
            $userId = auth()->id();

            $machineModel = new WorkCenterMachineModel();
            $costModel = new WorkCenterCostModel();
            $workCenterModel = new ProductionWorkCenterModel();

            $machineModel->where('work_center_id', $id)->set(['deleted_by' => $userId])->update();
            $machineModel->where('work_center_id', $id)->delete();

            $costModel->where('work_center_id', $id)->set(['deleted_by' => $userId])->update();
            $costModel->where('work_center_id', $id)->delete();

            $workCenterModel->update($id, [
                'is_active' => 0,
                'updated_by' => $userId,
            ]);
            $workCenterModel->delete($id);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to delete Work Center.');
            }

            $db->transCommit();

            (new AuditLogService())->log('production.work_center', 'work_center.delete', [
                'company_id' => $workCenter['company_id'] ?? null,
                'site_id' => $workCenter['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'production_work_centers',
                'record_id' => $id,
                'record_code' => (string) ($workCenter['work_center_code'] ?? $id),
                'description' => 'Work Center deleted with machine and cost details.',
            ]);
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function assertNotUsed(array $workCenter): void
    {
        $db = Database::connect();
        $code = trim((string) ($workCenter['work_center_code'] ?? ''));
        $companyId = (int) ($workCenter['company_id'] ?? 0);
        $siteId = (int) ($workCenter['site_id'] ?? 0);

        if ($code === '') {
            throw new RuntimeException('Work Center code is empty.');
        }

        $checks = [
            ['table' => 'production_routing_lines', 'column' => 'work_center_code', 'label' => 'Routing'],
            ['table' => 'production_work_orders', 'column' => 'work_center_code', 'label' => 'Work Order'],
            ['table' => 'production_work_order_routings', 'column' => 'work_center_code', 'label' => 'Work Order Routing'],
        ];

        foreach ($checks as $check) {
            $table = $check['table'];
            $column = $check['column'];

            if (! $db->tableExists($table) || ! $db->fieldExists($column, $table)) {
                continue;
            }

            $builder = $db->table($table)->where($column, $code);

            if ($db->fieldExists('company_id', $table) && $companyId > 0) {
                $builder->where('company_id', $companyId);
            }
            if ($db->fieldExists('site_id', $table) && $siteId > 0) {
                $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
            }
            if ($db->fieldExists('deleted_at', $table)) {
                $builder->where('deleted_at', null);
            }

            if ($builder->countAllResults() > 0) {
                throw new RuntimeException('Work Center tidak bisa dihapus karena sudah dipakai di ' . $check['label'] . '. Nonaktifkan saja atau hapus dokumen terkait terlebih dahulu.');
            }
        }
    }
}
