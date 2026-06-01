<?php

namespace App\Models;

use CodeIgniter\Model;

class SiteModel extends Model
{
    protected $table = 'sites';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'company_id',
        'code',
        'name',
        'address',
        'is_active',
        'created_by',
        'updated_by',
    ];
    protected $useTimestamps = true;
}
