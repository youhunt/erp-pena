<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$summary = $summary ?? [];
$rows = $rows ?? [];
$exportUrl = current_url() . '?' . http_build_query(array_filter([
    'date_from' => $dateFrom ?? null,
    'date_to' => $dateTo ?? null,
    'margin_status' => $selectedStatus ?? null,
    'export' => 'csv',
], static fn ($value): bool => $value !== null && $value !== ''));
$statusClass = static function (string $status): string {
    return match ($status) {
        'PROFIT_OK' => 'bg-success',
        'LOSS_REVIEW_COST_OR_PRICE' => 'bg-danger',
        'MISSING_COGS_GL' => 'bg-warning text-dark',
        'MISSING_DELIVERY' => 'bg-secondary',
        default => 'bg-light text-dark',
    };
};
$profitClass = static fn (float $amount): string => $amount >= 0 ? 'text-success' : 'text-danger';
?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h4 class="card-title mb-1">Sales Margin Report</h4>
                        <p class="text-muted mb-0">Audit invoice revenue, delivery COGS, gross profit/loss, and margin.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-end">
                        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
                            <div>
                                <label class="form-label small mb-1">From</label>
                                <input type="date" name="date_from" value="<?= esc($dateFrom) ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label class="form-label small mb-1">To</label>
                                <input type="date" name="date_to" value="<?= esc($dateTo) ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label class="form-label small mb-1">Status</label>
                                <select name="margin_status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <?php foreach ($statusOptions as $option): ?>
                                        <option value="<?= esc($option) ?>" <?= $selectedStatus === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary" type="submit">Filter</button>
                        </form>
                        <a href="<?= esc($exportUrl) ?>" class="btn btn-sm btn-outline-success"><i class="bx bx-download me-1"></i> Export CSV</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Invoices</div><div class="fs-4 fw-semibold"><?= esc(number_format((float) ($summary['invoice_count'] ?? 0))) ?></div></div></div>
                    <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Invoice Amount</div><div class="fs-4 fw-semibold"><?= esc(number_format((float) ($summary['invoice_amount'] ?? 0), 2)) ?></div></div></div>
                    <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">COGS Amount</div><div class="fs-4 fw-semibold"><?= esc(number_format((float) ($summary['cogs_amount'] ?? 0), 2)) ?></div></div></div>
                    <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Gross Profit/Loss</div><div class="fs-4 fw-semibold <?= $profitClass((float) ($summary['gross_profit_loss'] ?? 0)) ?>"><?= esc(number_format((float) ($summary['gross_profit_loss'] ?? 0), 2)) ?></div><small class="text-muted"><?= esc(isset($summary['gross_margin_pct']) && $summary['gross_margin_pct'] !== null ? number_format((float) $summary['gross_margin_pct'], 2) . '%' : '-') ?></small></div></div>
                </div>

                <?php if (! empty($summary['by_status'])): ?>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <?php foreach ($summary['by_status'] as $status => $data): ?>
                            <span class="badge <?= esc($statusClass((string) $status)) ?> p-2"><?= esc($status) ?>: <?= esc((string) ($data['count'] ?? 0)) ?></span>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Delivery</th>
                                <th class="text-end">Invoice</th>
                                <th class="text-end">COGS</th>
                                <th class="text-end">Gross P/L</th>
                                <th class="text-end">Margin</th>
                                <th>Status</th>
                                <th>GL</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $profit = (float) ($row['gross_profit_loss'] ?? 0); ?>
                            <tr>
                                <td><?= esc($row['invoice_date'] ?? '-') ?></td>
                                <td><a href="<?= site_url('ar/sales-invoices/' . (int) ($row['invoice_id'] ?? 0)) ?>" class="fw-semibold"><?= esc($row['invoice_no'] ?? '-') ?></a><br><small class="text-muted"><?= esc($row['invoice_status'] ?? '-') ?></small></td>
                                <td><div><?= esc($row['customer_code'] ?? '-') ?></div><small class="text-muted"><?= esc($row['customer_name'] ?? '-') ?></small></td>
                                <td><?= ! empty($row['delivery_id']) ? '<a href="' . site_url('sales/deliveries/' . (int) $row['delivery_id']) . '">' . esc($row['linked_delivery_no'] ?? $row['delivery_no'] ?? '-') . '</a>' : '<span class="text-muted">-</span>' ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($row['invoice_amount'] ?? 0), 2)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($row['cogs_amount'] ?? 0), 2)) ?></td>
                                <td class="text-end fw-semibold <?= $profitClass($profit) ?>"><?= esc(number_format($profit, 2)) ?></td>
                                <td class="text-end"><?= esc($row['gross_margin_pct'] !== null ? number_format((float) $row['gross_margin_pct'], 2) . '%' : '-') ?></td>
                                <td><span class="badge <?= esc($statusClass((string) ($row['margin_status'] ?? ''))) ?>"><?= esc($row['margin_status'] ?? '-') ?></span></td>
                                <td>
                                    <?= ! empty($row['invoice_gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . (int) $row['invoice_gl_entry_id']) . '">Inv GL</a>' : '-' ?>
                                    <?= ! empty($row['cogs_gl_entry_id']) ? '<br><a href="' . site_url('gl/entries/' . (int) $row['cogs_gl_entry_id']) . '">COGS GL</a>' : '' ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">No sales margin data for selected filter.</td></tr>
                        <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
