<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ErpMenuStructureSeeder extends Seeder
{
    private string $now;

    public function run(): void
    {
        $this->call(SyncErpMenuSeeder::class);
    }

    private function structure(): array
    {
        return [
            [
                'label' => 'Setup',
                'icon' => 'bx-cog',
                'children' => [
                    ['label' => 'Transaction Code', 'route' => 'setup/transaction-codes'],
                    ['label' => 'Company', 'route' => 'setup/companies'],
                    ['label' => 'Site', 'route' => 'setup/sites'],
                    ['label' => 'Department', 'route' => 'setup/departments'],
                    ['label' => 'Warehouse', 'route' => 'setup/warehouses'],
                    ['label' => 'Location', 'route' => 'setup/locations'],
                    ['label' => 'Country', 'route' => 'setup/countries'],
                    ['label' => 'Province', 'route' => 'setup/provinces'],
                    ['label' => 'City', 'route' => 'setup/cities'],
                    ['label' => 'Postal Code', 'route' => 'setup/postal-codes'],
                    ['label' => 'Unit of Measure', 'route' => 'setup/uoms'],
                    ['label' => 'UoM Conversion', 'route' => 'setup/uom-conversions'],
                    ['label' => 'VAT', 'route' => 'setup/vat'],
                    ['label' => 'Item VAT', 'route' => 'setup/item-vat'],
                    ['label' => 'Address Master', 'route' => 'setup/address-master'],
                ],
            ],
            [
                'label' => 'POS',
                'icon' => 'bx-store',
                'children' => [
                    ['label' => 'POS Master', 'route' => 'modules/pos-master'],
                    ['label' => 'POS System', 'route' => 'modules/pos-system'],
                ],
            ],
            [
                'label' => 'System',
                'icon' => 'bx-data',
                'children' => [
                    ['label' => 'User Management', 'route' => 'admin/users', 'permission' => 'users.view'],
                    ['label' => 'Roles & Permissions', 'route' => 'admin/roles', 'permission' => 'roles.view'],
                    ['label' => 'Data Import Export', 'route' => 'system/data-import', 'permission' => 'setup.master.view'],
                    ['label' => 'Excel Import Export', 'route' => 'system/excel-transfer', 'permission' => 'setup.master.view'],
                    ['label' => 'Audit Logs', 'route' => 'audit-logs', 'permission' => 'audit.logs.view'],
                ],
            ],
            [
                'label' => 'Sales',
                'icon' => 'bx-cart',
                'children' => [
                    ['label' => 'Customer Master', 'route' => 'setup/customers'],
                    ['label' => 'Customer Terms', 'route' => 'setup/customer-terms'],
                    ['label' => 'Customer Promo', 'route' => 'setup/customer-promos'],
                    ['label' => 'Customer Address', 'route' => 'setup/address-master'],
                    ['label' => 'Sales Order', 'route' => 'sales/orders'],
                    ['label' => 'Allocation Order', 'route' => 'sales/allocations'],
                    ['label' => 'Delivery Order', 'route' => 'sales/deliveries'],
                    ['label' => 'Sales Period Close', 'route' => 'period-close/sales'],
                ],
            ],
            [
                'label' => 'Purchase',
                'icon' => 'bx-purchase-tag',
                'children' => [
                    ['label' => 'Supplier Master', 'route' => 'setup/suppliers'],
                    ['label' => 'Supplier Terms', 'route' => 'setup/supplier-terms'],
                    ['label' => 'Supplier Promo', 'route' => 'setup/supplier-promos'],
                    ['label' => 'Supplier Address', 'route' => 'setup/address-master'],
                    ['label' => 'Purchase Order', 'route' => 'purchase/orders'],
                    ['label' => 'Purchase Intransit', 'route' => 'modules/purchase-intransit'],
                    ['label' => 'Inventory Purchase Receipt', 'route' => 'purchase/receipts'],
                    ['label' => 'Cost Purchase Receipt', 'route' => 'modules/cost-purchase-receipt'],
                    ['label' => 'Purchase Period Close', 'route' => 'period-close/purchase'],
                ],
            ],
            [
                'label' => 'Inventory',
                'icon' => 'bx-package',
                'children' => [
                    ['label' => 'Item Master', 'route' => 'setup/items'],
                    ['label' => 'Item Location', 'route' => 'setup/item-locations'],
                    ['label' => 'Item UoM Conversion', 'route' => 'setup/uom-conversions'],
                    ['label' => 'Batch Master', 'route' => 'modules/batch-master'],
                    ['label' => 'Inventory In Out', 'route' => 'inventory/in-out'],
                    ['label' => 'Inventory Transfer', 'route' => 'inventory/transfers'],
                    ['label' => 'Inventory Stock Opname', 'route' => 'inventory/stock-opname'],
                    ['label' => 'Inventory Period Close', 'route' => 'period-close/inventory'],
                ],
            ],
            [
                'label' => 'Planning',
                'icon' => 'bx-calendar',
                'children' => [
                    ['label' => 'Forecast', 'route' => 'modules/forecast'],
                    ['label' => 'Planned Released', 'route' => 'modules/planned-released'],
                    ['label' => 'MPS', 'route' => 'modules/mps'],
                    ['label' => 'MRP', 'route' => 'modules/mrp'],
                ],
            ],
            [
                'label' => 'Production',
                'icon' => 'bx-wrench',
                'children' => [
                    ['label' => 'BOM', 'route' => 'production/boms'],
                    ['label' => 'Work Center', 'route' => 'production/work-centers'],
                    ['label' => 'Routing', 'route' => 'production/routings'],
                    ['label' => 'Work Order', 'route' => 'production/work-orders'],
                    ['label' => 'Allocate Work Order', 'route' => 'production/work-orders'],
                    ['label' => 'Work Order In', 'route' => 'production/work-orders'],
                    ['label' => 'Work Order Out', 'route' => 'production/work-orders'],
                    ['label' => 'Work Order In Out', 'route' => 'production/work-orders'],
                    ['label' => 'Work Order Labor', 'route' => 'modules/work-order-labor'],
                    ['label' => 'Production Period Close', 'route' => 'period-close/production'],
                ],
            ],
            [
                'label' => 'Accounts Payable',
                'icon' => 'bx-receipt',
                'children' => [
                    ['label' => 'Accounts Payable', 'route' => 'ap/purchase-invoices'],
                    ['label' => 'Manual A/P Invoice', 'route' => 'ap/manual-invoices/new'],
                    ['label' => 'Purchase Invoice', 'route' => 'ap/purchase-invoices'],
                    ['label' => 'Inventory Purchase Invoice', 'route' => 'ap/purchase-invoices'],
                    ['label' => 'Advanced A/P Invoice', 'route' => 'modules/advanced-ap-invoice'],
                    ['label' => 'Payment Invoice', 'route' => 'ap/payments'],
                    ['label' => 'A/P Period Close', 'route' => 'period-close/ap'],
                ],
            ],
            [
                'label' => 'Accounts Receivable',
                'icon' => 'bx-money',
                'children' => [
                    ['label' => 'Accounts Receivable', 'route' => 'ar/sales-invoices'],
                    ['label' => 'Manual A/R Invoice', 'route' => 'ar/manual-invoices/new'],
                    ['label' => 'Proforma Invoice', 'route' => 'modules/proforma-invoice'],
                    ['label' => 'Sales Invoice', 'route' => 'ar/sales-invoices'],
                    ['label' => 'Inventory Sales Invoice', 'route' => 'ar/sales-invoices'],
                    ['label' => 'Advanced A/R Receipt', 'route' => 'modules/advanced-ar-receipt'],
                    ['label' => 'Payment Receipt', 'route' => 'ar/receipts'],
                    ['label' => 'A/R Period Close', 'route' => 'period-close/ar'],
                ],
            ],
            [
                'label' => 'Costing',
                'icon' => 'bx-calculator',
                'children' => [
                    ['label' => 'Cost Type', 'route' => 'modules/cost-type'],
                    ['label' => 'Item Cost', 'route' => 'modules/item-cost'],
                    ['label' => 'Calculate Cost', 'route' => 'modules/calculate-cost'],
                ],
            ],
            [
                'label' => 'Cash Bank',
                'icon' => 'bx-bank',
                'children' => [
                    ['label' => 'Cash Bank ID', 'route' => 'cash-bank/accounts'],
                    ['label' => 'Currency', 'route' => 'setup/currencies'],
                    ['label' => 'Employee ID', 'route' => 'modules/employee-id'],
                    ['label' => 'Rate Master', 'route' => 'modules/rate-master'],
                    ['label' => 'Cash Entry', 'route' => 'cash-bank/cash-entries'],
                    ['label' => 'Bank Entry', 'route' => 'cash-bank/bank-entries'],
                    ['label' => 'Bank Reconcile', 'route' => 'cash-bank/reconciliations'],
                ],
            ],
            [
                'label' => 'GL',
                'icon' => 'bx-book',
                'children' => [
                    ['label' => 'GL Book', 'route' => 'modules/gl-book'],
                    ['label' => 'GL Column', 'route' => 'modules/gl-column'],
                    ['label' => 'Account No.', 'route' => 'gl/chart-of-accounts'],
                    ['label' => 'Chart of Account', 'route' => 'gl/chart-of-accounts'],
                    ['label' => 'Recurring', 'route' => 'modules/recurring'],
                    ['label' => 'GL Entry', 'route' => 'gl/entries'],
                    ['label' => 'Recurring Posting', 'route' => 'modules/recurring-posting'],
                    ['label' => 'GL Period Close', 'route' => 'period-close/gl'],
                ],
            ],
            [
                'label' => 'FA',
                'icon' => 'bx-building-house',
                'children' => [
                    ['label' => 'Asset ID', 'route' => 'modules/asset-id'],
                    ['label' => 'Asset Depreciation', 'route' => 'modules/asset-depreciation'],
                    ['label' => 'Asset Period Close', 'route' => 'period-close/fa'],
                ],
            ],
        ];
    }

    private function menuItem(?int $parentId, string $label, string $route, ?string $icon, ?string $permission, int $sort): int
    {
        $builder = $this->db->table('menu_items')
            ->where('label', $label);

        if ($parentId === null) {
            $builder->where('parent_id', null);
        } else {
            $builder->where('parent_id', $parentId);
        }

        $row = $builder->get()->getRowArray();
        $data = [
            'parent_id' => $parentId,
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'permission' => $permission,
            'sort_order' => $sort,
            'is_active' => 1,
            'updated_at' => $this->now,
        ];

        $data = $this->filterFields($data);

        if ($row !== null) {
            $this->db->table('menu_items')->where('id', $row['id'])->update($data);
            return (int) $row['id'];
        }

        $this->db->table('menu_items')->insert($data + $this->filterFields(['created_at' => $this->now]));
        return (int) $this->db->insertID();
    }

    private function filterFields(array $data): array
    {
        return array_filter(
            $data,
            fn (string $field): bool => $this->db->fieldExists($field, 'menu_items'),
            ARRAY_FILTER_USE_KEY
        );
    }
}
