<?php

namespace App\Models;

use CodeIgniter\Model;

class SetupCodeModel extends Model
{
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'code', 'name', 'rate', 'description', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
