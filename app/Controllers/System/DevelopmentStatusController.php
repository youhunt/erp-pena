<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;

class DevelopmentStatusController extends BaseController
{
    public function index(): string
    {
        $modules = [
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

        $coreFlows = [
            [
                'flow' => 'Purchasing E2E',
                'steps' => 'PO → Receipt → Stock Card → AP Invoice → AP Payment → GL',
                'entry' => site_url('purchase/orders'),
                'audit' => site_url('gl/entries'),
                'status' => 'Primary UAT',
            ],
            [
                'flow' => 'Sales E2E',
                'steps' => 'SO → Delivery → Stock Card → AR Invoice → AR Receipt → GL',
                'entry' => site_url('sales/orders'),
                'audit' => site_url('inventory/stock-card'),
                'status' => 'Primary UAT',
            ],
            [
                'flow' => 'Inventory Control',
                'steps' => 'Stock Adjustment → Stock Card → Stock Balance → GL',
                'entry' => site_url('inventory/stock-adjustment'),
                'audit' => site_url('inventory/stock-card'),
                'status' => 'Secondary UAT',
            ],
            [
                'flow' => 'Cash / Bank',
                'steps' => 'Cash/Bank Entry → GL → Bank Statement → Reconciliation',
                'entry' => site_url('cash-bank/accounts'),
                'audit' => site_url('cash-bank/reconciliations'),
                'status' => 'Next Hardening',
            ],
            [
                'flow' => 'Production Core',
                'steps' => 'BOM → Routing → Work Order → Issue Material → Receive Finished Good → Stock Card',
                'entry' => site_url('production/work-orders'),
                'audit' => site_url('inventory/stock-card'),
                'status' => 'UAT Ready',
            ],
        ];

        $coreGuardrails = [
            'Run required hosting SQL before browser UAT, especially document number, receipt/delivery reversal, PO UAT, and SO UAT SQL files.',
            'Test every transaction action through the button and through direct URL/POST replay; service-layer guard must reject invalid status.',
            'Use one clean test company/site first, then repeat selected tests with another site to verify tenant isolation.',
            'Always compare transaction result with Stock Card and GL Entries; business flow is not considered pass until audit pages match.',
            'For Production Work Order, test draft edit, allocate, issue, receive, and combined issue+receive rollback scenario.',
        ];

        $nextCoreBacklog = [
            ['priority' => 1, 'item' => 'Run Purchasing E2E UAT', 'target' => 'Verify PO receipt stock-in, AP payable, payment, cash/bank, and GL.'],
            ['priority' => 2, 'item' => 'Run Sales E2E UAT', 'target' => 'Verify SO delivery stock-out, AR receivable, receipt, cash/bank, and GL.'],
            ['priority' => 3, 'item' => 'Harden Cash/Bank audit', 'target' => 'Improve reconciliation and cash/bank reporting after E2E flow passes.'],
            ['priority' => 4, 'item' => 'Export audit reports', 'target' => 'Add export for Stock Card and GL validation when UAT data is stable.'],
            ['priority' => 5, 'item' => 'Non-admin permission UAT', 'target' => 'Verify finance, sales, purchase, inventory, and production role restrictions.'],
        ];

        $uatFocus = [
            'Run required hosting SQL and confirm document_number_sequences exists.',
            'Test Purchase E2E: PO → Receipt → Stock Card → AP Invoice → AP Payment → GL.',
            'Test Sales E2E: SO → Delivery → Stock Card → AR Invoice → AR Receipt → GL.',
            'Check Stock Card running qty/value after receipt, delivery, production, and adjustment.',
            'Check GL Entries difference = 0 after posting transactions.',
            'Test production edit/import/work-order actions with production.manage permission.',
            'Test non-admin role after core transaction flow is stable.',
        ];

        return view('system/development_status/index', [
            'title' => 'Development Status',
            'modules' => $modules,
            'coreFlows' => $coreFlows,
            'coreGuardrails' => $coreGuardrails,
            'nextCoreBacklog' => $nextCoreBacklog,
            'uatFocus' => $uatFocus,
            'overall' => [
                'internal_development' => 70,
                'internal_demo' => 65,
                'uat_readiness' => 68,
                'production_readiness' => 45,
            ],
        ]);
    }
}
