<?php

namespace App\Models;

use CodeIgniter\Model;

class BankReconciliationModel extends Model
{
    protected $table = 'bank_reconciliations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'cash_bank_account_id', 'cash_bank_code', 'reconcile_no',
        'statement_date', 'statement_ref', 'book_balance', 'statement_balance',
        'reconciled_amount', 'difference_amount', 'entry_count', 'status', 'notes',
        'posted_at', 'posted_by', 'created_by', 'updated_by',
    ];
}
