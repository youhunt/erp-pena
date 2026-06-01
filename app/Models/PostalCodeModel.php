<?php

namespace App\Models;

use CodeIgniter\Model;

class PostalCodeModel extends Model
{
    protected $table = 'postal_codes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['country_id', 'province_id', 'city_id', 'code', 'name', 'district', 'village', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
