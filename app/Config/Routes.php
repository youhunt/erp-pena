<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->post('tenant/switch', 'TenantController::switch');
    $routes->get('modules/(:segment)', 'ModulePlaceholderController::show/$1');
    $routes->get('audit-logs', 'AuditLogController::index');
    $routes->get('audit-logs/(:num)', 'AuditLogController::show/$1');

    $routes->get('print/purchase-orders/(:num)', 'DocumentPrintController::purchaseOrder/$1');
    $routes->get('print/sales-orders/(:num)', 'DocumentPrintController::salesOrder/$1');
    $routes->get('print/purchase-receipts/(:num)', 'DocumentPrintController::purchaseReceipt/$1');
    $routes->get('print/sales-deliveries/(:num)', 'DocumentPrintController::salesDelivery/$1');
    $routes->get('print/purchase-invoices/(:num)', 'DocumentPrintController::purchaseInvoice/$1');
    $routes->get('print/sales-invoices/(:num)', 'DocumentPrintController::salesInvoice/$1');

    $routes->group('system', static function (RouteCollection $routes): void {
        $routes->get('development-status', 'System\DevelopmentStatusController::index');
        $routes->get('data-import', 'System\DataImportController::index');
        $routes->get('data-import/coa/template', 'System\DataImportController::coaTemplate');
        $routes->get('data-import/coa/import', 'System\DataImportController::coaImportForm');
        $routes->post('data-import/coa/import', 'System\DataImportController::coaImport');
        $routes->get('data-import/coa/export', 'System\DataImportController::coaExport');
        $routes->get('data-import/opening-stock/template', 'System\DataImportController::openingStockTemplate');
        $routes->get('data-import/opening-stock/import', 'System\DataImportController::openingStockImportForm');
        $routes->post('data-import/opening-stock/import', 'System\DataImportController::openingStockImport');
        $routes->get('data-import/opening-stock/export', 'System\DataImportController::openingStockExport');
        $routes->get('excel-transfer', 'System\ExcelLiteTransferController::index');
        $routes->get('excel-transfer/(:segment)/template', 'System\ExcelLiteTransferController::template/$1');
        $routes->get('excel-transfer/(:segment)/import', 'System\ExcelLiteTransferController::importForm/$1');
        $routes->post('excel-transfer/(:segment)/import', 'System\ExcelLiteTransferController::import/$1');
        $routes->post('excel-transfer/(:segment)/commit', 'System\ExcelLiteTransferController::commit/$1');
        $routes->get('excel-transfer/(:segment)/errors/(:segment)', 'System\ExcelLiteTransferController::downloadErrors/$1/$2');
        $routes->get('excel-transfer/(:segment)/export', 'System\ExcelLiteTransferController::export/$1');
    });

    $routes->group('admin', static function (RouteCollection $routes): void {
        $routes->get('users', 'Admin\UserController::index');
        $routes->get('users/new', 'Admin\UserController::create');
        $routes->post('users', 'Admin\UserController::store');
        $routes->get('users/(:num)/edit', 'Admin\UserController::edit/$1');
        $routes->post('users/(:num)', 'Admin\UserController::update/$1');
        $routes->post('users/(:num)/toggle', 'Admin\UserController::toggle/$1');
        $routes->get('roles', 'Admin\RoleController::index');
    });

    $routes->group('inventory', static function (RouteCollection $routes): void {
        $routes->get('stock-balances', 'Inventory\StockBalanceController::index');
        $routes->get('stock-alerts', 'Inventory\StockAlertController::index');
        $routes->get('stock-card', 'Inventory\StockCardController::index');
        $routes->get('stock-card/export', 'System\CoreAuditExportController::stockCard');
        $routes->get('in-out', 'Inventory\InventoryMovementController::inOut');
        $routes->post('in-out', 'Inventory\InventoryMovementController::storeInOut');
        $routes->get('movement-documents/(:num)', 'Inventory\InventoryMovementController::showDocument/$1');
        $routes->post('movement-documents/(:num)/reverse', 'Inventory\InventoryMovementDocumentController::reverse/$1');
        $routes->get('transfers', 'Inventory\InventoryTransferController::index');
        $routes->get('transfers/new', 'Inventory\InventoryTransferController::create');
        $routes->post('transfers', 'Inventory\InventoryTransferController::store');
        $routes->post('transfers/(:num)/submit', 'Inventory\InventoryTransferController::submit/$1');
        $routes->post('transfers/(:num)/post', 'Inventory\InventoryTransferController::post/$1');
        $routes->post('transfers/(:num)/cancel', 'Inventory\InventoryTransferController::cancel/$1');
        $routes->post('transfers/(:num)/reverse', 'Inventory\InventoryTransferController::reverse/$1');
        $routes->get('transfers/(:num)', 'Inventory\InventoryTransferController::show/$1');
        $routes->get('stock-opname', 'Inventory\InventoryMovementController::stockOpname');
        $routes->post('stock-opname', 'Inventory\InventoryMovementController::storeStockOpname');
        $routes->get('stock-adjustment', 'Inventory\StockAdjustmentController::create');
        $routes->post('stock-adjustment', 'Inventory\StockAdjustmentController::store');
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
        $routes->get('books', 'Finance\GeneralLedgerController::books');
        $routes->get('columns', 'Finance\GeneralLedgerController::columns');
        $routes->get('legacy-coa', 'Finance\GeneralLedgerController::legacyCoa');
        $routes->get('legacy-excel', 'Finance\LegacyGlExcelController::index');
        $routes->get('legacy-excel/(:segment)/template', 'Finance\LegacyGlExcelController::template/$1');
        $routes->get('legacy-excel/(:segment)/export', 'Finance\LegacyGlExcelController::export/$1');
        $routes->get('legacy-excel/(:segment)/import', 'Finance\LegacyGlExcelController::importForm/$1');
        $routes->post('legacy-excel/(:segment)/import', 'Finance\LegacyGlExcelController::import/$1');
        $routes->post('legacy-excel/(:segment)/commit', 'Finance\LegacyGlExcelController::commit/$1');
        $routes->get('legacy-excel/(:segment)/errors/(:segment)', 'Finance\LegacyGlExcelController::downloadErrors/$1/$2');
        $routes->get('chart-of-accounts', 'Finance\GeneralLedgerController::chartAccounts');
        $routes->get('posting-profiles', 'Finance\GeneralLedgerController::postingProfiles');
        $routes->post('posting-profiles', 'Finance\GeneralLedgerController::updatePostingProfiles');
        $routes->get('recurring', 'Finance\GeneralLedgerController::recurring');
        $routes->get('entries', 'Finance\GeneralLedgerController::entries');
        $routes->get('entries/export', 'System\CoreAuditExportController::glEntries');
        $routes->get('entries/unbalanced-export', 'System\GlExceptionExportController::unbalanced');
        $routes->get('entries/new', 'Finance\GeneralLedgerController::newEntry');
        $routes->post('entries', 'Finance\GeneralLedgerController::storeEntry');
        $routes->get('entries/(:num)', 'Finance\GeneralLedgerController::showEntry/$1');
        $routes->get('utilities', 'Finance\GeneralLedgerController::utilities');
        $routes->post('utilities/init-defaults', 'Finance\GeneralLedgerController::initDefaults');
        $routes->post('utilities/sync-legacy-coa', 'Finance\GeneralLedgerController::syncLegacyCoa');
        $routes->post('utilities/sync-legacy-books', 'Finance\GeneralLedgerController::syncLegacyBooks');
    });

    $routes->get('period-close', 'Finance\PeriodCloseController::index');
    $routes->get('period-close/export', 'System\PeriodCloseAuditExportController::index');
    $routes->get('period-close/export/(:segment)', 'System\PeriodCloseAuditExportController::index/$1');
    $routes->get('period-close/new', 'Finance\PeriodCloseController::create');
    $routes->get('period-close/new/(:segment)', 'Finance\PeriodCloseController::create/$1');
    $routes->post('period-close', 'Finance\PeriodCloseController::store');
    $routes->get('period-close/(:num)/export', 'System\PeriodCloseAuditExportController::show/$1');
    $routes->get('period-close/(:num)', 'Finance\PeriodCloseController::show/$1');
    $routes->post('period-close/(:num)/reopen', 'Finance\PeriodCloseController::reopen/$1');
    $routes->get('period-close/(:segment)', 'Finance\PeriodCloseController::index/$1');

    $routes->group('cash-bank', static function (RouteCollection $routes): void {
        $routes->get('accounts', 'Finance\CashBankController::accounts');
        $routes->get('cash-entries', 'Finance\CashBankController::entries/cash');
        $routes->get('cash-entries/export', 'System\CashBankAuditExportController::entries/cash');
        $routes->get('cash-entries/new', 'Finance\CashBankController::newEntry/cash');
        $routes->post('cash-entries', 'Finance\CashBankController::storeEntry/cash');
        $routes->get('cash-entries/(:num)', 'Finance\CashBankController::showEntry/cash/$1');
        $routes->get('bank-entries', 'Finance\CashBankController::entries/bank');
        $routes->get('bank-entries/export', 'System\CashBankAuditExportController::entries/bank');
        $routes->get('bank-entries/new', 'Finance\CashBankController::newEntry/bank');
        $routes->post('bank-entries', 'Finance\CashBankController::storeEntry/bank');
        $routes->get('bank-entries/(:num)', 'Finance\CashBankController::showEntry/bank/$1');
        $routes->get('statements', 'Finance\CashBankController::statementImports');
        $routes->get('statements/template', 'Finance\CashBankController::statementTemplate');
        $routes->get('statements/import', 'Finance\CashBankController::statementImportForm');
        $routes->post('statements/import', 'Finance\CashBankController::importStatement');
        $routes->post('statements/(:num)/match', 'Finance\CashBankController::matchStatementImport/$1');
        $routes->get('statements/(:num)/export', 'System\CashBankAuditExportController::statement/$1');
        $routes->get('statements/(:num)', 'Finance\CashBankController::showStatementImport/$1');
        $routes->get('reconciliations', 'Finance\CashBankController::reconciliations');
        $routes->get('reconciliations/new', 'Finance\CashBankController::newReconciliation');
        $routes->post('reconciliations', 'Finance\CashBankController::storeReconciliation');
        $routes->get('reconciliations/(:num)/export', 'System\CashBankAuditExportController::reconciliation/$1');
        $routes->get('reconciliations/(:num)', 'Finance\CashBankController::showReconciliation/$1');
    });

    $routes->group('production', static function (RouteCollection $routes): void {
        $routes->get('imports/(:segment)', 'Production\ProductionImportController::form/$1');
        $routes->get('imports/(:segment)/template', 'Production\ProductionImportController::template/$1');
        $routes->post('imports/(:segment)', 'Production\ProductionImportController::import/$1');
        $routes->get('boms', 'Production\ProductionMasterController::boms');
        $routes->get('boms/new', 'Production\ProductionMasterController::newBom');
        $routes->post('boms', 'Production\ProductionMasterController::storeBom');
        $routes->get('boms/(:num)/edit', 'Production\ProductionEditController::editBom/$1');
        $routes->post('boms/(:num)', 'Production\ProductionEditController::updateBom/$1');
        $routes->get('boms/(:num)', 'Production\ProductionMasterController::showBom/$1');
        $routes->get('work-centers', 'Production\ProductionMasterController::workCenters');
        $routes->get('work-centers/new', 'Production\ProductionMasterController::newWorkCenter');
        $routes->post('work-centers', 'Production\ProductionMasterController::storeWorkCenter');
        $routes->get('work-centers/(:num)/edit', 'Production\ProductionEditController::editWorkCenter/$1');
        $routes->post('work-centers/(:num)', 'Production\ProductionEditController::updateWorkCenter/$1');
        $routes->get('work-centers/(:num)', 'Production\ProductionMasterController::showWorkCenter/$1');
        $routes->get('routings', 'Production\ProductionMasterController::routings');
        $routes->get('routings/new', 'Production\ProductionMasterController::newRouting');
        $routes->post('routings', 'Production\ProductionMasterController::storeRouting');
        $routes->get('routings/(:num)/edit', 'Production\ProductionEditController::editRouting/$1');
        $routes->post('routings/(:num)', 'Production\ProductionEditController::updateRouting/$1');
        $routes->get('routings/(:num)', 'Production\ProductionMasterController::showRouting/$1');
        $routes->get('work-orders', 'Production\WorkOrderController::index');
        $routes->get('work-orders/export', 'System\ProductionAuditExportController::workOrders');
        $routes->get('work-orders/new', 'Production\WorkOrderController::create');
        $routes->post('work-orders', 'Production\WorkOrderController::store');
        $routes->get('work-orders/(:num)/edit', 'Production\ProductionEditController::editWorkOrder/$1');
        $routes->post('work-orders/(:num)', 'Production\ProductionEditController::updateWorkOrder/$1');
        $routes->get('work-orders/(:num)/export', 'System\ProductionAuditExportController::workOrder/$1');
        $routes->get('work-orders/(:num)', 'Production\WorkOrderController::show/$1');
        $routes->post('work-orders/(:num)/allocate', 'Production\WorkOrderController::allocate/$1');
        $routes->post('work-orders/(:num)/issue-materials', 'Production\WorkOrderController::issueMaterials/$1');
        $routes->post('work-orders/(:num)/receive-finished', 'Production\WorkOrderController::receiveFinished/$1');
        $routes->post('work-orders/(:num)/issue-receive', 'Production\WorkOrderController::issueReceive/$1');
    });

    $routes->group('setup', static function (RouteCollection $routes): void {
        $routes->get('document-numbering', 'Setup\DocumentNumberingController::index');
        $routes->post('document-numbering', 'Setup\DocumentNumberingController::save');
        $routes->post('document-numbering/reset-sequence', 'Setup\DocumentNumberingController::resetSequence');
        $routes->get('options/cities', 'Setup\MasterDataController::cityOptions');
        $routes->get('options/locations', 'Setup\MasterDataController::locationOptions');
        foreach (['transaction-codes','prefix-codes','companies','sites','departments','warehouses','locations','countries','provinces','cities','postal-codes','currencies','uoms','uom-conversions','vat','wht','item-vat','address-master','customer-terms','customer-promos','customers','supplier-terms','supplier-promos','suppliers','items','item-locations','batch-masters'] as $resource) {
            $routes->get($resource, 'Setup\MasterDataController::index/' . $resource);
            $routes->get($resource . '/new', 'Setup\MasterDataController::create/' . $resource);
            $routes->post($resource, 'Setup\MasterDataController::store/' . $resource);
            $routes->get($resource . '/(:num)', 'Setup\MasterDataController::show/' . $resource . '/$1');
            $routes->get($resource . '/(:num)/edit', 'Setup\MasterDataController::edit/' . $resource . '/$1');
            $routes->post($resource . '/(:num)', 'Setup\MasterDataController::update/' . $resource . '/$1');
            $routes->post($resource . '/(:num)/delete', 'Setup\MasterDataController::delete/' . $resource . '/$1');
            $routes->get($resource . '/export', 'System\ExcelLiteTransferController::export/' . $resource);
            $routes->get($resource . '/import', 'System\ExcelLiteTransferController::importForm/' . $resource);
            $routes->post($resource . '/import', 'System\ExcelLiteTransferController::import/' . $resource);
            $routes->get($resource . '/template', 'System\ExcelLiteTransferController::template/' . $resource);
        }
        $routes->post('provinces/sync', 'Setup\WilayahSyncController::provinces');
        $routes->post('cities/sync', 'Setup\WilayahSyncController::cities');
    });
});