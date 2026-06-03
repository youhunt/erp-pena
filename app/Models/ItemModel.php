<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemModel extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site',
        'code', 'name',
        'item_code', 'item_name', 'item_coded', 'item_named',
        'shelf_life', 'stockuom', 'purchaseuom', 'sellinguom', 'stockwhs',
        'item_price', 'purchasep', 'sellingprice', 'vat',
        'item_length', 'item_width', 'item_heigh', 'item_diam',
        'item_lengt', 'item_widthh', 'item_heigh_uom', 'item_diam_uom',
        'out_length', 'out_width', 'out_height', 'out_diame',
        'out_lengt', 'out_widthh', 'out_height_uom', 'out_diame_uom',
        'item_group', 'item_subg', 'item_class', 'item_subc',
        'item_type', 'item_subty', 'item_atribu',
        'active', 'is_active', 'created_by', 'updated_by', 'deleted_by',
    ];
}
