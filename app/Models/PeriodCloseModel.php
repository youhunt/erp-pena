<?php

namespace App\Models;

use CodeIgniter\Model;

class PeriodCloseModel extends Model
{
    protected $table = 'period_closes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'site_scope_id', 'module_code', 'period', 'period_start', 'period_end',
        'status', 'closed_at', 'closed_by', 'reopened_at', 'reopened_by', 'notes',
        'created_by', 'updated_by',
    ];
}
