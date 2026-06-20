<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;

class DevelopmentStatusController extends BaseController
{
    public function index(): string
    {
        $modules = [
            ['area' => 'Foundation', 'status' => 'Partial', 'readiness' => 80, 'notes' => 'CI4, Shield, Skote, tenant, menu, docs baseline.'],
            ['area' => 'Multi Company / Site', 'status' => 'Partial', 'readiness' => 75, 'notes' => 'Active company/site works; permission UAT still needed.'],
            ['area' => 'Document Numbering', 'status' => 'Done', 'readiness' => 85, 'notes' => 'PO/SO/PR/DO/SI/PI/ARR/APP auto numbering.'],
            ['area' => 'Purchase Order', 'status' => 'Partial', 'readiness' => 75, 'notes' => 'Manual/import patched; PO+Site key, discount/tax, freight relaxed.'],
            ['area' => 'Purchase Receipt', 'status' => 'Patched', 'readiness' => 70, 'notes' => 'Stock-in and PO received/outstanding recalculation.'],
            ['area' => 'AP Invoice', 'status' => 'Partial', 'readiness' => 60, 'notes' => 'Receipt to invoice and payable open.'],
            ['area' => 'AP Payment', 'status' => 'Partial', 'readiness' => 55, 'notes' => 'Payment posting with auto APP number and balance update.'],
            ['area' => 'Sales Order', 'status' => 'Partial', 'readiness' => 75, 'notes' => 'Manual/import patched; customer/item auto-fill and reopen cancelled SO.'],
            ['area' => 'Sales Delivery', 'status' => 'Patched', 'readiness' => 65, 'notes' => 'Stock-out and SO delivered/outstanding recalculation.'],
            ['area' => 'AR Invoice', 'status' => 'Partial', 'readiness' => 60, 'notes' => 'Delivery to invoice and receivable open.'],
            ['area' => 'AR Receipt', 'status' => 'Partial', 'readiness' => 55, 'notes' => 'Receipt posting with auto ARR number and balance update.'],
            ['area' => 'Inventory Audit', 'status' => 'Partial', 'readiness' => 65, 'notes' => 'Stock card qty/value in-out and running value.'],
            ['area' => 'GL Validation', 'status' => 'Partial', 'readiness' => 60, 'notes' => 'Debit/credit validation and trial balance summary.'],
            ['area' => 'AI/OCR', 'status' => 'Partial', 'readiness' => 45, 'notes' => 'Upload/review/convert foundation exists; full UAT pending.'],
            ['area' => 'Production Readiness', 'status' => 'Pending', 'readiness' => 40, 'notes' => 'Core is UAT-ready but not full production candidate yet.'],
        ];

        $uatFocus = [
            'Run required hosting SQL and confirm document_number_sequences exists.',
            'Test Purchase E2E: PO → Receipt → Stock Card → AP Invoice → AP Payment → GL.',
            'Test Sales E2E: SO → Delivery → Stock Card → AR Invoice → AR Receipt → GL.',
            'Check Stock Card running qty/value after receipt, delivery, and adjustment.',
            'Check GL Entries difference = 0 after posting transactions.',
            'Test non-admin role after core transaction flow is stable.',
        ];

        return view('system/development_status/index', [
            'title' => 'Development Status',
            'modules' => $modules,
            'uatFocus' => $uatFocus,
            'overall' => [
                'internal_development' => 65,
                'internal_demo' => 60,
                'uat_readiness' => 65,
                'production_readiness' => 40,
            ],
        ]);
    }
}
