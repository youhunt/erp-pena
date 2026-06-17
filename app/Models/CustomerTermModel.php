<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerTermModel extends Model
{
    protected $table = 'customer_terms';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'customer', 'customer_name',
        'terms_code', 'terms_name', 'terms_days', 'promo_code',
        'is_active', 'created_by', 'updated_by', 'deleted_by',
    ];
}
