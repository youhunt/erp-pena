<?php

namespace App\Models;

class TransactionCodeModel extends SetupCodeModel
{
    protected $table = 'transaction_codes';
    protected $allowedFields = [
        'company_id',
        'code',
        'name',
        'prefix',
        'format',
        'reset_period',
        'padding',
        'rate',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];
}
