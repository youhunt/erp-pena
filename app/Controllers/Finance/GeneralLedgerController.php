<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Models\ChartAccountModel;
use App\Models\GlBookModel;
use App\Models\GlEntryLineModel;
use App\Models\GlEntryModel;
use App\Services\Finance\GeneralLedgerService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class GeneralLedgerController extends BaseController
{
    public function chartAccounts(): string
    {
        $tenant = new TenantContext(session());
        $accounts = new ChartAccountModel();
        if ($tenant->activeCompanyId() !== null) {
            $accounts->where('company_id', $tenant->activeCompanyId());
        }

        return view('finance/gl/chart_accounts', [
            'title' => 'Chart of Account',
            'accounts' => $accounts->orderBy('account_no', 'ASC')->findAll(300),
        ]);
    }

    public function entries(): string
    {
        $tenant = new TenantContext(session());
        $entries = new GlEntryModel();
        if ($tenant->activeCompanyId() !== null) {
            $entries->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $entries->where('site_id', $tenant->activeSiteId());
        }

        return view('finance/gl/entries/index', [
            'title' => 'GL Entries',
            'entries' => $entries->orderBy('journal_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function newEntry(): string
    {
        return view('finance/gl/entries/form', [
            'title' => 'Create GL Entry',
            'books' => $this->books(),
            'accounts' => $this->accounts(),
        ]);
    }

    public function storeEntry()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate([
            'journal_no' => 'required|max_length[60]',
            'journal_date' => 'required|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $entryId = (new GeneralLedgerService())->post([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'gl_book_id' => $this->nullableInt($this->request->getPost('gl_book_id')),
                'journal_no' => trim((string) $this->request->getPost('journal_no')),
                'journal_date' => (string) $this->request->getPost('journal_date'),
                'description' => trim((string) $this->request->getPost('description')),
                'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: 'IDR')),
                'exchange_rate' => (float) ($this->request->getPost('exchange_rate') ?: 1),
            ], $this->postedLines(), auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/gl/entries/' . $entryId)->with('message', 'GL entry posted.');
    }

    public function showEntry(int $id): string
    {
        $tenant = new TenantContext(session());
        $entry = (new GlEntryModel())->find($id);
        if ($entry === null || (int) $entry['company_id'] !== (int) $tenant->activeCompanyId()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/gl/entries/show', [
            'title' => 'GL Entry Detail',
            'entry' => $entry,
            'lines' => (new GlEntryLineModel())->where('gl_entry_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    private function accounts(): array
    {
        $tenant = new TenantContext(session());
        $model = new ChartAccountModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }

        return $model->where('is_active', 1)->where('is_postable', 1)->orderBy('account_no', 'ASC')->findAll(300);
    }

    private function books(): array
    {
        $tenant = new TenantContext(session());
        $model = new GlBookModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }

        return $model->where('is_active', 1)->orderBy('is_default', 'DESC')->orderBy('book_code', 'ASC')->findAll(50);
    }

    private function postedLines(): array
    {
        $accounts = (array) $this->request->getPost('account_no');
        $descriptions = (array) $this->request->getPost('line_description');
        $debits = (array) $this->request->getPost('debit');
        $credits = (array) $this->request->getPost('credit');
        $lines = [];

        foreach ($accounts as $i => $accountNo) {
            $lines[] = [
                'account_no' => trim((string) $accountNo),
                'description' => trim((string) ($descriptions[$i] ?? '')),
                'debit' => (float) ($debits[$i] ?? 0),
                'credit' => (float) ($credits[$i] ?? 0),
            ];
        }

        return $lines;
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
