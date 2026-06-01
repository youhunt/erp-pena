<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Models\CompanyModel;

class CompanyController extends BaseController
{
    public function index(): string
    {
        return view('setup/companies/index', [
            'title' => 'Companies',
            'companies' => (new CompanyModel())->orderBy('code', 'ASC')->findAll(),
        ]);
    }
}
