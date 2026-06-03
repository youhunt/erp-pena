<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'company_id', 'site_id', 'code', 'name', 'terms_code', 'currency_code', 'tax_number', 'address', 'phone', 'email', 'is_active',
        'company', 'site', 'customer', 'customern', 'customerr', 'contactnar', 'description', 'shipwhs',
        'officeaddre', 'officecity', 'officeprovir', 'officecount', 'officeposta', 'officeconta', 'officephon', 'officehp',
        'taxcode', 'taxnumber', 'vat', 'limitamound', 'limitqty', 'terms', 'limitdays', 'salescode', 'salesname',
        'bank1', 'bankaccou', 'bank2', 'bankaccou2',
        'billingcust', 'billingtoc', 'billingaddre', 'billingcity', 'billingprovi', 'billingcoun', 'billingposta', 'billingconta', 'billingphon', 'billinghp',
        'mailcustom', 'mailcode', 'mailaddres', 'mailcity', 'mailprovin', 'mailcountr', 'mailpostal', 'mailcontac', 'mailphone', 'mailhp',
        'shiptocust', 'shiptocode', 'shiptoaddr', 'shiptocity', 'shiptoprovi', 'shiptocour', 'shiptopost', 'shiptocont', 'shiptophon', 'shiptohp',
        'active', 'created_by', 'updated_by', 'deleted_by',
    ];
}
