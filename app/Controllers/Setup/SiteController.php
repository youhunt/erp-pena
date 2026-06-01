<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Models\SiteModel;

class SiteController extends BaseController
{
    public function index(): string
    {
        return view('setup/sites/index', [
            'title' => 'Sites',
            'sites' => (new SiteModel())->orderBy('code', 'ASC')->findAll(),
        ]);
    }
}
