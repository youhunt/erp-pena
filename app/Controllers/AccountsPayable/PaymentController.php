<?php

namespace App\Controllers\AccountsPayable;

use App\Controllers\BaseController;
use App\Models\ApPayableModel;
use App\Models\ApPaymentModel;
use App\Models\CashBankAccountModel;
use App\Services\Finance\SettlementService;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;
use RuntimeException;

class PaymentController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new ApPaymentModel();
        $this->scope($model, $tenant);

        return view('accounts_payable/payments/index', [
            'title' => 'A/P Payments',
            'payments' => $model->orderBy('payment_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function createFromInvoice(int $invoiceId): string
    {
        $tenant = new TenantContext(session());
        $payable = $this->payableFromInvoice($tenant, $invoiceId);
        if ($payable === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $status = (string) ($payable['status'] ?? '');
        if (! in_array($status, ['open', 'partial'], true)) {
            return view('errors/html/error_404', ['message' => 'Only open or partial A/P invoice can be paid. Current status: ' . ($status !== '' ? $status : 'unknown') . '.']);
        }
        if ((float) ($payable['outstanding_amount'] ?? 0) <= 0) {
            return view('errors/html/error_404', ['message' => 'A/P invoice has no outstanding amount to pay.']);
        }

        return view('accounts_payable/payments/form', [
            'title' => 'Post A/P Payment',
            'payable' => $payable,
            'cashBankAccounts' => $this->cashBankAccounts($tenant),
            'suggestedPaymentNo' => $this->previewDocumentNumber('APP'),
        ]);
    }

    public function storeFromInvoice(int $invoiceId)
    {
        $tenant = new TenantContext(session());
        $payable = $this->payableFromInvoice($tenant, $invoiceId);
        if ($payable === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $status = (string) ($payable['status'] ?? '');
        if (! in_array($status, ['open', 'partial'], true)) {
            return redirect()->back()->with('error', 'Only open or partial A/P invoice can be paid. Current status: ' . ($status !== '' ? $status : 'unknown') . '.');
        }

        if (! $this->validate([
            'payment_no' => 'permit_empty|max_length[60]',
            'payment_date' => 'required|valid_date[Y-m-d]',
            'payment_amount' => 'required',
            'payment_method' => 'required|max_length[40]',
            'cash_bank_code' => 'required|max_length[80]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $paymentDate = (string) $this->request->getPost('payment_date');
            $paymentNo = trim((string) $this->request->getPost('payment_no'));
            if ($paymentNo === '') {
                $paymentNo = $this->issueDocumentNumber('APP', $paymentDate, (int) $payable['company_id'], $payable['site_id'] ?? null);
            }

            $paymentId = (new SettlementService())->postApPayment([
                'company_id' => $payable['company_id'],
                'site_id' => $payable['site_id'] ?? null,
                'ap_payable_id' => $payable['id'],
                'payment_no' => $paymentNo,
                'payment_date' => $paymentDate,
                'payment_amount' => $this->toNumber($this->request->getPost('payment_amount')),
                'payment_method' => trim((string) $this->request->getPost('payment_method')),
                'cash_bank_code' => trim((string) $this->request->getPost('cash_bank_code')),
                'reference_no' => trim((string) $this->request->getPost('reference_no')),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/ap/payments/' . $paymentId)->with('message', 'A/P payment posted and invoice balance updated.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new ApPaymentModel();
        $this->scope($model, $tenant);
        $payment = $model->find($id);
        if ($payment === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('accounts_payable/payments/show', [
            'title' => 'A/P Payment Detail',
            'payment' => $payment,
        ]);
    }

    public function cancel(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new ApPaymentModel();
        $this->scope($model, $tenant);
        if ($model->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->validate([
            'cancel_reason' => 'permit_empty|max_length[500]',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            (new SettlementService())->cancelApPayment($id, auth()->id(), trim((string) $this->request->getPost('cancel_reason')) ?: null);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/ap/payments/' . $id)->with('message', 'A/P payment cancelled and invoice balance reopened.');
    }

    private function payableFromInvoice(TenantContext $tenant, int $invoiceId): ?array
    {
        $model = new ApPayableModel();
        $this->scope($model, $tenant);
        return $model->where('purchase_invoice_id', $invoiceId)->first();
    }

    private function scope($model, TenantContext $tenant): void
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
    }

    private function cashBankAccounts(TenantContext $tenant): array
    {
        $model = new CashBankAccountModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->groupStart()
                ->where('site_id', $tenant->activeSiteId())
                ->orWhere('site_id', null)
                ->groupEnd();
        }

        return $model->where('is_active', 1)
            ->orderBy('account_type', 'ASC')
            ->orderBy('cash_bank_code', 'ASC')
            ->findAll();
    }

    private function previewDocumentNumber(string $transactionCode): string
    {
        try {
            return (new DocumentNumberService())->preview($transactionCode, new DateTimeImmutable(), [
                'prefix' => $transactionCode,
                'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
                'reset_period' => 'monthly',
                'padding' => 5,
            ]);
        } catch (\Throwable) {
            return '';
        }
    }

    private function issueDocumentNumber(string $transactionCode, string $date, int $companyId, mixed $siteId): string
    {
        return (new DocumentNumberService())->next($transactionCode, new DateTimeImmutable($date), [
            'company_id' => $companyId,
            'site_id' => ! empty($siteId) ? (int) $siteId : 0,
            'prefix' => $transactionCode,
            'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
            'reset_period' => 'monthly',
            'padding' => 5,
        ]);
    }

    private function toNumber(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        return (float) $value;
    }
}
