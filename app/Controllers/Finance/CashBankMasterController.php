<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;

class CashBankMasterController extends BaseController
{
    public function accounts(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        if ((string) $this->request->getGet('action') === 'save') {
            $branch = trim((string) $this->request->getGet('bank_branch'));
            $code = trim((string) $this->request->getGet('bank_code'));
            $curr = trim((string) $this->request->getGet('currency_code'));
            $name = trim((string) $this->request->getGet('bank_name'));
            $account = trim((string) $this->request->getGet('bank_account'));
            if ($branch === '' || $code === '' || $curr === '' || $name === '' || $account === '') {
                return redirect()->to('/cash-bank/accounts')->with('error', 'Bank Branch, Bank Code, Currency, Bank Name, and Bank Account are required.');
            }

            $payload = [
                'company_id' => $tenant->activeCompanyId(),
                'site_id' => $tenant->activeSiteId(),
                'bank_branch' => $branch,
                'bank_code' => $code,
                'cash_bank_code' => $branch,
                'cash_bank_name' => $name,
                'account_type' => 'bank',
                'currency_code' => $curr,
                'bank_account' => $account,
                'pic' => trim((string) $this->request->getGet('pic')),
                'phone' => trim((string) $this->request->getGet('phone')),
                'address' => trim((string) $this->request->getGet('address')),
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $existing = $db->table('cash_bank_accounts')
                ->where('company_id', $tenant->activeCompanyId())
                ->where('bank_branch', $branch)
                ->where('deleted_at', null)
                ->get(1)->getRowArray();
            if ($existing !== null) {
                $db->table('cash_bank_accounts')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['current_balance'] = 0;
                $payload['opening_balance'] = 0;
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('cash_bank_accounts')->insert($payload);
            }
            return redirect()->to('/cash-bank/accounts')->with('message', 'Cash Bank ID saved.');
        }

        return view('finance/cash_bank/masters/accounts', [
            'title' => 'Cash Bank ID',
            'accounts' => $this->rows('cash_bank_accounts', 'cash_bank_code'),
            'currencies' => $this->currencyOptions(),
        ]);
    }

    public function currencies(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        if ((string) $this->request->getGet('action') === 'save') {
            $code = strtoupper(trim((string) $this->request->getGet('code')));
            $name = trim((string) $this->request->getGet('name'));
            if ($code === '' || $name === '') {
                return redirect()->to('/cash-bank/currencies')->with('error', 'Currency Code and Name are required.');
            }

            $payload = [
                'company_id' => $tenant->activeCompanyId(),
                'code' => $code,
                'name' => $name,
                'rounding' => (float) ($this->request->getGet('rounding') ?: 0),
                'is_active' => 1,
                'updated_by' => auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $existing = $db->table('currencies')->where('company_id', $tenant->activeCompanyId())->where('code', $code)->where('deleted_at', null)->get(1)->getRowArray();
            if ($existing !== null) {
                $db->table('currencies')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['created_by'] = auth()->id();
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('currencies')->insert($payload);
            }
            return redirect()->to('/cash-bank/currencies')->with('message', 'Currency saved.');
        }

        return view('finance/cash_bank/masters/currencies', [
            'title' => 'Currency',
            'rows' => $this->rows('currencies', 'code'),
        ]);
    }

    public function employees(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        if ((string) $this->request->getGet('action') === 'save') {
            $code = trim((string) $this->request->getGet('employee_code'));
            $name = trim((string) $this->request->getGet('name'));
            if ($code === '' || $name === '') {
                return redirect()->to('/cash-bank/employees')->with('error', 'Employee ID and Employee Name are required.');
            }

            $payload = [
                'company_id' => $tenant->activeCompanyId(),
                'site_id' => $tenant->activeSiteId(),
                'employee_code' => $code,
                'site_code' => trim((string) $this->request->getGet('site_code')),
                'department_code' => trim((string) $this->request->getGet('department_code')),
                'name' => $name,
                'description' => trim((string) $this->request->getGet('description')),
                'is_active' => 1,
                'updated_by' => auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $existing = $db->table('employees')->where('company_id', $tenant->activeCompanyId())->where('employee_code', $code)->where('deleted_at', null)->get(1)->getRowArray();
            if ($existing !== null) {
                $db->table('employees')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['created_by'] = auth()->id();
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('employees')->insert($payload);
            }
            return redirect()->to('/cash-bank/employees')->with('message', 'Employee saved.');
        }

        return view('finance/cash_bank/masters/employees', [
            'title' => 'Employee ID',
            'rows' => $this->rows('employees', 'employee_code'),
            'sites' => $this->masterRows('sites'),
            'departments' => $this->masterRows('departments'),
        ]);
    }

    public function rates(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        if ((string) $this->request->getGet('action') === 'save') {
            $type = trim((string) $this->request->getGet('rate_type'));
            $from = strtoupper(trim((string) $this->request->getGet('from_currency')));
            $to = strtoupper(trim((string) $this->request->getGet('to_currency')));
            $date = trim((string) $this->request->getGet('rate_date'));
            $amount = (float) $this->request->getGet('amount');
            if ($type === '' || $from === '' || $to === '' || $date === '' || $amount <= 0) {
                return redirect()->to('/cash-bank/rates')->with('error', 'Rate Type, From Currency, To Currency, Date, and Amount are required.');
            }

            $payload = [
                'company_id' => $tenant->activeCompanyId(),
                'rate_type' => $type,
                'from_currency' => $from,
                'to_currency' => $to,
                'rate_date' => $date,
                'amount' => $amount,
                'is_active' => 1,
                'updated_by' => auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $existing = $db->table('currency_rates')
                ->where('company_id', $tenant->activeCompanyId())
                ->where('rate_type', $type)
                ->where('from_currency', $from)
                ->where('to_currency', $to)
                ->where('rate_date', $date)
                ->where('deleted_at', null)
                ->get(1)->getRowArray();
            if ($existing !== null) {
                $db->table('currency_rates')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['created_by'] = auth()->id();
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('currency_rates')->insert($payload);
            }
            return redirect()->to('/cash-bank/rates')->with('message', 'Rate saved.');
        }

        return view('finance/cash_bank/masters/rates', [
            'title' => 'Rate Master',
            'rows' => $this->rows('currency_rates', 'rate_date', 'DESC'),
            'currencies' => $this->currencyOptions(),
        ]);
    }

    private function rows(string $table, string $orderBy, string $direction = 'ASC'): array
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return [];
        }
        $tenant = new TenantContext(session());
        $builder = $db->table($table);
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $builder->groupStart()->where('company_id', $tenant->activeCompanyId())->orWhere('company_id', null)->groupEnd();
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $builder->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        return $builder->orderBy($orderBy, $direction)->get(500)->getResultArray();
    }

    private function currencyOptions(): array
    {
        $rows = $this->rows('currencies', 'code');
        return $rows !== [] ? $rows : [['code' => 'IDR', 'name' => 'Indonesian Rupiah']];
    }

    private function masterRows(string $table): array
    {
        return $this->rows($table, Database::connect()->fieldExists('code', $table) ? 'code' : 'id');
    }
}
