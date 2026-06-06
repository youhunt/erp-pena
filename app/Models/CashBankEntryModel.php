<?php

namespace App\Models;

use CodeIgniter\Model;

class CashBankEntryModel extends Model
{
    protected $table = 'cash_bank_entries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'cash_bank_account_id', 'entry_no', 'entry_date', 'entry_type',
        'cash_bank_code', 'currency_code', 'amount', 'counter_account_no', 'reference_no',
        'description', 'status', 'gl_entry_id', 'posted_at', 'posted_by', 'created_by', 'updated_by',
    ];
}
