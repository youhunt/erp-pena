<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DevelopmentStatusController extends BaseController
{
    public function index(): string|ResponseInterface
    {
        $modules = $this->modules();
        $coreFlows = $this->coreFlows();
        $coreGuardrails = $this->coreGuardrails();
        $nextCoreBacklog = $this->nextCoreBacklog();
        $uatFocus = $this->uatFocus();
        $overall = [
            'internal_development' => 70,
            'internal_demo' => 65,
            'uat_readiness' => 68,
            'production_readiness' => 45,
        ];

        if (in_array((string) $this->request->getGet('export'), ['xlsx', 'excel'], true)) {
            return $this->xlsxResponse($modules, $coreFlows, $nextCoreBacklog, $uatFocus, $overall);
        }

        return view('system/development_status/index', [
            'title' => 'Development Status',
            'modules' => $modules,
            'coreFlows' => $coreFlows,
            'coreGuardrails' => $coreGuardrails,
            'nextCoreBacklog' => $nextCoreBacklog,
            'uatFocus' => $uatFocus,
            'overall' => $overall,
        ]);
    }

    private function modules(): array
    {
        return [
            ['area' => 'Foundation', 'status' => 'Stable Foundation', 'readiness' => 85, 'notes' => 'CI4, Shield, Skote, tenant, menu, docs baseline.'],
            ['area' => 'Multi Company / Site', 'status' => 'UAT Ready', 'readiness' => 80, 'notes' => 'Active company/site works; cross-role UAT still needed.'],
            ['area' => 'Document Numbering', 'status' => 'Done', 'readiness' => 90, 'notes' => 'PO/SO/PR/DO/SI/PI/ARR/APP auto numbering.'],
            ['area' => 'Purchase Order', 'status' => 'UAT Ready', 'readiness' => 80, 'notes' => 'Manual/import patched; PO+Site key, discount/tax, freight relaxed, activation fixed.'],
            ['area' => 'Purchase Receipt', 'status' => 'UAT Ready', 'readiness' => 75, 'notes' => 'Stock-in, reversal guard, and PO received/outstanding recalculation.'],
            ['area' => 'AP Invoice', 'status' => 'Core Flow Ready', 'readiness' => 65, 'notes' => 'Receipt to invoice and payable open; cancellation UAT required.'],
            ['area' => 'AP Payment', 'status' => 'Core Flow Ready', 'readiness' => 60, 'notes' => 'Payment posting with auto APP number, cash/bank entry, and balance update.'],
            ['area' => 'Sales Order', 'status' => 'UAT Ready', 'readiness' => 80, 'notes' => 'Manual/import patched; customer/item auto-fill, edit, commercial fields, reopen cancelled SO.'],
            ['area' => 'Sales Delivery', 'status' => 'UAT Ready', 'readiness' => 75, 'notes' => 'Stock-out, reversal guard, and SO delivered/outstanding recalculation.'],
            ['area' => 'AR Invoice', 'status' => 'Core Flow Ready', 'readiness' => 65, 'notes' => 'Delivery to invoice and receivable open; cancellation UAT required.'],
            ['area' => 'AR Receipt', 'status' => 'Core Flow Ready', 'readiness' => 60, 'notes' => 'Receipt posting with auto ARR number, cash/bank entry, and balance update.'],
            ['area' => 'Inventory Audit', 'status' => 'Core Flow Ready', 'readiness' => 70, 'notes' => 'Stock card qty/value in-out and running value.'],
            ['area' => 'GL Validation', 'status' => 'Core Flow Ready', 'readiness' => 65, 'notes' => 'Debit/credit validation and trial balance summary.'],
            ['area' => 'Production Core', 'status' => 'UAT Ready', 'readiness' => 65, 'notes' => 'BOM, Work Center, Routing, Work Order import/edit and WO posting guard.'],
            ['area' => 'Permission / Status Guard', 'status' => 'Hardened', 'readiness' => 75, 'notes' => 'Route permission and service-layer status guard are in place; non-admin UAT required.'],
            ['area' => 'AI/OCR', 'status' => 'Foundation', 'readiness' => 45, 'notes' => 'Upload/review/convert foundation exists; full UAT pending.'],
            ['area' => 'Production Readiness', 'status' => 'Internal UAT', 'readiness' => 45, 'notes' => 'Core is ready for systematic internal UAT, not yet customer production.'],
        ];
    }

    private function coreFlows(): array
    {
        return [
            ['flow' => 'Purchasing E2E', 'steps' => 'PO → Receipt → Stock Card → AP Invoice → AP Payment → GL', 'entry' => site_url('purchase/orders'), 'audit' => site_url('gl/entries'), 'status' => 'Primary UAT'],
            ['flow' => 'Sales E2E', 'steps' => 'SO → Delivery → Stock Card → AR Invoice → AR Receipt → GL', 'entry' => site_url('sales/orders'), 'audit' => site_url('inventory/stock-card'), 'status' => 'Primary UAT'],
            ['flow' => 'Inventory Control', 'steps' => 'Stock Adjustment → Stock Card → Stock Balance → GL', 'entry' => site_url('inventory/stock-adjustment'), 'audit' => site_url('inventory/stock-card'), 'status' => 'Secondary UAT'],
            ['flow' => 'Cash / Bank', 'steps' => 'Cash/Bank Entry → GL → Bank Statement → Reconciliation', 'entry' => site_url('cash-bank/accounts'), 'audit' => site_url('cash-bank/reconciliations'), 'status' => 'Next Hardening'],
            ['flow' => 'Production Core', 'steps' => 'BOM → Routing → Work Order → Issue Material → Receive Finished Good → Stock Card', 'entry' => site_url('production/work-orders'), 'audit' => site_url('inventory/stock-card'), 'status' => 'UAT Ready'],
        ];
    }

    private function coreGuardrails(): array
    {
        return [
            'Run required hosting SQL before browser UAT, especially document number, receipt/delivery reversal, PO UAT, and SO UAT SQL files.',
            'Test every transaction action through the button and through direct URL/POST replay; service-layer guard must reject invalid status.',
            'Use one clean test company/site first, then repeat selected tests with another site to verify tenant isolation.',
            'Always compare transaction result with Stock Card and GL Entries; business flow is not considered pass until audit pages match.',
            'For Production Work Order, test draft edit, allocate, issue, receive, and combined issue+receive rollback scenario.',
        ];
    }

    private function nextCoreBacklog(): array
    {
        return [
            ['priority' => 1, 'item' => 'Run Purchasing E2E UAT', 'target' => 'Verify PO receipt stock-in, AP payable, payment, cash/bank, and GL.'],
            ['priority' => 2, 'item' => 'Run Sales E2E UAT', 'target' => 'Verify SO delivery stock-out, AR receivable, receipt, cash/bank, and GL.'],
            ['priority' => 3, 'item' => 'Harden Cash/Bank audit', 'target' => 'Improve reconciliation and cash/bank reporting after E2E flow passes.'],
            ['priority' => 4, 'item' => 'Export audit reports', 'target' => 'Add export for Stock Card and GL validation when UAT data is stable.'],
            ['priority' => 5, 'item' => 'Non-admin permission UAT', 'target' => 'Verify finance, sales, purchase, inventory, and production role restrictions.'],
        ];
    }

    private function uatFocus(): array
    {
        return [
            'Run required hosting SQL and confirm document_number_sequences exists.',
            'Test Purchase E2E: PO → Receipt → Stock Card → AP Invoice → AP Payment → GL.',
            'Test Sales E2E: SO → Delivery → Stock Card → AR Invoice → AR Receipt → GL.',
            'Check Stock Card running qty/value after receipt, delivery, production, and adjustment.',
            'Check GL Entries difference = 0 after posting transactions.',
            'Test production edit/import/work-order actions with production.manage permission.',
            'Test non-admin role after core transaction flow is stable.',
        ];
    }

    private function xlsxResponse(array $modules, array $coreFlows, array $nextCoreBacklog, array $uatFocus, array $overall): ResponseInterface
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Readiness');

        $sheet->setCellValue('A1', 'PENA ERP Readiness Status');
        $sheet->setCellValue('A2', 'Export Date');
        $sheet->setCellValue('B2', date('Y-m-d H:i:s'));
        $sheet->setCellValue('A4', 'Metric');
        $sheet->setCellValue('B4', 'Readiness');
        $sheet->setCellValue('A5', 'Internal Development');
        $sheet->setCellValue('B5', ((float) $overall['internal_development']) / 100);
        $sheet->setCellValue('A6', 'Internal Demo');
        $sheet->setCellValue('B6', ((float) $overall['internal_demo']) / 100);
        $sheet->setCellValue('A7', 'UAT Readiness');
        $sheet->setCellValue('B7', ((float) $overall['uat_readiness']) / 100);
        $sheet->setCellValue('A8', 'Production Readiness');
        $sheet->setCellValue('B8', ((float) $overall['production_readiness']) / 100);

        $headers = ['Area', 'Status', 'Readiness', 'Notes'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '10', $header);
        }

        $row = 11;
        foreach ($modules as $module) {
            $sheet->setCellValue('A' . $row, $module['area']);
            $sheet->setCellValue('B' . $row, $module['status']);
            $sheet->setCellValue('C' . $row, ((float) $module['readiness']) / 100);
            $sheet->setCellValue('D' . $row, $module['notes']);
            $row++;
        }
        $lastReadinessRow = $row - 1;

        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A4:B4')->getFont()->setBold(true);
        $sheet->getStyle('A10:D10')->getFont()->setBold(true);
        $sheet->getStyle('A4:B8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9D9D9');
        $sheet->getStyle('A10:D' . $lastReadinessRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9D9D9');
        $sheet->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
        $sheet->getStyle('A10:D10')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
        $sheet->getStyle('B5:B8')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('C11:C' . $lastReadinessRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('B5:C' . $lastReadinessRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->freezePane('A11');
        $sheet->setAutoFilter('A10:D' . $lastReadinessRow);
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $flowSheet = $spreadsheet->createSheet();
        $flowSheet->setTitle('UAT Flows');
        $flowSheet->fromArray(['Flow', 'Steps', 'Status', 'Entry', 'Audit'], null, 'A1');
        $flowRow = 2;
        foreach ($coreFlows as $flow) {
            $flowSheet->fromArray([$flow['flow'], $flow['steps'], $flow['status'], $flow['entry'], $flow['audit']], null, 'A' . $flowRow);
            $flowRow++;
        }
        $flowSheet->getStyle('A1:E1')->getFont()->setBold(true);
        $flowSheet->getStyle('A1:E' . max(1, $flowRow - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9D9D9');
        $flowSheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
        foreach (range('A', 'E') as $column) {
            $flowSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $backlogSheet = $spreadsheet->createSheet();
        $backlogSheet->setTitle('Backlog');
        $backlogSheet->fromArray(['Priority', 'Item', 'Target'], null, 'A1');
        $backlogRow = 2;
        foreach ($nextCoreBacklog as $item) {
            $backlogSheet->fromArray([$item['priority'], $item['item'], $item['target']], null, 'A' . $backlogRow);
            $backlogRow++;
        }
        $backlogSheet->setCellValue('E1', 'Current UAT Focus');
        $focusRow = 2;
        foreach ($uatFocus as $focus) {
            $backlogSheet->setCellValue('E' . $focusRow, $focus);
            $focusRow++;
        }
        $backlogLastRow = max($backlogRow - 1, $focusRow - 1, 1);
        $backlogSheet->getStyle('A1:C1')->getFont()->setBold(true);
        $backlogSheet->getStyle('E1')->getFont()->setBold(true);
        $backlogSheet->getStyle('A1:C' . max(1, $backlogRow - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9D9D9');
        $backlogSheet->getStyle('E1:E' . max(1, $focusRow - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9D9D9');
        foreach (['A', 'B', 'C', 'E'] as $column) {
            $backlogSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'pena_erp_readiness_status_' . date('Ymd_His') . '.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'erp_readiness_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $body = file_get_contents($path) ?: '';
        @unlink($path);
        $spreadsheet->disconnectWorksheets();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($body);
    }
}
