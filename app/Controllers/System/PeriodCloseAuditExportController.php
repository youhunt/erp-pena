<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\PeriodCloseModel;
use App\Services\Finance\PeriodCloseService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class PeriodCloseAuditExportController extends BaseController
{
    public function index(?string $module = null)
    {
        $tenant = new TenantContext(session());
        $module = $module !== null ? strtolower($module) : null;
        $model = new PeriodCloseModel();
        $this->scope($model, $tenant);
        if ($module !== null && array_key_exists($module, PeriodCloseService::modules())) {
            $model->where('module_code', $module);
        }

        $periods = $model->orderBy('period', 'DESC')->orderBy('module_code', 'ASC')->findAll(10000);

        return $this->xlsxWorkbookResponse('period-close-' . ($module ?: 'all') . '-' . date('Y-m-d') . '.xlsx', [
            'Summary' => $this->summaryRows($periods, $module),
            'Period Close Detail' => $this->periodRows($periods),
        ]);
    }

    public function show(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new PeriodCloseModel();
        $this->scope($model, $tenant);
        $period = $model->find($id);
        if ($period === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->xlsxWorkbookResponse('period-close-' . ($period['module_code'] ?? 'module') . '-' . ($period['period'] ?? $id) . '.xlsx', [
            'Summary' => $this->singlePeriodSummaryRows($period),
            'Period Detail' => $this->periodRows([$period]),
        ]);
    }

    private function summaryRows(array $periods, ?string $module): array
    {
        $closed = 0;
        $reopened = 0;
        foreach ($periods as $period) {
            (($period['status'] ?? '') === 'closed') ? $closed++ : $reopened++;
        }

        return [
            ['Metric', 'Value'],
            ['Report', 'Period Close'],
            ['Module Filter', $module !== null ? (PeriodCloseService::modules()[$module] ?? $module) : 'ALL'],
            ['Rows', count($periods)],
            ['Closed Rows', $closed],
            ['Open/Reopened Rows', $reopened],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function singlePeriodSummaryRows(array $period): array
    {
        return [
            ['Metric', 'Value'],
            ['Report', 'Period Close Detail'],
            ['Module', PeriodCloseService::modules()[$period['module_code'] ?? ''] ?? ($period['module_code'] ?? '-')],
            ['Period', (string) ($period['period'] ?? '-')],
            ['Period Start', (string) ($period['period_start'] ?? '-')],
            ['Period End', (string) ($period['period_end'] ?? '-')],
            ['Status', (string) ($period['status'] ?? '-')],
            ['Closed At', (string) ($period['closed_at'] ?? '')],
            ['Reopened At', (string) ($period['reopened_at'] ?? '')],
            ['Notes', (string) ($period['notes'] ?? '')],
            ['Generated At', date('Y-m-d H:i:s')],
        ];
    }

    private function periodRows(array $periods): array
    {
        $rows = [[
            'Module Code',
            'Module Name',
            'Period',
            'Period Start',
            'Period End',
            'Status',
            'Closed At',
            'Reopened At',
            'Closed By',
            'Reopened By',
            'Notes',
            'Created At',
            'Updated At',
        ]];

        foreach ($periods as $period) {
            $moduleCode = (string) ($period['module_code'] ?? '');
            $rows[] = [
                $moduleCode,
                PeriodCloseService::modules()[$moduleCode] ?? $moduleCode,
                $period['period'] ?? '',
                $period['period_start'] ?? '',
                $period['period_end'] ?? '',
                $period['status'] ?? '',
                $period['closed_at'] ?? '',
                $period['reopened_at'] ?? '',
                $period['closed_by'] ?? '',
                $period['reopened_by'] ?? '',
                $period['notes'] ?? '',
                $period['created_at'] ?? '',
                $period['updated_at'] ?? '',
            ];
        }

        return $rows;
    }

    private function scope($model, TenantContext $tenant): void
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }
    }

    /**
     * @param array<string, array<int, array<int, mixed>>> $sheets
     */
    private function xlsxWorkbookResponse(string $filename, array $sheets)
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new RuntimeException('PhpSpreadsheet is required to generate Excel files. Run composer install.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetIndex = 0;
        foreach ($sheets as $sheetTitle => $rows) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(substr((string) $sheetTitle, 0, 31));
            $sheet->fromArray($rows, null, 'A1');
            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestRow();
            if ($highestRow >= 1) {
                $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
                $sheet->setAutoFilter('A1:' . $highestColumn . max(1, $highestRow));
            }
            foreach (range('A', $highestColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheetIndex++;
        }
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean() ?: '';
        $spreadsheet->disconnectWorksheets();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }
}
