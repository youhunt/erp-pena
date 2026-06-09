<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\XlsxSheetReader;
use App\Libraries\XlsxSheetWriter;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class LegacyGlExcelController extends BaseController
{
    private const MAX_UPLOAD_BYTES = 10485760;
    private const SESSION_KEY = 'legacy_gl_excel_previews';

    private array $resources = [
        'glbook' => ['title' => 'GL Book Source', 'table' => 'glbook', 'fields' => ['booktype', 'currency', 'year', 'company', 'site', 'description'], 'unique' => ['booktype', 'company', 'site', 'year']],
        'glbookline' => ['title' => 'GL Book Line Source', 'table' => 'glbookline', 'fields' => ['fromdate', 'todate', 'closed'], 'unique' => ['fromdate', 'todate']],
        'gl' => ['title' => 'GL Source', 'table' => 'gl', 'fields' => ['prefix', 'glno', 'transdate', 'transcode', 'site', 'remarks', 'postdate'], 'unique' => ['glno']],
        'glline' => ['title' => 'GL Line Source', 'table' => 'glline', 'fields' => ['transcode', 'dept', 'employee', 'employeename', 'description', 'currency', 'transamount', 'rate', 'bookamount', 'adjust', 'column', 'approval_1', 'approval_2', 'approval_3', 'approval_4'], 'unique' => ['transcode', 'column', 'description']],
        'glcolumn' => ['title' => 'GL Column Source', 'table' => 'glcolumn', 'fields' => ['booktype', 'company', 'site', 'type', 'remarks'], 'unique' => ['booktype', 'company', 'site']],
        'glcolumnline' => ['title' => 'GL Column Line Source', 'table' => 'glcolumnline', 'fields' => ['code', 'description'], 'key' => 'code'],
        'coa' => ['title' => 'COA Source', 'table' => 'coa', 'fields' => ['booktype', 'company', 'site', 'code', 'remarks'], 'unique' => ['booktype', 'company', 'site', 'code']],
        'coaline' => ['title' => 'COA Line Source', 'table' => 'coaline', 'fields' => ['column', 'description'], 'key' => 'column'],
        'recurring' => ['title' => 'Recurring Source', 'table' => 'recurring', 'fields' => ['prefix', 'recno', 'transdate', 'site', 'remarks', 'postdate', 'active'], 'key' => 'recno'],
        'recurring-line' => ['title' => 'Recurring Line Source', 'table' => 'recurring_line', 'fields' => ['transcode', 'dept', 'employee', 'employeename', 'description', 'currency', 'transamount', 'rate', 'bookamount', 'adjust', 'day', 'date', 'column', 'approval_1', 'approval_2', 'approval_3', 'approval_4', 'active'], 'unique' => ['transcode', 'column', 'description']],
        'general-ledger' => ['title' => 'General Ledger Source', 'table' => 'general_ledger', 'fields' => ['prefix', 'glno', 'transdate', 'transcode', 'site', 'remarks', 'postdate', 'active'], 'key' => 'glno'],
        'general-ledger-line' => ['title' => 'General Ledger Line Source', 'table' => 'general_ledger_line', 'fields' => ['transcode', 'dept', 'employee', 'employeename', 'description', 'currency', 'transamount', 'rate', 'bookamount', 'adjust', 'column', 'approval_1', 'approval_2', 'approval_3', 'approval_4', 'active'], 'unique' => ['transcode', 'column', 'description']],
    ];

    public function index(): string
    {
        $db = Database::connect();
        $resources = [];
        foreach ($this->resources as $key => $config) {
            $resources[$key] = $config + ['count' => $db->tableExists($config['table']) ? $db->table($config['table'])->countAllResults() : 0];
        }

        return view('finance/gl/legacy_excel', [
            'title' => 'Legacy GL Excel',
            'resources' => $resources,
        ]);
    }

    public function template(string $resource)
    {
        $config = $this->config($resource);
        return $this->xlsxResponse($resource . '-template.xlsx', [$config['fields'], $this->sampleRow($config)], $config['title']);
    }

    public function export(string $resource)
    {
        $config = $this->config($resource);
        $db = Database::connect();
        $rows = [$config['fields']];
        if ($db->tableExists($config['table'])) {
            foreach ($db->table($config['table'])->orderBy('id', 'ASC')->get()->getResultArray() as $row) {
                $line = [];
                foreach ($config['fields'] as $field) {
                    $line[] = (string) ($row[$field] ?? '');
                }
                $rows[] = $line;
            }
        }

        return $this->xlsxResponse($resource . '-export.xlsx', $rows, $config['title']);
    }

    public function importForm(string $resource): string
    {
        $config = $this->config($resource);
        return view('system/excel_transfer/import', [
            'title' => 'Import ' . $config['title'] . ' from Excel',
            'resource' => $resource,
            'config' => $config,
            'headers' => $config['fields'],
        ]);
    }

    public function import(string $resource)
    {
        $config = $this->config($resource);
        $file = $this->request->getFile('excel_file');
        $uploadError = $this->validateUpload($file);
        if ($uploadError !== null) {
            return redirect()->back()->with('error', $uploadError);
        }

        try {
            $preview = $this->preview($config, $file->getTempName());
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        $token = bin2hex(random_bytes(16));
        $this->storePreview($token, ['resource' => $resource, 'config' => $config, 'return_to' => 'gl/legacy-excel'] + $preview);

        return view('system/excel_transfer/preview', [
            'title' => 'Preview Import ' . $config['title'],
            'resource' => $resource,
            'config' => $config,
            'headers' => $preview['headers'],
            'summary' => ['total' => $preview['total'], 'valid' => count($preview['valid_rows']), 'error' => count($preview['errors'])],
            'errors' => $preview['errors'],
            'previewRows' => array_slice($preview['raw_valid_rows'], 0, 25),
            'returnTo' => 'gl/legacy-excel',
            'previewToken' => $token,
            'commitUrl' => 'gl/legacy-excel/' . $resource . '/commit',
            'downloadErrorUrl' => 'gl/legacy-excel/' . $resource . '/errors/' . $token,
        ]);
    }

    public function commit(string $resource)
    {
        $this->config($resource);
        $token = (string) $this->request->getPost('preview_token');
        $preview = $this->getPreview($token);
        if ($preview === null || ($preview['resource'] ?? '') !== $resource) {
            return redirect()->to(site_url('gl/legacy-excel'))->with('error', 'Import preview expired. Upload ulang file Excel.');
        }
        if (! empty($preview['errors'])) {
            return redirect()->to(site_url('gl/legacy-excel'))->with('error', 'Tidak bisa post data yang masih punya error.');
        }

        try {
            $result = $this->persistRows($preview['config'], $preview['valid_rows']);
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('gl/legacy-excel'))->with('error', $exception->getMessage());
        }

        $this->clearPreview($token);
        return redirect()->to(site_url('gl/legacy-excel'))->with('message', "Legacy GL Excel import posted. {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.");
    }

    public function downloadErrors(string $resource, string $token)
    {
        $config = $this->config($resource);
        $preview = $this->getPreview($token);
        if ($preview === null || ($preview['resource'] ?? '') !== $resource) {
            return redirect()->to(site_url('gl/legacy-excel'))->with('error', 'Import preview expired.');
        }

        $rows = [array_merge(['excel_row', 'error_message'], $preview['headers'] ?? [])];
        foreach ($preview['errors'] as $error) {
            $raw = $preview['raw_rows_by_number'][$error['row']] ?? [];
            $line = [(string) $error['row'], $error['message']];
            foreach (($preview['headers'] ?? []) as $header) {
                $line[] = (string) ($raw[$header] ?? '');
            }
            $rows[] = $line;
        }

        return $this->xlsxResponse($resource . '-import-errors.xlsx', $rows, $config['title'] . ' Errors');
    }

    private function preview(array $config, string $path): array
    {
        $rows = $this->readRows($path);
        if ($rows === [] || ! isset($rows[0])) {
            throw new RuntimeException('Uploaded file is empty.');
        }

        $headers = array_map(static fn ($value): string => trim((string) $value), $rows[0]);
        foreach ($this->requiredHeaders($config) as $required) {
            if (! in_array($required, $headers, true)) {
                throw new RuntimeException('Header harus memiliki kolom ' . $required . '.');
            }
        }

        $validRows = [];
        $rawValidRows = [];
        $rawRowsByNumber = [];
        $errors = [];
        $total = 0;

        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;
            $data = [];
            foreach ($headers as $i => $header) {
                if ($header !== '' && in_array($header, $config['fields'], true)) {
                    $value = trim((string) ($row[$i] ?? ''));
                    $data[$header] = $value === '' ? null : $value;
                }
            }
            if ($data === []) {
                continue;
            }

            $total++;
            $rawRowsByNumber[$rowNumber] = $data;
            try {
                foreach ($this->requiredHeaders($config) as $required) {
                    if (($data[$required] ?? null) === null || ($data[$required] ?? '') === '') {
                        throw new RuntimeException('Required field ' . $required . ' is empty.');
                    }
                }
                $validRows[] = $data;
                $rawValidRows[] = ['_row_number' => $rowNumber] + $data;
            } catch (RuntimeException $exception) {
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }

        return compact('headers', 'validRows', 'rawValidRows', 'rawRowsByNumber', 'errors', 'total') + [
            'valid_rows' => $validRows,
            'raw_valid_rows' => $rawValidRows,
            'raw_rows_by_number' => $rawRowsByNumber,
        ];
    }

    private function persistRows(array $config, array $rows): array
    {
        $db = Database::connect();
        if (! $db->tableExists($config['table'])) {
            throw new RuntimeException('Table ' . $config['table'] . ' does not exist. Run migration first.');
        }

        $fields = $db->getFieldNames($config['table']);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            foreach ($rows as $data) {
                $payload = [];
                foreach ($data as $field => $value) {
                    if (in_array($field, $fields, true)) {
                        $payload[$field] = $value;
                    }
                }
                if ($payload === []) {
                    $skipped++;
                    continue;
                }
                if (in_array('active', $fields, true) && (($payload['active'] ?? null) === null || ($payload['active'] ?? '') === '')) {
                    $payload['active'] = 1;
                }
                foreach (['updated_at' => $now, 'updated_by' => (string) auth()->id()] as $field => $value) {
                    if (in_array($field, $fields, true)) {
                        $payload[$field] = $value;
                    }
                }

                $existing = $this->findExisting($config, $payload);
                if ($existing !== null) {
                    $db->table($config['table'])->where('id', $existing['id'])->update($payload);
                    $updated++;
                    continue;
                }

                foreach (['created_at' => $now, 'created_by' => (string) auth()->id()] as $field => $value) {
                    if (in_array($field, $fields, true)) {
                        $payload[$field] = $value;
                    }
                }
                $db->table($config['table'])->insert($payload);
                $created++;
            }
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('Import failed. No data was saved.');
        }

        $db->transCommit();
        return compact('created', 'updated', 'skipped');
    }

    private function findExisting(array $config, array $data): ?array
    {
        $db = Database::connect();
        $builder = $db->table($config['table']);
        if (! empty($config['key']) && ! empty($data[$config['key']])) {
            $builder->where($config['key'], $data[$config['key']]);
        } elseif (! empty($config['unique'])) {
            foreach ($config['unique'] as $field) {
                if (! array_key_exists($field, $data)) {
                    return null;
                }
                $builder->where($field, $data[$field]);
            }
        } else {
            return null;
        }

        return $builder->get()->getRowArray() ?: null;
    }

    private function requiredHeaders(array $config): array
    {
        if (! empty($config['key'])) {
            return [(string) $config['key']];
        }
        if (! empty($config['unique'])) {
            return [(string) $config['unique'][0]];
        }
        return [];
    }

    private function readRows(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }
        if (str_starts_with($content, 'PK')) {
            return (new XlsxSheetReader())->readFirstSheet($path);
        }
        if (stripos($content, '<table') !== false) {
            return $this->readHtmlTableRows($content);
        }
        return $this->readTabRows($content);
    }

    private function readTabRows(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($content, "\xEF\xBB\xBF"));
        $rows = [];
        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $rows[] = str_getcsv((string) $line, "\t");
        }
        return $rows;
    }

    private function readHtmlTableRows(string $content): array
    {
        $rows = [];
        if (! preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $content, $trMatches)) {
            return [];
        }
        foreach ($trMatches[1] as $trHtml) {
            preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $trHtml, $tdMatches);
            $row = [];
            foreach ($tdMatches[1] as $cellHtml) {
                $row[] = html_entity_decode(trim(strip_tags($cellHtml)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function validateUpload($file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return 'Please upload a valid Excel file.';
        }
        if ($file->getSize() < 1) {
            return 'Uploaded file is empty.';
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            return 'File is too large. Maximum 10 MB.';
        }
        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'tsv', 'txt'], true)) {
            return 'Gunakan file Excel .xlsx, .xls, .tsv, atau .txt.';
        }
        return null;
    }

    private function xlsxResponse(string $filename, array $rows, string $sheetName)
    {
        $path = (new XlsxSheetWriter())->writeFirstSheet($rows, $sheetName);
        $content = file_get_contents($path) ?: '';
        @unlink($path);
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }

    private function config(string $resource): array
    {
        if (! isset($this->resources[$resource])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $user = auth()->user();
        if (! $user || (! $user->can('finance.gl.manage') && ! $user->can('finance.gl.view') && ! $user->inGroup('superadmin'))) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->resources[$resource];
    }

    private function storePreview(string $token, array $preview): void
    {
        $all = session(self::SESSION_KEY) ?? [];
        $all[$token] = $preview;
        session()->set(self::SESSION_KEY, $all);
    }

    private function getPreview(string $token): ?array
    {
        $all = session(self::SESSION_KEY) ?? [];
        return is_array($all[$token] ?? null) ? $all[$token] : null;
    }

    private function clearPreview(string $token): void
    {
        $all = session(self::SESSION_KEY) ?? [];
        unset($all[$token]);
        session()->set(self::SESSION_KEY, $all);
    }

    private function sampleRow(array $config): array
    {
        return array_map(function (string $header): string {
            return match ($header) {
                'booktype' => '1',
                'currency' => 'IDR',
                'year' => date('Y'),
                'company' => 'PENA',
                'site' => 'HO',
                'description' => 'Example description',
                'fromdate', 'todate', 'transdate', 'postdate' => date('Y-m-d'),
                'closed' => 'N',
                'prefix' => 'GL',
                'glno' => 'GL-' . date('Ymd') . '-001',
                'recno' => 'REC-' . date('Ymd') . '-001',
                'transcode' => 'JV',
                'remarks' => 'Example remarks',
                'dept' => 'ADM',
                'employee' => 'EMP001',
                'employeename' => 'Employee Name',
                'transamount', 'bookamount' => '100000',
                'rate' => '1',
                'adjust' => '0',
                'column' => 'D',
                'code' => '1000',
                'type' => 'D',
                'approval_1', 'approval_2', 'approval_3', 'approval_4' => '',
                'active' => '1',
                default => '',
            };
        }, $config['fields']);
    }
}
