<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuItemModel extends Model
{
    protected $table = 'menu_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'parent_id',
        'label',
        'route',
        'icon',
        'permission',
        'sort_order',
        'is_active',
    ];
    protected $useTimestamps = true;
}
