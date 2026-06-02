<?php

namespace App\Controllers\Ai;

use App\Controllers\BaseController;

class SampleDocumentController extends BaseController
{
    public function purchaseOrder(): string
    {
        return view('ai/samples/purchase_order', ['title' => 'Sample Purchase Order']);
    }

    public function salesOrder(): string
    {
        return view('ai/samples/sales_order', ['title' => 'Sample Sales Order']);
    }
}
