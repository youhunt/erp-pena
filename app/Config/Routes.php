<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'DashboardController::index');
$routes->get('dashboard', 'DashboardController::index');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('select-context', 'ContextController::index');
    $routes->post('select-context', 'ContextController::set');
    $routes->get('development-status', 'System\DevelopmentStatusController::index');
    $routes->get('core/health', 'System\CoreHealthController::index');
    $routes->get('core/audit/transactions', 'System\TransactionAuditController::index');
    $routes->get('core/permissions', 'System\PermissionMatrixController::index');
    $routes->get('core/transaction-permissions', 'System\TransactionPermissionMatrixController::index');
    $routes->post('core/transaction-permissions/seed', 'System\TransactionPermissionMatrixController::seed');
    $routes->get('modules/calculate-cost', 'Costing\CostCalculationController::index');
    $routes->post('modules/calculate-cost', 'Costing\CostCalculationController::calculate');
    $routes->post('modules/calculate-cost/calculate-all', 'Costing\CostCalculationController::calculateAll');
    $routes->get('modules/calculate-cost/export', 'Costing\CostCalculationController::export');
    $routes->get('modules/calculate-cost/export-all', 'Costing\CostCalculationController::exportAll');
    $routes->get('auto-setup', 'System\AutoSetupController::index');
    $routes->post('auto-setup/run', 'System\AutoSetupController::run');
    $routes->get('data-import', 'System\DataImportController::index');
    $routes->post('data-import/upload', 'System\DataImportController::upload');
    $routes->get('data-import/template/(:segment)', 'System\DataImportController::template/$1');
    $routes->get('excel-transfer', 'System\ExcelTransferController::index');
    $routes->get('excel-transfer/template/(:segment)', 'System\ExcelTransferController::template/$1');
    $routes->post('excel-transfer/import', 'System\ExcelTransferController::import');
    $routes->get('excel-transfer/export/(:segment)', 'System\ExcelTransferController::export/$1');
    $routes->get('master-data', 'MasterData\MasterDataController::overview');
    $routes->get('admin/menu-management', 'Admin\MenuManagementController::index');
    $routes->post('admin/menu-management/seed', 'Admin\MenuManagementController::seed');
    $routes->post('admin/menu-management', 'Admin\MenuManagementController::store');
    $routes->get('admin/menu-management/(:num)', 'Admin\MenuManagementController::show/$1');
    $routes->post('admin/menu-management/(:num)', 'Admin\MenuManagementController::update/$1');
    $routes->post('admin/menu-management/(:num)/toggle', 'Admin\MenuManagementController::toggle/$1');
    $routes->get('admin/modules', 'Admin\ModuleController::index');
    $routes->post('admin/modules', 'Admin\ModuleController::save');
    $routes->post('admin/modules/refresh', 'Admin\ModuleController::refresh');
    $routes->get('audit-logs', 'AuditLogController::index');

    $routes->group('inventory', static function (RouteCollection $routes): void {
        $routes->get('stock-balances', 'Inventory\InventoryReportController::stockBalances');
        $routes->get('stock-alerts', 'Inventory\InventoryReportController::stockAlerts');
        $routes->get('stock-card', 'Inventory\InventoryReportController::stockCard');
        $routes->get('inout', 'Inventory\InventoryInOutController::index');
        $routes->get('inout/new', 'Inventory\InventoryInOutController::create');
        $routes->post('inout', 'Inventory\InventoryInOutController::store');
        $routes->get('inout/(:num)', 'Inventory\InventoryInOutController::show/$1');
        $routes->get('movements', 'Inventory\MovementDocumentController::index');
        $routes->get('movements/new', 'Inventory\MovementDocumentController::create');
        $routes->post('movements', 'Inventory\MovementDocumentController::store');
        $routes->get('movements/(:num)', 'Inventory\MovementDocumentController::show/$1');
        $routes->get('transfers', 'Inventory\StockTransferController::index');
        $routes->get('transfers/new', 'Inventory\StockTransferController::create');
        $routes->post('transfers', 'Inventory\StockTransferController::store');
        $routes->get('transfers/(:num)', 'Inventory\StockTransferController::show/$1');
        $routes->get('stock-opname', 'Inventory\StockOpnameController::index');
        $routes->get('stock-opname/new', 'Inventory\StockOpnameController::create');
        $routes->post('stock-opname', 'Inventory\StockOpnameController::store');
        $routes->get('stock-opname/(:num)', 'Inventory\StockOpnameController::show/$1');
        $routes->post('stock-opname/(:num)/count', 'Inventory\StockOpnameController::recordCount/$1');
        $routes->post('stock-opname/(:num)/post', 'Inventory\StockOpnameController::post/$1');
        $routes->get('adjustments', 'Inventory\StockAdjustmentController::index');
        $routes->get('adjustments/new', 'Inventory\StockAdjustmentController::create');
        $routes->post('adjustments', 'Inventory\StockAdjustmentController::store');
        $routes->get('adjustments/(:num)', 'Inventory\StockAdjustmentController::show/$1');
    });

    $routes->group('sales', static function (RouteCollection $routes): void {
        $routes->get('orders', 'Sales\SalesOrderController::index');
        $routes->get('orders/export', 'System\DocumentAuditExportController::salesOrders');
        $routes->get('orders/new', 'Sales\SalesOrderController::create');
        $routes->get('orders/import', 'System\OrderImportController::salesForm');
        $routes->get('orders/import-template', 'System\OrderImportController::salesTemplate');
        $routes->post('orders/import', 'System\OrderImportController::importSales');
        $routes->post('orders/import/commit', 'System\OrderImportController::commitSales');
        $routes->post('orders', 'Sales\SalesOrderController::store');
        $routes->get('orders/(:num)/edit', 'Sales\SalesOrderController::edit/$1');
        $routes->post('orders/(:num)', 'Sales\SalesOrderController::update/$1');
        $routes->get('orders/(:num)/export', 'System\DocumentAuditExportController::salesOrder/$1');
        $routes->get('orders/(:num)', 'Sales\SalesOrderController::show/$1');
        $routes->post('orders/(:num)/submit', 'Sales\SalesOrderController::submit/$1');
        $routes->post('orders/(:num)/approve', 'Sales\SalesOrderController::approve/$1');
        $routes->post('orders/(:num)/reserve', 'Sales\SalesOrderController::reserve/$1');
        $routes->post('orders/(:num)/cancel', 'Sales\SalesOrderController::cancel/$1');
        $routes->get('orders/(:num)/allocate', 'Sales\AllocationController::createFromSo/$1');
        $routes->post('orders/(:num)/allocate', 'Sales\AllocationController::storeFromSo/$1');
        $routes->get('allocations', 'Sales\AllocationController::index');
        $routes->get('allocations/(:num)/edit', 'Sales\AllocationController::edit/$1');
        $routes->post('allocations/(:num)', 'Sales\AllocationController::update/$1');
        $routes->get('allocations/(:num)', 'Sales\AllocationController::show/$1');
        $routes->get('orders/(:num)/deliver', 'Sales\SalesDeliveryController::createFromSo/$1');
        $routes->post('orders/(:num)/deliver', 'Sales\SalesDeliveryController::storeFromSo/$1');
        $routes->get('deliveries', 'Sales\SalesDeliveryController::index');
        $routes->get('deliveries/export', 'System\DocumentAuditExportController::salesDeliveries');
        $routes->get('deliveries/import', 'System\FulfillmentImportController::salesDeliveryForm');
        $routes->get('deliveries/import-template', 'System\FulfillmentImportController::salesDeliveryTemplate');
        $routes->post('deliveries/import', 'System\FulfillmentImportController::importSalesDelivery');
        $routes->post('deliveries/import/commit', 'System\FulfillmentImportController::commitSalesDelivery');
        $routes->get('deliveries/(:num)/export', 'System\DocumentAuditExportController::salesDelivery/$1');
        $routes->get('deliveries/(:num)', 'Sales\SalesDeliveryController::show/$1');
        $routes->post('deliveries/(:num)/reverse', 'Sales\SalesDeliveryController::reverse/$1');
        $routes->get('deliveries/(:num)/invoice', 'AccountsReceivable\SalesInvoiceController::createFromDelivery/$1');
        $routes->post('deliveries/(:num)/invoice', 'AccountsReceivable\SalesInvoiceController::storeFromDelivery/$1');
        $routes->get('reports/margins', 'Sales\SalesMarginReportController::index');
    });

    $routes->group('ar', static function (RouteCollection $routes): void {
        $routes->get('manual-invoices/new', 'AccountsReceivable\SalesInvoiceController::newManual');
        $routes->post('manual-invoices', 'AccountsReceivable\SalesInvoiceController::storeManual');
        $routes->get('sales-invoices', 'AccountsReceivable\SalesInvoiceController::index');
        $routes->get('sales-invoices/(:num)', 'AccountsReceivable\SalesInvoiceController::show/$1');
        $routes->post('sales-invoices/(:num)/cancel', 'AccountsReceivable\SalesInvoiceController::cancel/$1');
        $routes->get('sales-invoices/(:num)/receipt', 'AccountsReceivable\ReceiptController::createFromInvoice/$1');
        $routes->post('sales-invoices/(:num)/receipt', 'AccountsReceivable\ReceiptController::storeFromInvoice/$1');
        $routes->get('receipts', 'AccountsReceivable\ReceiptController::index');
        $routes->get('receipts/(:num)', 'AccountsReceivable\ReceiptController::show/$1');
        $routes->post('receipts/(:num)/cancel', 'AccountsReceivable\ReceiptController::cancel/$1');
        $routes->get('aging', 'Finance\AgingController::ar');
    });

    $routes->group('purchase', static function (RouteCollection $routes): void {
        $routes->get('orders', 'Purchase\PurchaseOrderController::index');
        $routes->get('orders/export', 'System\DocumentAuditExportController::purchaseOrders');
        $routes->get('orders/new', 'Purchase\PurchaseOrderController::create');
        $routes->get('orders/import', 'System\OrderImportController::purchaseForm');
        $routes->get('orders/import-template', 'System\OrderImportController::purchaseTemplate');
        $routes->post('orders/import', 'System\OrderImportController::importPurchase');
        $routes->post('orders/import/commit', 'System\OrderImportController::commitPurchase');
        $routes->post('orders', 'Purchase\PurchaseOrderController::store');
        $routes->get('orders/(:num)/edit', 'Purchase\PurchaseOrderController::edit/$1');
        $routes->post('orders/(:num)', 'Purchase\PurchaseOrderController::update/$1');
        $routes->get('orders/(:num)/export', 'System\DocumentAuditExportController::purchaseOrder/$1');
        $routes->get('orders/(:num)', 'Purchase\PurchaseOrderController::show/$1');
        $routes->post('orders/(:num)/submit', 'Purchase\PurchaseOrderController::submit/$1');
        $routes->post('orders/(:num)/approve', 'Purchase\PurchaseOrderController::approve/$1');
        $routes->post('orders/(:num)/close', 'Purchase\PurchaseOrderController::close/$1');
        $routes->post('orders/(:num)/cancel', 'Purchase\PurchaseOrderController::cancel/$1');
        $routes->post('orders/(:num)/activate', 'Purchase\PurchaseOrderController::activate/$1');
        $routes->get('orders/(:num)/receive', 'Purchase\PurchaseReceiptController::createFromPo/$1');
        $routes->post('orders/(:num)/receive', 'Purchase\PurchaseReceiptController::storeFromPo/$1');
        $routes->get('receipts', 'Purchase\PurchaseReceiptController::index');
        $routes->get('receipts/export', 'System\DocumentAuditExportController::purchaseReceipts');
        $routes->get('receipts/import', 'System\FulfillmentImportController::purchaseReceiptForm');
        $routes->get('receipts/import-template', 'System\FulfillmentImportController::purchaseReceiptTemplate');
        $routes->post('receipts/import', 'System\FulfillmentImportController::importPurchaseReceipt');
        $routes->post('receipts/import/commit', 'System\FulfillmentImportController::commitPurchaseReceipt');
        $routes->get('receipts/(:num)/export', 'System\DocumentAuditExportController::purchaseReceipt/$1');
        $routes->get('receipts/(:num)', 'Purchase\PurchaseReceiptController::show/$1');
        $routes->post('receipts/(:num)/reverse', 'Purchase\PurchaseReceiptController::reverse/$1');
        $routes->get('receipts/(:num)/invoice', 'AccountsPayable\PurchaseInvoiceController::createFromReceipt/$1');
        $routes->post('receipts/(:num)/invoice', 'AccountsPayable\PurchaseInvoiceController::storeFromReceipt/$1');
    });

    $routes->group('ap', static function (RouteCollection $routes): void {
        $routes->get('manual-invoices/new', 'AccountsPayable\PurchaseInvoiceController::newManual');
        $routes->post('manual-invoices', 'AccountsPayable\PurchaseInvoiceController::storeManual');
        $routes->get('purchase-invoices', 'AccountsPayable\PurchaseInvoiceController::index');
        $routes->get('purchase-invoices/(:num)', 'AccountsPayable\PurchaseInvoiceController::show/$1');
        $routes->post('purchase-invoices/(:num)/cancel', 'AccountsPayable\PurchaseInvoiceController::cancel/$1');
        $routes->get('purchase-invoices/(:num)/payment', 'AccountsPayable\PaymentController::createFromInvoice/$1');
        $routes->post('purchase-invoices/(:num)/payment', 'AccountsPayable\PaymentController::storeFromInvoice/$1');
        $routes->get('payments', 'AccountsPayable\PaymentController::index');
        $routes->get('payments/(:num)', 'AccountsPayable\PaymentController::show/$1');
        $routes->post('payments/(:num)/cancel', 'AccountsPayable\PaymentController::cancel/$1');
        $routes->get('aging', 'Finance\AgingController::ap');
    });

    $routes->group('gl', static function (RouteCollection $routes): void {
        $routes->get('utilities', 'Finance\GeneralLedgerController::utilities');
        $routes->post('utilities/init-defaults', 'Finance\GeneralLedgerController::initDefaults');
        $routes->post('utilities/sync-legacy-coa', 'Finance\GeneralLedgerController::syncLegacyCoa');
        $routes->post('utilities/sync-legacy-books', 'Finance\GeneralLedgerController::syncLegacyBooks');
        $routes->get('legacy-excel', 'Finance\GeneralLedgerController::utilities');
        $routes->get('books', 'Finance\GeneralLedgerController::books');
        $routes->get('columns', 'Finance\GeneralLedgerController::columns');
        $routes->get('legacy-coa', 'Finance\GeneralLedgerController::legacyCoa');
        $routes->get('chart-of-accounts', 'Finance\GeneralLedgerController::chartAccounts');
        $routes->get('posting-profiles', 'Finance\GeneralLedgerController::postingProfiles');
        $routes->post('posting-profiles', 'Finance\GeneralLedgerController::updatePostingProfiles');
        $routes->get('period-close', 'Finance\PeriodCloseController::index');
        $routes->post('period-close', 'Finance\PeriodCloseController::close');
        $routes->post('period-close/(:num)/reopen', 'Finance\PeriodCloseController::reopen/$1');
        $routes->get('entries', 'Finance\GeneralLedgerController::entries');
        $routes->get('entries/(:num)', 'Finance\GeneralLedgerController::showEntry/$1');
    });

    $routes->group('cash-bank', static function (RouteCollection $routes): void {
        $routes->get('cash-entries', 'CashBank\CashBankController::cashEntries');
        $routes->get('cash-entries/new', 'CashBank\CashBankController::createCashEntry');
        $routes->post('cash-entries', 'CashBank\CashBankController::storeCashEntry');
        $routes->get('cash-entries/(:num)', 'CashBank\CashBankController::showCashEntry/$1');
        $routes->get('bank-entries', 'CashBank\CashBankController::bankEntries');
        $routes->get('bank-entries/new', 'CashBank\CashBankController::createBankEntry');
        $routes->post('bank-entries', 'CashBank\CashBankController::storeBankEntry');
        $routes->get('bank-entries/(:num)', 'CashBank\CashBankController::showBankEntry/$1');
        $routes->get('statement-import', 'CashBank\StatementImportController::index');
        $routes->post('statement-import/import', 'CashBank\StatementImportController::import');
        $routes->get('statement-match', 'CashBank\StatementMatchController::index');
        $routes->post('statement-match/run', 'CashBank\StatementMatchController::run');
        $routes->get('reconciliation', 'CashBank\ReconciliationController::index');
    });

    $routes->group('production', static function (RouteCollection $routes): void {
        $routes->get('imports/(:segment)', 'Production\ProductionImportController::form/$1');
        $routes->get('imports/(:segment)/template', 'Production\ProductionImportController::template/$1');
        $routes->post('imports/(:segment)', 'Production\ProductionImportController::import/$1');
        $routes->get('forecasts', 'Production\PlanningController::forecasts');
        $routes->post('forecasts', 'Production\PlanningController::storeForecast');
        $routes->get('mps', 'ModulePlaceholderController::mps');
        $routes->get('planned-released', 'ModulePlaceholderController::plannedReleased');
        $routes->get('mrp', 'Production\PlanningController::mrp');
        $routes->post('mrp/run', 'Production\PlanningController::runMrp');
        $routes->get('mrp/runs/(:num)', 'Production\PlanningController::showMrpRun/$1');
        $routes->get('boms', 'Production\ProductionMasterController::boms');
        $routes->get('boms/new', 'Production\ProductionMasterController::newBom');
        $routes->post('boms', 'Production\ProductionMasterController::storeBom');
        $routes->get('boms/(:num)', 'Production\ProductionMasterController::showBom');
        $routes->get('boms/(:num)/edit', 'Production\ProductionEditController::editBom');
        $routes->post('boms/(:num)', 'Production\ProductionEditController::updateBom');
        $routes->get('work-centers', 'Production\ProductionMasterController::workCenters');
        $routes->get('work-centers/new', 'Production\ProductionMasterController::newWorkCenter');
        $routes->post('work-centers', 'Production\ProductionMasterController::storeWorkCenter');
        $routes->get('work-centers/(:num)', 'Production\ProductionMasterController::showWorkCenter');
        $routes->get('work-centers/(:num)/edit', 'Production\ProductionEditController::editWorkCenter');
        $routes->post('work-centers/(:num)', 'Production\ProductionEditController::updateWorkCenter');
        $routes->get('routings', 'Production\ProductionMasterController::routings');
        $routes->get('routings/new', 'Production\ProductionMasterController::newRouting');
        $routes->post('routings', 'Production\ProductionMasterController::storeRouting');
        $routes->get('routings/(:num)', 'Production\ProductionMasterController::showRouting');
        $routes->get('routings/(:num)/edit', 'Production\ProductionEditController::editRouting');
        $routes->post('routings/(:num)', 'Production\ProductionEditController::updateRouting');
        $routes->get('work-orders', 'Production\WorkOrderController::index');
        $routes->get('work-orders/new', 'Production\WorkOrderController::create');
        $routes->post('work-orders', 'Production\WorkOrderController::store');
        $routes->get('work-orders/(:num)', 'Production\WorkOrderController::show');
        $routes->post('work-orders/(:num)/allocate', 'Production\WorkOrderController::allocate');
        $routes->post('work-orders/(:num)/issue', 'Production\WorkOrderController::issueMaterials');
        $routes->post('work-orders/(:num)/receive', 'Production\WorkOrderController::receiveFinished');
        $routes->post('work-orders/(:num)/issue-receive', 'Production\WorkOrderController::issueReceive');
        $routes->get('period-close', 'ModulePlaceholderController::periodClose');
    });

    $routes->get('production-period-close', 'ModulePlaceholderController::periodClose');

    $taxMasterRoutes = ['vat', 'item-vat', 'other-charge-vat', 'charge-vat', 'wht'];
    foreach ($taxMasterRoutes as $master) {
        $routes->get('setup/' . $master, 'Setup\TaxMasterController::index/' . $master);
        $routes->get('setup/' . $master . '/new', 'Setup\TaxMasterController::create/' . $master);
        $routes->post('setup/' . $master, 'Setup\TaxMasterController::store/' . $master);
        $routes->get('setup/' . $master . '/(:num)', 'Setup\TaxMasterController::show/' . $master . '/$1');
        $routes->get('setup/' . $master . '/(:num)/edit', 'Setup\TaxMasterController::edit/' . $master . '/$1');
        $routes->post('setup/' . $master . '/(:num)', 'Setup\TaxMasterController::update/' . $master . '/$1');
        $routes->post('setup/' . $master . '/(:num)/delete', 'Setup\TaxMasterController::delete/' . $master . '/$1');
    }

    $genericSetupRoutes = ['transaction-codes', 'prefix-codes', 'companies', 'sites', 'departments', 'warehouses', 'locations', 'countries', 'provinces', 'cities', 'postal-codes', 'currencies', 'uoms', 'uom-conversions', 'address-master', 'customer-terms', 'customer-promos', 'customers', 'supplier-terms', 'supplier-promos', 'suppliers', 'items', 'item-locations', 'batch-masters'];
    foreach ($genericSetupRoutes as $master) {
        $routes->get('setup/' . $master, 'Setup\MasterDataController::index/' . $master);
        $routes->get('setup/' . $master . '/new', 'Setup\MasterDataController::create/' . $master);
        $routes->post('setup/' . $master, 'Setup\MasterDataController::store/' . $master);
        $routes->get('setup/' . $master . '/(:num)', 'Setup\MasterDataController::show/' . $master . '/$1');
        $routes->get('setup/' . $master . '/(:num)/edit', 'Setup\MasterDataController::edit/' . $master . '/$1');
        $routes->post('setup/' . $master . '/(:num)', 'Setup\MasterDataController::update/' . $master . '/$1');
        $routes->post('setup/' . $master . '/(:num)/delete', 'Setup\MasterDataController::delete/' . $master . '/$1');
    }

    $routes->get('setup/document-numbering', 'Setup\DocumentNumberingController::index');
    $routes->get('setup/document-numbering/new', 'Setup\DocumentNumberingController::create');
    $routes->post('setup/document-numbering', 'Setup\DocumentNumberingController::store');
    $routes->get('setup/document-numbering/(:num)/edit', 'Setup\DocumentNumberingController::edit/$1');
    $routes->post('setup/document-numbering/(:num)', 'Setup\DocumentNumberingController::update/$1');

    $routes->get('modules/(:segment)', 'ModulePlaceholderController::show/$1');
});
