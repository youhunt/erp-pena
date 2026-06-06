<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Models\CashBankAccountModel;
use App\Models\CashBankEntryModel;
use App\Models\ChartAccountModel;
use App\Services\Finance\CashBankService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class CashBankController extends BaseController
{
    public function accounts(): string
    {
        $tenant = new TenantContext(session());
        $model = new CashBankAccountModel();
        $this->scope($model, $tenant);

        return view('finance/cash_bank/accounts', [
            'title' => 'Cash Bank ID',
            'accounts' => $model->orderBy('cash_bank_code', 'ASC')->findAll(200),
        ]);
    }

    public function entries(string $type = 'cash'): string
    {
        $tenant = new TenantContext(session());
        $model = new CashBankEntryModel();
        $this->scope($model, $tenant);
        $type === 'cash'
            ? $model->whereIn('entry_type', ['cash_in', 'cash_out'])
            : $model->whereIn('entry_type', ['bank_in', 'bank_out']);

        return view('finance/cash_bank/entries/index', [
            'title' => $type === 'cash' ? 'Cash Entry' : 'Bank Entry',
            'type' => $type,
            'entries' => $model->orderBy('entry_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function newEntry(string $type = 'cash'): string
    {
        return view('finance/cash_bank/entries/form', [
            'title' => $type === 'cash' ? 'Create Cash Entry' : 'Create Bank Entry',
            'type' => $type,
            'accounts' => $this->cashBankAccounts($type),
            'chartAccounts' => $this->chartAccounts(),
        ]);
    }

    public function storeEntry(string $type = 'cash')
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate([
            'entry_no' => 'required|max_length[60]',
            'entry_date' => 'required|valid_date[Y-m-d]',
            'direction' => 'required|in_list[in,out]',
            'cash_bank_code' => 'required|max_length[60]',
            'amount' => 'required|numeric|greater_than[0]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $direction = (string) $this->request->getPost('direction');
        $entryType = $type . '_' . $direction;

        try {
            $entryId = (new CashBankService())->post([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'entry_no' => trim((string) $this->request->getPost('entry_no')),
                'entry_date' => (string) $this->request->getPost('entry_date'),
                'entry_type' => $entryType,
                'cash_bank_code' => trim((string) $this->request->getPost('cash_bank_code')),
                'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: 'IDR')),
                'amount' => (float) $this->request->getPost('amount'),
                'counter_account_no' => trim((string) $this->request->getPost('counter_account_no')),
                'reference_no' => trim((string) $this->request->getPost('reference_no')),
                'description' => trim((string) $this->request->getPost('description')),
            ], auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries') . '/' . $entryId)->with('message', 'Cash/Bank entry posted.');
    }

    public function showEntry(string $type, int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new CashBankEntryModel();
        $this->scope($model, $tenant);
        $entry = $model->find($id);
        if ($entry === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/cash_bank/entries/show', [
            'title' => 'Cash/Bank Entry Detail',
            'type' => $type,
            'entry' => $entry,
        ]);
    }

    private function cashBankAccounts(string $type): array
    {
        $tenant = new TenantContext(session());
        $model = new CashBankAccountModel();
        $this->scope($model, $tenant);

        return $model->where('account_type', $type)->where('is_active', 1)->orderBy('cash_bank_code', 'ASC')->findAll(100);
    }

    private function chartAccounts(): array
    {
        $tenant = new TenantContext(session());
        $model = new ChartAccountModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }

        return $model->where('is_active', 1)->where('is_postable', 1)->orderBy('account_no', 'ASC')->findAll(300);
    }

    private function scope($model, TenantContext $tenant): void
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }
    }
}
