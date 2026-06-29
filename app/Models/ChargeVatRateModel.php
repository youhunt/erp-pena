<?php

namespace App\Models;

use CodeIgniter\Model;

class ChargeVatRateModel extends Model
{
    protected $table = 'charge_vat_rates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site',
        'vat', 'description',
        'vatpctg1', 'vatpctg2', 'vatpctg3', 'vatpctg4', 'vatpctg5',
        'gl',
        'is_active', 'created_by', 'updated_by',
    ];
}
