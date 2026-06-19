<?php

namespace App\Models;

use CodeIgniter\Model;

class BankStatementImportModel extends Model
{
    protected $table = 'bank_statement_imports';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'cash_bank_account_id', 'cash_bank_code', 'statement_ref',
        'statement_date', 'source_filename', 'opening_balance', 'closing_balance',
        'debit_total', 'credit_total', 'net_amount', 'line_count', 'matched_count',
        'status', 'notes', 'imported_at', 'imported_by', 'created_by', 'updated_by',
    ];
}
