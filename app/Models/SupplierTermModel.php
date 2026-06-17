<?php

namespace App\Models;

use CodeIgniter\Model;

class SupplierTermModel extends Model
{
    protected $table = 'supplier_terms';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'supplier', 'supplier_name',
        'terms_code', 'terms_name', 'terms_days', 'promo_code',
        'is_active', 'created_by', 'updated_by', 'deleted_by',
    ];
}
