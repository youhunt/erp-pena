<?php

namespace App\Models;

use CodeIgniter\Model;

class GlEntryLineModel extends Model
{
    protected $table = 'gl_entry_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'gl_entry_id', 'company_id', 'site_id', 'line_no', 'account_id', 'account_no',
        'account_name', 'description', 'debit', 'credit', 'created_at',
    ];
}
