<?php

namespace App\Models;

use CodeIgniter\Model;

class CurrencyModel extends Model
{
    protected $table = 'currencies';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['code', 'name', 'parent_id', 'rounding', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
