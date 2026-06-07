<?php

namespace App\Services\Finance;

use App\Models\PeriodCloseModel;
use App\Services\AuditLogService;
use DateTimeImmutable;
use RuntimeException;

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
        $module = $this->normalizeModule((string) ($data['module_code'] ?? ''));
        $period = $this->normalizePeriod((string) ($data['period'] ?? ''));

        if ($companyId < 1 || $module === '' || $period === '') {
            throw new RuntimeException('Company, module, and period are required.');
        }

        [$start, $end] = $this->periodRange($period);
        $model = new PeriodCloseModel();
        $existing = $model
            ->where('company_id', $companyId)
            ->where('module_code', $module)
            ->where('period', $period)
            ->where('deleted_at', null)
            ->first();

        $payload = [
            'company_id' => $companyId,
            'site_id' => $data['site_id'] ?? null,
            'module_code' => $module,
            'period' => $period,
            'period_start' => $start,
            'period_end' => $end,
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
            'closed_by' => $userId,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $userId,
        ];

        if ($existing !== null) {
            $model->update((int) $existing['id'], $payload);
            $periodCloseId = (int) $existing['id'];
        } else {
            $periodCloseId = (int) $model->insert($payload + ['created_by' => $userId], true);
        }

        $this->audit('period.close', $periodCloseId, $payload, $userId);

        return $periodCloseId;
    }

    public function reopen(int $id, ?int $userId = null): void
    {
        $model = new PeriodCloseModel();
        $row = $model->find($id);
        if ($row === null) {
            throw new RuntimeException('Period close record not found.');
        }

        $payload = [
            'status' => 'open',
            'reopened_at' => date('Y-m-d H:i:s'),
            'reopened_by' => $userId,
            'updated_by' => $userId,
        ];
        $model->update($id, $payload);
        $this->audit('period.reopen', $id, $row + $payload, $userId);
    }

    public function assertOpen(string $module, int $companyId, ?string $date, ?int $siteId = null): void
    {
        if ($companyId < 1 || empty($date)) {
            return;
        }

        $module = $this->normalizeModule($module);
        $period = substr((string) $date, 0, 7);
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return;
        }

        $query = (new PeriodCloseModel())
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

    private function normalizeModule(string $module): string
    {
        $module = strtolower(trim($module));
        return array_key_exists($module, self::modules()) ? $module : '';
    }

    private function normalizePeriod(string $period): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $period) ? $period : '';
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
