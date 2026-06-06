<?php

namespace App\Models;

use CodeIgniter\Model;

class CashBankAccountModel extends Model
{
    protected $table = 'cash_bank_accounts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'cash_bank_code', 'cash_bank_name', 'account_type',
        'currency_code', 'gl_account_no', 'opening_balance', 'current_balance', 'is_active',
    ];
}
