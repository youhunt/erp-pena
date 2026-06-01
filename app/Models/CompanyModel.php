<?php

namespace App\Models;

use CodeIgniter\Model;

class CompanyModel extends Model
{
    protected $table = 'companies';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'code',
        'name',
        'legal_name',
        'tax_number',
        'base_currency',
        'address',
        'is_active',
        'created_by',
        'updated_by',
    ];
    protected $useTimestamps = true;
}
