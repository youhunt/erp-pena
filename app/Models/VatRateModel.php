<?php

namespace App\Models;

use CodeIgniter\Model;

class VatRateModel extends Model
{
    protected $table = 'vat_rates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site',
        'vat', 'description',
        'vatpctg', 'scpctg', 'otherpctg', 'optionalpctg',
        'gl',
        // legacy compatibility fields
        'code', 'name', 'rate',
        'is_active', 'created_by', 'updated_by',
    ];
}
