<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryMovementDocumentModel extends Model
{
    protected $table = 'inventory_movement_documents';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'document_no', 'document_date', 'document_type', 'direction', 'status',
        'warehouse_id', 'location_id', 'total_qty', 'total_value', 'notes', 'posted_at', 'posted_by',
        'reversed_at', 'reversed_by', 'reversal_reason', 'reversal_document_id',
        'created_by', 'updated_by',
    ];
}
