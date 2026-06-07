<?php

namespace App\Models;

use CodeIgniter\Model;

class GlPostingProfileModel extends Model
{
    protected $table = 'gl_posting_profiles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'module_code', 'posting_key', 'account_no', 'description',
        'is_active', 'created_by', 'updated_by',
    ];
}
