<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Database\Seeds\FinanceGlSeeder;
use App\Models\ChartAccountModel;
use App\Models\GlBookModel;
use App\Models\GlEntryLineModel;
use App\Models\GlEntryModel;
use App\Models\GlPostingProfileModel;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\LegacyGlBridgeService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
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

    public function books(): string
    {
        $tenant = new TenantContext(session());
        $model = new GlBookModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }

        return view('finance/gl/books', [
            'title' => 'GL Book',
            'books' => $model->orderBy('is_default', 'DESC')->orderBy('book_code', 'ASC')->findAll(100),
            'legacyBooks' => $this->legacyRows('glbook', 100),
        ]);
    }

    public function columns(): string
    {
        return view('finance/gl/columns', [
            'title' => 'GL Column',
            'columns' => $this->legacyRows('glcolumn', 100),
            'lines' => $this->legacyRows('glcolumnline', 200),
        ]);
    }

    public function legacyCoa(): string
    {
        return view('finance/gl/legacy_coa', [
            'title' => 'Legacy COA Source',
            'coaRows' => $this->legacyRows('coa', 300),
            'coaLines' => $this->legacyRows('coaline', 300),
        ]);
    }

    public function recurring(): string
    {
        return view('finance/gl/recurring', [
            'title' => 'Recurring Journal',
            'recurringRows' => $this->legacyRows('recurring', 100),
            'recurringLines' => $this->legacyRows('recurring_line', 300),
        ]);
    }

    public function utilities(): string
    {
        $db = Database::connect();

        return view('finance/gl/utilities', [
            'title' => 'GL Utilities',
            'hasCoa' => $db->tableExists('coa'),
            'hasGlBook' => $db->tableExists('glbook'),
            'legacyCoaCount' => $db->tableExists('coa') ? $db->table('coa')->countAllResults() : 0,
            'legacyBookCount' => $db->tableExists('glbook') ? $db->table('glbook')->countAllResults() : 0,
            'modernCoaCount' => $db->tableExists('chart_accounts') ? $db->table('chart_accounts')->countAllResults() : 0,
            'modernBookCount' => $db->tableExists('gl_books') ? $db->table('gl_books')->countAllResults() : 0,
        ]);
    }

    public function initDefaults()
    {
        Database::seeder()->call(FinanceGlSeeder::class);

        return redirect()->to(site_url('gl/utilities'))->with('message', 'Finance GL default data initialized.');
    }

    public function syncLegacyCoa()
    {
        try {
            $result = (new LegacyGlBridgeService())->syncCoaToChartAccounts();
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('gl/utilities'))->with('error', $exception->getMessage());
        }

        return redirect()->to(site_url('gl/utilities'))->with('message', "Legacy COA synced. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    public function syncLegacyBooks()
    {
        try {
            $result = (new LegacyGlBridgeService())->syncGlBookToModern();
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('gl/utilities'))->with('error', $exception->getMessage());
        }

        return redirect()->to(site_url('gl/utilities'))->with('message', "Legacy GL Book synced. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
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

    public function postingProfiles(): string
    {
        $tenant = new TenantContext(session());
        $model = new GlPostingProfileModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }

        return view('finance/gl/posting_profiles/index', [
            'title' => 'Posting Profile',
            'profiles' => $model->orderBy('module_code', 'ASC')->orderBy('posting_key', 'ASC')->findAll(200),
            'accounts' => $this->accounts(),
        ]);
    }

    public function updatePostingProfiles()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->with('error', 'Active company is required.');
        }

        $accountNos = (array) $this->request->getPost('account_no');
        $model = new GlPostingProfileModel();
        foreach ($accountNos as $profileId => $accountNo) {
            $profile = $model->where('company_id', $companyId)->find((int) $profileId);
            if ($profile === null) {
                continue;
            }
            $model->update((int) $profile['id'], [
                'account_no' => trim((string) $accountNo),
                'updated_by' => auth()->id(),
            ]);
        }

        return redirect()->to('/gl/posting-profiles')->with('message', 'Posting profiles updated.');
    }

    public function newEntry(): string
    {
        return view('finance/gl/entries/form', [
            'title' => 'Create GL Entry',
            'books' => $this->booksList(),
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

    private function booksList(): array
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

    private function legacyRows(string $table, int $limit): array
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return [];
        }

        return $db->table($table)->limit($limit)->get()->getResultArray();
    }
}
