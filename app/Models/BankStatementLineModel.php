<?php

namespace App\Models;

use CodeIgniter\Model;

class BankStatementLineModel extends Model
{
    protected $table = 'bank_statement_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'bank_statement_import_id', 'company_id', 'site_id', 'cash_bank_account_id',
        'cash_bank_code', 'line_no', 'statement_date', 'value_date', 'reference_no',
        'description', 'debit_amount', 'credit_amount', 'signed_amount', 'balance_amount',
        'currency_code', 'match_status', 'cash_bank_entry_id', 'raw_payload',
    ];
}
