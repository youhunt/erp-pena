<?php

namespace App\Models;

use CodeIgniter\Model;

class WithholdingTaxRateModel extends Model
{
    protected $table = 'wht_rates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site',
        'vat', 'description',
        'vatpctg1', 'vatpctg2', 'vatpctg3', 'vatpctg4', 'vatpctg5',
        'gl',
        // legacy compatibility fields
        'code', 'name', 'rate',
        'is_active', 'created_by', 'updated_by',
    ];
}
