<?php

namespace App\Models;

use CodeIgniter\Model;

class UomModel extends Model
{
    protected $table = 'uoms';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'code', 'name', 'rate', 'description', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
