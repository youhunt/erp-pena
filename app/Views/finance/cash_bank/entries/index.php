<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$basePath = 'cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries');
$exportUrl = site_url($basePath . '/export');
$newUrl = site_url($basePath . '/new');
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                <p class="text-muted mb-0">Posted <?= esc($type) ?> transactions with currency, rate, base amount, and linked GL posting.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= esc($exportUrl) ?>" class="btn btn-outline-success"><i class="bx bx-download me-1"></i> Export XLSX</a>
                <a href="<?= esc($newUrl) ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Entry</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Entry No</th>
                        <th>Type</th>
                        <th>Cash/Bank</th>
                        <th>Currency</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Base Amount</th>
                        <th>Reference</th>
                        <th>GL</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php
                        $currency = (string) ($entry['currency_code'] ?? 'IDR');
                        $baseCurrency = (string) ($entry['base_currency'] ?? 'IDR');
                        $amount = (float) ($entry['amount'] ?? 0);
                        $rate = (float) ($entry['exchange_rate'] ?? 1);
                        $baseAmount = array_key_exists('base_amount', $entry) ? (float) $entry['base_amount'] : $amount * ($rate > 0 ? $rate : 1);
                    ?>
                    <tr>
                        <td><?= esc($entry['entry_date'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= esc($entry['entry_no'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= str_ends_with((string) ($entry['entry_type'] ?? ''), '_in') ? 'success' : 'danger' ?>"><?= esc($entry['entry_type'] ?? '-') ?></span></td>
                        <td><?= esc($entry['cash_bank_code'] ?? '-') ?></td>
                        <td><span class="badge bg-light text-dark"><?= esc($currency) ?></span></td>
                        <td class="text-end fw-semibold"><?= esc(number_format($amount, 2)) ?></td>
                        <td class="text-end"><?= esc(number_format($rate > 0 ? $rate : 1, 12)) ?></td>
                        <td class="text-end fw-semibold"><?= esc($baseCurrency . ' ' . number_format($baseAmount, 2)) ?></td>
                        <td><?= esc($entry['reference_no'] ?? '-') ?></td>
                        <td><?= ! empty($entry['gl_entry_id']) ? '<span class="badge bg-success">Posted</span>' : '<span class="badge bg-secondary">No GL</span>' ?></td>
                        <td class="text-end"><a href="<?= site_url($basePath . '/' . $entry['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i></a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($entries === []): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No entry found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
