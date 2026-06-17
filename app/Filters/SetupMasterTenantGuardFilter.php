<?php

namespace App\Filters;

use App\Services\TenantContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class SetupMasterTenantGuardFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return null;
        }

        $path = trim((string) $request->getUri()->getPath(), '/');
        if (! str_starts_with($path, 'setup/')) {
            return null;
        }

        if (str_contains($path, '/sync') || str_contains($path, '/import') || str_contains($path, '/delete')) {
            return null;
        }

        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return null;
        }

        $db = Database::connect();
        $companyCode = (string) ($db->table('companies')->select('code')->where('id', $companyId)->get()->getRowArray()['code'] ?? '');
        $siteId = $tenant->activeSiteId();
        $siteCode = '';
        if ($siteId !== null && $siteId > 0) {
            $siteCode = (string) ($db->table('sites')->select('code')->where('id', $siteId)->get()->getRowArray()['code'] ?? '');
        }

        $post = $request->getPost() ?? [];
        $post['company'] = $companyCode;
        $post['site'] = $siteCode;

        if (method_exists($request, 'setGlobal')) {
            $request->setGlobal('post', $post);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (strtoupper($request->getMethod()) !== 'GET') {
            return null;
        }

        $path = trim((string) $request->getUri()->getPath(), '/');
        if (! preg_match('~^setup/(customer-terms|supplier-terms)/(new|\d+/edit)$~', $path, $matches)) {
            return null;
        }

        $body = (string) $response->getBody();
        if ($body === '' || ! str_contains($body, 'for="terms_code"')) {
            return null;
        }

        $resource = $matches[1];
        $partnerType = $resource === 'customer-terms' ? 'customer' : 'supplier';
        if (str_contains($body, 'name="' . $partnerType . '"')) {
            return null;
        }

        $selectedCode = $this->selectedPartnerCodeFromPath($resource, $matches[2]);
        $partnerHtml = $this->partnerFieldHtml($partnerType, $selectedCode);
        $pattern = '~(\s*<div class="col-lg-6 mb-3">\s*<label class="form-label" for="terms_code")~';
        $body = preg_replace($pattern, "\n" . $partnerHtml . '$1', $body, 1) ?? $body;

        $response->setBody($body);

        return null;
    }

    private function selectedPartnerCodeFromPath(string $resource, string $mode): string
    {
        if (! str_ends_with($mode, '/edit')) {
            return '';
        }

        $id = (int) str_replace('/edit', '', $mode);
        if ($id < 1) {
            return '';
        }

        $table = $resource === 'customer-terms' ? 'customer_terms' : 'supplier_terms';
        $field = $resource === 'customer-terms' ? 'customer' : 'supplier';
        $row = Database::connect()->table($table)->select($field)->where('id', $id)->get()->getRowArray();

        return (string) ($row[$field] ?? '');
    }

    private function partnerFieldHtml(string $partnerType, string $selectedCode): string
    {
        $isCustomer = $partnerType === 'customer';
        $label = $isCustomer ? 'Customer' : 'Supplier';
        $nameField = $isCustomer ? 'customer_name' : 'supplier_name';
        $options = $this->partnerOptions($partnerType);

        $html = '<div class="col-lg-6 mb-3">';
        $html .= '<label class="form-label" for="' . esc($partnerType, 'attr') . '">' . esc($label) . '</label>';
        $html .= '<select class="form-select select2" id="' . esc($partnerType, 'attr') . '" name="' . esc($partnerType, 'attr') . '" data-placeholder="All ' . esc($label, 'attr') . '">';
        $html .= '<option value="">All ' . esc($label) . '</option>';
        foreach ($options as $code => $optionLabel) {
            $selected = (string) $code === (string) $selectedCode ? ' selected' : '';
            $html .= '<option value="' . esc((string) $code, 'attr') . '"' . $selected . '>' . esc($optionLabel) . '</option>';
        }
        $html .= '</select>';
        $html .= '<div class="form-text">Kosongkan kalau terms berlaku umum untuk semua ' . strtolower($label) . '.</div>';
        $html .= '</div>';
        $html .= '<input type="hidden" id="' . esc($nameField, 'attr') . '" name="' . esc($nameField, 'attr') . '" value="">';
        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded",function(){';
        $html .= 'var s=document.getElementById("' . $partnerType . '");var t=document.getElementById("' . $nameField . '");';
        $html .= 'if(!s||!t){return;}function sync(){var o=s.options[s.selectedIndex];var label=o?o.textContent.trim():"";var p=label.split(" - ");t.value=p.length>1?p.slice(1).join(" - ").trim():"";}';
        $html .= 's.addEventListener("change",sync);sync();';
        $html .= '});';
        $html .= '</script>';

        return $html;
    }

    private function partnerOptions(string $partnerType): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $isCustomer = $partnerType === 'customer';
        $table = $isCustomer ? 'customers' : 'suppliers';
        $codeField = $isCustomer ? 'customer' : 'supplier';
        $nameField = $isCustomer ? 'customern' : 'supplierna';

        $builder = $db->table($table)->select($codeField . ', ' . $nameField);
        if ($db->fieldExists('company_id', $table) && $tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($db->fieldExists('site_id', $table) && $tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('active', $table)) {
            $builder->where('active', 1);
        }

        $options = [];
        foreach ($builder->orderBy($codeField, 'ASC')->get()->getResultArray() as $row) {
            $code = (string) ($row[$codeField] ?? '');
            if ($code === '') {
                continue;
            }
            $options[$code] = trim($code . ' - ' . (string) ($row[$nameField] ?? ''));
        }

        return $options;
    }
}
