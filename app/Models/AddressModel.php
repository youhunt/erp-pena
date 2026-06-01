<?php

namespace App\Models;

use CodeIgniter\Model;

class AddressModel extends Model
{
    protected $table = 'addresses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'company_id',
        'site_id',
        'address_type',
        'owner_type',
        'owner_code',
        'code',
        'name',
        'country_id',
        'province_id',
        'city_id',
        'postal_code_id',
        'address_line1',
        'address_line2',
        'phone',
        'email',
        'is_active',
        'created_by',
        'updated_by',
    ];
    protected $useTimestamps = true;
}
