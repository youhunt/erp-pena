<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionBomLineModel extends Model
{
    protected $table = 'production_bom_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'production_bom_id', 'child_no', 'child_item_id', 'child_item_code',
        'child_item_name', 'component_type', 'qty_used', 'uom_code', 'factor',
        'description', 'active_date', 'inactive_date',
    ];
}
