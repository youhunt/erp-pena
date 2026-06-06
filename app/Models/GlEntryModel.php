<?php

namespace App\Models;

use CodeIgniter\Model;

class GlEntryModel extends Model
{
    protected $table = 'gl_entries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'gl_book_id', 'journal_no', 'journal_date', 'period',
        'source_module', 'source_type', 'source_id', 'source_no', 'description',
        'currency_code', 'exchange_rate', 'total_debit', 'total_credit', 'status',
        'posted_at', 'posted_by', 'created_by', 'updated_by',
    ];
}
