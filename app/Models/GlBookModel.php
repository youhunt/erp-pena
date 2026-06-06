<?php

namespace App\Models;

use CodeIgniter\Model;

class GlBookModel extends Model
{
    protected $table = 'gl_books';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'book_code', 'book_name', 'currency_code', 'is_default', 'is_active',
    ];
}
