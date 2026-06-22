<?php

namespace App\Services\Finance;

use App\Models\PeriodCloseModel;
use App\Services\AuditLogService;
use App\Services\Support\PeriodCloseIntegrityGuard;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class PeriodCloseService
{
    /** @return array<string, string> */
    public static function modules(): array
    {
        return [
            'sales' => 'Sales',
            'purchase' => 'Purchase',
            'inventory' => 'Inventory',
            'production' => 'Production',
            'ap' => 'Accounts Payable',
            'ar' => 'Accounts Receivable',
            'cashbank' => 'Cash Bank',
            'gl' => 'General Ledger',
        ];
    }

    public function close(array $data, ?int $userId = null): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $module = strtolower(trim((string) ($data['module_code'] ?? '')));
        $period = trim((string) ($data['period'] ?? ''));
        $siteId = ! empty($data['site_id']) ? (int) $data['site_id'] : null;
        $guard = new PeriodCloseIntegrityGuard();
        $guard->assertCloseContext($companyId, $module, $period, array_keys(self::modules()));
        $siteScopeId = $guard->siteScopeId($siteId);

        $db = Database::connect();
        if (! $db->fieldExists('site_scope_id', 'period_closes')) {
            throw new RuntimeException('Period close site-scope upgrade is required. Run the latest database migration first.');
        }

        [$start, $end] = $this->periodRange($period);
        $model = new PeriodCloseModel();
        $payload = [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'site_scope_id' => $siteScopeId,
            'module_code' => $module,
            'period' => $period,
            'period_start' => $start,
            'period_end' => $end,
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
            'closed_by' => $userId,
            'reopened_at' => null,
            'reopened_by' => null,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $userId,
        ];

        $db->transBegin();
        try {
            $existing = $db->query(
                'SELECT * FROM period_closes '
                . 'WHERE company_id = ? AND site_scope_id = ? AND module_code = ? AND period = ? '
                . 'AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                [$companyId, $siteScopeId, $module, $period]
            )->getRowArray();

            if ($existing !== null) {
                $guard->assertCanClose($existing['status'] ?? null);
                $model->update((int) $existing['id'], $payload);
                $periodCloseId = (int) $existing['id'];
            } else {
                $model->insert($payload + ['created_by' => $userId]);
                $periodCloseId = (int) $model->getInsertID();
                if ($periodCloseId < 1) {
                    throw new RuntimeException('Failed to create period close record.');
                }
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to close period.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }

        $this->audit('period.close', $periodCloseId, $payload, $userId);

        return $periodCloseId;
    }

    public function reopen(int $id, int $companyId, ?int $siteId = null, ?int $userId = null): void
    {
        $guard = new PeriodCloseIntegrityGuard();
        $siteScopeId = $guard->siteScopeId($siteId);
        if ($companyId < 1) {
            throw new RuntimeException('Company is required to reopen a period.');
        }

        $db = Database::connect();
        if (! $db->fieldExists('site_scope_id', 'period_closes')) {
            throw new RuntimeException('Period close site-scope upgrade is required. Run the latest database migration first.');
        }

        $db->transBegin();
        try {
            $row = $db->query(
                'SELECT * FROM period_closes WHERE id = ? AND company_id = ? AND site_scope_id = ? '
                . 'AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                [$id, $companyId, $siteScopeId]
            )->getRowArray();
            if ($row === null) {
                throw new RuntimeException('Period close record not found in the active company/site.');
            }
            $guard->assertCanReopen($row['status'] ?? null);

            $payload = [
                'status' => 'open',
                'reopened_at' => date('Y-m-d H:i:s'),
                'reopened_by' => $userId,
                'updated_by' => $userId,
            ];
            (new PeriodCloseModel())->update($id, $payload);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to reopen period.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }

        $this->audit('period.reopen', $id, array_replace($row, $payload), $userId);
    }

    public function assertOpen(string $module, int $companyId, ?string $date, ?int $siteId = null): void
    {
        $module = strtolower(trim($module));
        $period = (new PeriodCloseIntegrityGuard())->postingPeriod(
            $module,
            $companyId,
            $date,
            array_keys(self::modules())
        );

        $model = new PeriodCloseModel();
        $query = $model
            ->where('company_id', $companyId)
            ->where('module_code', $module)
            ->where('period', $period)
            ->where('status', 'closed')
            ->where('deleted_at', null);

        if ($siteId !== null) {
            $query->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->groupEnd();
        }

        if ($query->first() !== null) {
            throw new RuntimeException(strtoupper($module) . ' period ' . $period . ' is closed.');
        }
    }

    /** @return array{0: string, 1: string} */
    private function periodRange(string $period): array
    {
        $start = new DateTimeImmutable($period . '-01');
        return [$start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d')];
    }

    private function audit(string $action, int $recordId, array $values, ?int $userId): void
    {
        (new AuditLogService())->log('finance.period', $action, [
            'company_id' => $values['company_id'] ?? null,
            'site_id' => $values['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'period_closes',
            'record_id' => $recordId,
            'record_code' => ($values['module_code'] ?? '') . '-' . ($values['period'] ?? ''),
            'description' => 'Period status changed.',
            'new_values' => $values,
        ]);
    }
}
