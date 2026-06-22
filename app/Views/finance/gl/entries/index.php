<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$validation = $validation ?? [
    'entry_count' => 0,
    'line_count' => 0,
    'total_debit' => 0,
    'total_credit' => 0,
    'difference' => 0,
    'unbalanced_count' => 0,
];
$trialBalanceRows = $trialBalanceRows ?? [];
$filters = $filters ?? ['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d'), 'source_module' => ''];
$exportUrl = site_url('gl/entries/export') . '?' . http_build_query($filters);
$trialBalanceExportUrl = site_url('gl/entries/export') . '?' . http_build_query(array_merge($filters, ['report' => 'trial-balance']));
$unbalancedExportUrl = site_url('gl/entries/unbalanced-export') . '?' . http_build_query($filters);
?>
<div class="row">
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Total Debit</p><h4 class="mb-0"><?= esc(number_format((float) $validation['total_debit'], 2)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Total Credit</p><h4 class="mb-0"><?= esc(number_format((float) $validation['total_credit'], 2)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Difference</p><h4 class="mb-0 <?= abs((float) $validation['difference']) > 0.009 ? 'text-danger' : 'text-success' ?>"><?= esc(number_format((float) $validation['difference'], 2)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Unbalanced Entries</p><h4 class="mb-0 <?= (int) $validation['unbalanced_count'] > 0 ? 'text-danger' : 'text-success' ?>"><?= esc((string) $validation['unbalanced_count']) ?></h4></div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">GL Entries</h4>
                <p class="text-muted mb-0">Posted journals with validation summary and trial balance for selected period.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= esc($exportUrl) ?>" class="btn btn-success">
                    <i class="bx bx-download me-1"></i> Export GL Detail XLSX
                </a>
                <a href="<?= esc($trialBalanceExportUrl) ?>" class="btn btn-outline-success">
                    <i class="bx bx-spreadsheet me-1"></i> Export Trial Balance XLSX
                </a>
                <a href="<?= esc($unbalancedExportUrl) ?>" class="btn btn-outline-danger">
                    <i class="bx bx-error-circle me-1"></i> Export Unbalanced XLSX
                </a>
                <a href="<?= site_url('gl/entries/new') ?>" class="btn btn-primary">
                    <i class="bx bx-plus me-1"></i> New GL Entry
                </a>
            </div>
        </div>

        <form method="get" class="row g-2 mb-4">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= esc($filters['date_from']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= esc($filters['date_to']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Source Module</label>
                <select name="source_module" class="form-select">
                    <option value="">All Sources</option>
                    <?php foreach (($sourceModules ?? []) as $module): ?>
                        <option value="<?= esc($module) ?>" <?= ($filters['source_module'] ?? '') === $module ? 'selected' : '' ?>><?= esc($module) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-primary" type="submit"><i class="bx bx-search me-1"></i> Filter</button>
                <a href="<?= site_url('gl/entries') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>

        <?php if (abs((float) $validation['difference']) > 0.009 || (int) $validation['unbalanced_count'] > 0): ?>
            <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>GL tidak balance untuk filter ini. Cek difference dan unbalanced entries sebelum laporan finance dipakai.</div>
                <a href="<?= esc($unbalancedExportUrl) ?>" class="btn btn-sm btn-danger">Export Unbalanced</a>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                GL balance untuk filter ini. Total debit sama dengan total credit.
            </div>
        <?php endif ?>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Journal No</th>
                        <th>Period</th>
                        <th>Source</th>
                        <th>Description</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php $entryDiff = round((float) ($entry['total_debit'] ?? 0) - (float) ($entry['total_credit'] ?? 0), 2); ?>
                    <tr class="<?= abs($entryDiff) > 0.009 ? 'table-danger' : '' ?>">
                        <td><?= esc($entry['journal_date'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= esc($entry['journal_no'] ?? '-') ?></td>
                        <td><?= esc($entry['period'] ?? '-') ?></td>
                        <td>
                            <div><?= esc($entry['source_module'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($entry['source_type'] ?? '') ?></small>
                        </td>
                        <td><?= esc($entry['description'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($entry['total_debit'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($entry['total_credit'] ?? 0), 2)) ?></td>
                        <td><span class="badge bg-<?= abs($entryDiff) > 0.009 ? 'danger' : 'success' ?>"><?= esc($entry['status'] ?? 'posted') ?></span></td>
                        <td class="text-end"><a href="<?= site_url('gl/entries/' . $entry['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i></a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($entries === []): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No GL entry found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">Trial Balance Summary</h4>
                <p class="text-muted mb-0">Summary by account for selected period/source.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="<?= esc($trialBalanceExportUrl) ?>" class="btn btn-sm btn-outline-success">
                    <i class="bx bx-download me-1"></i> Export XLSX
                </a>
                <span class="badge bg-light text-dark"><?= esc((string) count($trialBalanceRows)) ?> account(s)</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account No</th>
                        <th>Account Name</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trialBalanceRows as $row): ?>
                        <?php $balance = (float) ($row['balance'] ?? 0); ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($row['account_no'] ?? '-') ?></td>
                            <td><?= esc($row['account_name'] ?? '-') ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($row['debit'] ?? 0), 2)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($row['credit'] ?? 0), 2)) ?></td>
                            <td class="text-end fw-semibold <?= $balance < 0 ? 'text-danger' : '' ?>"><?= esc(number_format($balance, 2)) ?></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($trialBalanceRows === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No trial balance row found for selected filter.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
