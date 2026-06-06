<?php

namespace App\Models;

use CodeIgniter\Model;

class ChartAccountModel extends Model
{
    protected $table = 'chart_accounts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'account_no', 'account_name', 'account_type', 'normal_balance',
        'parent_account_no', 'is_postable', 'is_active',
    ];
}
