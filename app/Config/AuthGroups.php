<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'viewer';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Admin',
            'description' => 'Full platform and all-company administration.',
        ],
        'company_admin' => [
            'title'       => 'Company Admin',
            'description' => 'Company-level administration and master data.',
        ],
        'finance' => [
            'title'       => 'Finance',
            'description' => 'Finance, GL, AR, cash bank, costing, and fixed asset users.',
        ],
        'sales' => [
            'title'       => 'Sales',
            'description' => 'Sales, customer, order, delivery, and invoice users.',
        ],
        'purchase' => [
            'title'       => 'Purchase',
            'description' => 'Supplier, purchase order, receiving, and vendor invoice users.',
        ],
        'inventory' => [
            'title'       => 'Inventory',
            'description' => 'Item, warehouse, stock movement, and adjustment users.',
        ],
        'production' => [
            'title'       => 'Production',
            'description' => 'BOM, routing, work center, and work order users.',
        ],
        'viewer' => [
            'title'       => 'Viewer',
            'description' => 'Read-only ERP access based on assigned company and site.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'dashboard.view'          => 'View ERP dashboard',
        'setup.master.view'       => 'View setup and master data',
        'setup.master.manage'     => 'Create, update, and delete setup master data',
        'users.view'              => 'View users, roles, and permissions',
        'users.manage'            => 'Manage users, roles, permissions, companies, and sites',
        'sales.customer.view'     => 'View customer master',
        'sales.customer.manage'   => 'Manage customer master',
        'sales.order.view'        => 'View sales orders',
        'sales.order.create'      => 'Create sales orders',
        'sales.order.approve'     => 'Approve sales orders',
        'purchase.supplier.view'  => 'View supplier master',
        'purchase.supplier.manage'=> 'Manage supplier master',
        'purchase.po.view'        => 'View purchase orders',
        'purchase.po.create'      => 'Create purchase orders',
        'purchase.po.approve'     => 'Approve purchase orders',
        'inventory.item.view'     => 'View item master',
        'inventory.item.manage'   => 'Manage item master',
        'inventory.stock.view'    => 'View warehouse stock',
        'inventory.movement.post' => 'Post inventory movements',
        'finance.gl.view'         => 'View GL and finance transactions',
        'finance.gl.post'         => 'Post GL journals',
        'finance.invoice.view'    => 'View invoices and receivables',
        'finance.invoice.manage'  => 'Manage invoices and receivables',
        'production.view'         => 'View production master and transactions',
        'production.manage'       => 'Manage production master and transactions',
        'pos.view'                => 'View POS master and transactions',
        'pos.manage'              => 'Manage POS master and transactions',
        'planning.view'           => 'View planning transactions',
        'planning.manage'         => 'Manage planning transactions',
        'finance.ap.view'         => 'View accounts payable transactions',
        'finance.ap.manage'       => 'Manage accounts payable transactions',
        'finance.ar.view'         => 'View accounts receivable transactions',
        'finance.ar.manage'       => 'Manage accounts receivable transactions',
        'costing.view'            => 'View costing master and transactions',
        'costing.manage'          => 'Manage costing master and transactions',
        'cashbank.view'           => 'View cash bank master and transactions',
        'cashbank.manage'         => 'Manage cash bank master and transactions',
        'fixedasset.view'         => 'View fixed asset master and transactions',
        'fixedasset.manage'       => 'Manage fixed asset master and transactions',
        'ai.document.upload'      => 'Upload ERP documents for OCR',
        'ai.document.review'      => 'Review OCR and AI extraction result',
        'ai.document.convert'     => 'Convert reviewed document to ERP transaction',
        'audit.logs.view'         => 'View audit logs',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'superadmin' => [
            '*',
        ],
        'company_admin' => [
            'dashboard.view',
            'setup.master.*',
            'users.*',
            'sales.*',
            'purchase.*',
            'inventory.*',
            'finance.*',
            'production.*',
            'pos.*',
            'planning.*',
            'costing.*',
            'cashbank.*',
            'fixedasset.*',
            'ai.document.*',
            'audit.logs.view',
        ],
        'finance' => [
            'dashboard.view',
            'setup.master.view',
            'finance.*',
            'finance.ap.*',
            'finance.ar.*',
            'costing.*',
            'cashbank.*',
            'fixedasset.*',
            'sales.order.view',
            'purchase.po.view',
            'ai.document.review',
            'audit.logs.view',
        ],
        'sales' => [
            'dashboard.view',
            'sales.*',
            'inventory.stock.view',
            'finance.invoice.view',
            'pos.view',
            'ai.document.upload',
            'ai.document.review',
        ],
        'purchase' => [
            'dashboard.view',
            'purchase.*',
            'inventory.stock.view',
            'ai.document.upload',
            'ai.document.review',
            'ai.document.convert',
        ],
        'inventory' => [
            'dashboard.view',
            'inventory.*',
            'purchase.po.view',
            'sales.order.view',
            'ai.document.review',
        ],
        'production' => [
            'dashboard.view',
            'production.*',
            'planning.*',
            'inventory.stock.view',
        ],
        'viewer' => [
            'dashboard.view',
            'setup.master.view',
            'sales.customer.view',
            'sales.order.view',
            'purchase.supplier.view',
            'purchase.po.view',
            'inventory.item.view',
            'inventory.stock.view',
            'finance.gl.view',
            'finance.invoice.view',
            'finance.ap.view',
            'finance.ar.view',
            'production.view',
            'pos.view',
            'planning.view',
            'costing.view',
            'cashbank.view',
            'fixedasset.view',
        ],
    ];
}
