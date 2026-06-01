<?php

namespace App\Models;

use CodeIgniter\Model;

class UomConversionModel extends Model
{
    protected $table = 'uom_conversions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'from_uom_id', 'to_uom_id', 'multiplier', 'divider', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
