<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerPromotionModel extends Model
{
    protected $table = 'customer_promotions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'promo_code', 'promo_description',
        'customer', 'customer_name', 'item_parent', 'item_parent_name', 'line_no', 'promo_type',
        'from_qty', 'to_qty', 'uom', 'promo_price', 'pct', 'disc_amount', 'free_item',
        'free_item_name', 'free_qty', 'active_date', 'active_hour', 'inactive_date', 'inactive_hour',
        'is_active', 'created_by', 'updated_by', 'deleted_by',
    ];
}
