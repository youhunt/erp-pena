<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemVatRateModel extends Model
{
    protected $table = 'item_vat_rates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'item_id', 'vat_rate_id', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
