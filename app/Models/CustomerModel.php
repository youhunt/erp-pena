<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'site_id', 'code', 'name', 'terms_code', 'currency_code', 'tax_number', 'address', 'phone', 'email', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
