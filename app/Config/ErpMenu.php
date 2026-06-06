<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class ErpMenu extends BaseConfig
{
    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return [
            $this->item('Dashboard', 'dashboard', 'bx-home-circle', 'dashboard.view', 10),
            $this->group('Setup', 'bx-cog', 20, [
                $this->leaf('Transaction Code', 'setup/transaction-codes', 'setup.master.view'),
                $this->leaf('Company', 'setup/companies', 'setup.master.view'),
                $this->leaf('Site', 'setup/sites', 'setup.master.view'),
                $this->leaf('Department', 'setup/departments', 'setup.master.view'),
                $this->leaf('Warehouse', 'setup/warehouses', 'setup.master.view'),
                $this->leaf('Location', 'setup/locations', 'setup.master.view'),
                $this->leaf('Country', 'setup/countries', 'setup.master.view'),
                $this->leaf('Province', 'setup/provinces', 'setup.master.view'),
                $this->leaf('City', 'setup/cities', 'setup.master.view'),
                $this->leaf('Postal Code', 'setup/postal-codes', 'setup.master.view'),
                $this->leaf('Unit of Measure', 'setup/uoms', 'setup.master.view'),
                $this->leaf('UoM Conversion', 'setup/uom-conversions', 'setup.master.view'),
                $this->leaf('VAT', 'setup/vat', 'setup.master.view'),
                $this->leaf('Item VAT', 'setup/item-vat', 'setup.master.view'),
                $this->leaf('Address Master', 'setup/address-master', 'setup.master.view'),
            ]),
            $this->group('POS', 'bx-store-alt', 30, [
                $this->leaf('POS Master', $this->placeholderRoute('POS Master'), 'pos.view'),
                $this->leaf('POS System', $this->placeholderRoute('POS System'), 'pos.view'),
            ]),
            $this->group('Sales', 'bx-cart', 40, [
                $this->leaf('Customer Master', 'setup/customers', 'sales.customer.view'),
                $this->leaf('Customer Terms', 'setup/customer-terms', 'sales.customer.view'),
                $this->leaf('Customer Promo', 'setup/customer-promos', 'sales.customer.view'),
                $this->leaf('Customer Address', $this->placeholderRoute('Customer Address'), 'sales.customer.view'),
                $this->leaf('Sales Order', 'sales/orders', 'sales.order.view'),
                $this->leaf('Allocation Order', 'sales/allocations', 'sales.order.view'),
                $this->leaf('Delivery Order', 'sales/deliveries', 'sales.order.view'),
                $this->leaf('Sales Period Close', $this->placeholderRoute('Sales Period Close'), 'sales.order.view'),
            ]),
            $this->group('Purchase', 'bx-shopping-bag', 50, [
                $this->leaf('Supplier Master', 'setup/suppliers', 'purchase.supplier.view'),
                $this->leaf('Supplier Terms', 'setup/supplier-terms', 'purchase.supplier.view'),
                $this->leaf('Supplier Promo', 'setup/supplier-promos', 'purchase.supplier.view'),
                $this->leaf('Supplier Address', $this->placeholderRoute('Supplier Address'), 'purchase.supplier.view'),
                $this->leaf('Purchase Order', 'purchase/orders', 'purchase.po.view'),
                $this->leaf('Purchase Intransit', $this->placeholderRoute('Purchase Intransit'), 'purchase.po.view'),
                $this->leaf('Inventory Purchase Receipt', $this->placeholderRoute('Inventory Purchase Receipt'), 'purchase.po.view'),
                $this->leaf('Cost Purchase Receipt', $this->placeholderRoute('Cost Purchase Receipt'), 'purchase.po.view'),
                $this->leaf('Purchase Period Close', $this->placeholderRoute('Purchase Period Close'), 'purchase.po.view'),
            ]),
            $this->group('Inventory', 'bx-package', 60, [
                $this->leaf('Item Master', 'setup/items', 'inventory.item.view'),
                $this->leaf('Item UoM Conversion', $this->placeholderRoute('Item UoM Conversion'), 'inventory.item.view'),
                $this->leaf('Batch Master', $this->placeholderRoute('Batch Master'), 'inventory.item.view'),
                $this->leaf('Inventory In Out', 'inventory/in-out', 'inventory.movement.post'),
                $this->leaf('Inventory Transfer', 'inventory/transfers', 'inventory.movement.post'),
                $this->leaf('Inventory Stock Opname', 'inventory/stock-opname', 'inventory.movement.post'),
                $this->leaf('Inventory Period Close', $this->placeholderRoute('Inventory Period Close'), 'inventory.stock.view'),
            ]),
            $this->group('Planning', 'bx-calendar', 70, [
                $this->leaf('Forecast', $this->placeholderRoute('Forecast'), 'planning.view'),
                $this->leaf('Planned Released', $this->placeholderRoute('Planned Released'), 'planning.view'),
                $this->leaf('MPS', $this->placeholderRoute('MPS'), 'planning.view'),
                $this->leaf('MRP', $this->placeholderRoute('MRP'), 'planning.view'),
            ]),
            $this->group('Production', 'bx-factory', 80, [
                $this->leaf('BOM', 'production/boms', 'production.view'),
                $this->leaf('Work Center', 'production/work-centers', 'production.view'),
                $this->leaf('Routing', 'production/routings', 'production.view'),
                $this->leaf('Work Order', 'production/work-orders', 'production.view'),
                $this->leaf('Allocate Work Order', 'production/work-orders', 'production.view'),
                $this->leaf('Work Order In', 'production/work-orders', 'production.view'),
                $this->leaf('Work Order Out', 'production/work-orders', 'production.view'),
                $this->leaf('Work Order In Out', 'production/work-orders', 'production.view'),
                $this->leaf('Work Order Labor', $this->placeholderRoute('Work Order Labor'), 'production.view'),
                $this->leaf('Production Period Close', $this->placeholderRoute('Production Period Close'), 'production.view'),
            ]),
            $this->group('Accounts Payable', 'bx-receipt', 90, [
                $this->leaf('Accounts Payable', $this->placeholderRoute('Accounts Payable'), 'finance.ap.view'),
                $this->leaf('Manual A/P Invoice', $this->placeholderRoute('Manual A/P Invoice'), 'finance.ap.view'),
                $this->leaf('Purchase Invoice', 'ap/purchase-invoices', 'finance.ap.view'),
                $this->leaf('Inventory Purchase Invoice', $this->placeholderRoute('Inventory Purchase Invoice'), 'finance.ap.view'),
                $this->leaf('Advanced A/P Invoice', $this->placeholderRoute('Advanced A/P Invoice'), 'finance.ap.view'),
                $this->leaf('Payment Invoice', 'ap/payments', 'finance.ap.view'),
                $this->leaf('A/P Period Close', $this->placeholderRoute('A/P Period Close'), 'finance.ap.view'),
            ]),
            $this->group('Accounts Receivable', 'bx-credit-card', 100, [
                $this->leaf('Accounts Receivable', $this->placeholderRoute('Accounts Receivable'), 'finance.ar.view'),
                $this->leaf('Manual A/R Invoice', $this->placeholderRoute('Manual A/R Invoice'), 'finance.ar.view'),
                $this->leaf('Proforma Invoice', $this->placeholderRoute('Proforma Invoice'), 'finance.ar.view'),
                $this->leaf('Sales Invoice', 'ar/sales-invoices', 'finance.ar.view'),
                $this->leaf('Inventory Sales Invoice', $this->placeholderRoute('Inventory Sales Invoice'), 'finance.ar.view'),
                $this->leaf('Advanced A/R Receipt', $this->placeholderRoute('Advanced A/R Receipt'), 'finance.ar.view'),
                $this->leaf('Payment Receipt', 'ar/receipts', 'finance.ar.view'),
                $this->leaf('A/R Period Close', $this->placeholderRoute('A/R Period Close'), 'finance.ar.view'),
            ]),
            $this->group('Costing', 'bx-calculator', 110, [
                $this->leaf('Cost Type', $this->placeholderRoute('Cost Type'), 'costing.view'),
                $this->leaf('Item Cost', $this->placeholderRoute('Item Cost'), 'costing.view'),
                $this->leaf('Calculate Cost', $this->placeholderRoute('Calculate Cost'), 'costing.view'),
            ]),
            $this->group('Cash Bank', 'bx-wallet', 120, [
                $this->leaf('Cash Bank ID', $this->placeholderRoute('Cash Bank ID'), 'cashbank.view'),
                $this->leaf('Currency', $this->placeholderRoute('Currency'), 'cashbank.view'),
                $this->leaf('Employee ID', $this->placeholderRoute('Employee ID'), 'cashbank.view'),
                $this->leaf('Rate Master', $this->placeholderRoute('Rate Master'), 'cashbank.view'),
                $this->leaf('Cash Entry', $this->placeholderRoute('Cash Entry'), 'cashbank.view'),
                $this->leaf('Bank Entry', $this->placeholderRoute('Bank Entry'), 'cashbank.view'),
                $this->leaf('Bank Reconcile', $this->placeholderRoute('Bank Reconcile'), 'cashbank.view'),
            ]),
            $this->group('GL', 'bx-book', 130, [
                $this->leaf('GL Book', $this->placeholderRoute('GL Book'), 'finance.gl.view'),
                $this->leaf('GL Column', $this->placeholderRoute('GL Column'), 'finance.gl.view'),
                $this->leaf('Account No.', $this->placeholderRoute('Account No'), 'finance.gl.view'),
                $this->leaf('Chart of Account', $this->placeholderRoute('Chart of Account'), 'finance.gl.view'),
                $this->leaf('Recurring', $this->placeholderRoute('Recurring'), 'finance.gl.view'),
                $this->leaf('GL Entry', $this->placeholderRoute('GL Entry'), 'finance.gl.view'),
                $this->leaf('Recurring Posting', $this->placeholderRoute('Recurring Posting'), 'finance.gl.view'),
                $this->leaf('GL Period Close', $this->placeholderRoute('GL Period Close'), 'finance.gl.view'),
            ]),
            $this->group('FA', 'bx-building-house', 140, [
                $this->leaf('Asset ID', $this->placeholderRoute('Asset ID'), 'fixedasset.view'),
                $this->leaf('Asset Depreciation', $this->placeholderRoute('Asset Depreciation'), 'fixedasset.view'),
                $this->leaf('Asset Period Close', $this->placeholderRoute('Asset Period Close'), 'fixedasset.view'),
            ]),
            $this->item('AI Documents', 'ai-documents', 'bx-scan', 'ai.document.review', 150),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function childrenOf(string $label): array
    {
        foreach ($this->items() as $item) {
            if ($item['label'] === $label) {
                return $item['children'] ?? [];
            }
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array<string, mixed>
     */
    private function group(string $label, string $icon, int $sort, array $children): array
    {
        return $this->item($label, '#', $icon, null, $sort, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function leaf(string $label, string $route, ?string $permission): array
    {
        return $this->item($label, $route, null, $permission);
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array<string, mixed>
     */
    private function item(string $label, string $route, ?string $icon, ?string $permission, ?int $sort = null, array $children = []): array
    {
        return [
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'permission' => $permission,
            'sort_order' => $sort,
            'children' => $children,
        ];
    }

    private function placeholderRoute(string $label): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $label));

        return 'modules/' . trim($slug, '-');
    }
}
