<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentUploadModel extends Model
{
    protected $table = 'document_uploads';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'company_id',
        'site_id',
        'uploaded_by',
        'original_name',
        'stored_path',
        'mime_type',
        'file_size',
        'sha256_hash',
        'document_type',
        'status',
        'duplicate_of_id',
    ];
    protected $useTimestamps = true;
}
