<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<style>
    .erp-uat-card {
        background: linear-gradient(135deg, #4658e8 0%, #233a9f 100%);
        box-shadow: 0 12px 28px rgba(35, 58, 159, .22);
    }
    .erp-uat-card .erp-uat-subtitle {
        color: rgba(255, 255, 255, .82) !important;
    }
    .erp-quick-audit-card,
    .dashboard-metric-card,
    .dashboard-queue-card,
    .dashboard-financial-card,
    .dashboard-core-card {
        border: 0;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
    }
    .dashboard-section-title,
    .dashboard-metric-value,
    .dashboard-financial-value,
    .erp-quick-audit-title,
    .dashboard-core-title {
        color: #172033 !important;
        font-weight: 700;
    }
    .dashboard-metric-label,
    .dashboard-financial-label,
    .dashboard-core-label {
        color: #5f6c80 !important;
        letter-spacing: .01em;
    }
    .erp-quick-audit-card .btn {
        border-width: 1.5px;
        font-weight: 600;
    }
    .dashboard-table-sm td,
    .dashboard-table-sm th {
        padding: .65rem .75rem;
        vertical-align: middle;
    }
    .dashboard-trend-bar {
        min-width: 64px;
        height: 7px;
        background: #edf1f7;
        border-radius: 999px;
        overflow: hidden;
    }
    .dashboard-trend-fill {
        height: 7px;
        border-radius: 999px;
        background: currentColor;
    }
</style>

<?php if (empty($hasTenantAccess)): ?>
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="avatar-lg mx-auto mb-4">
                        <span class="avatar-title rounded-circle bg-warning bg-soft text-warning font-size-32">
                            <i class="bx bx-buildings"></i>
                        </span>
                    </div>
                    <h4 class="mb-2">No Company Access Assigned</h4>
                    <p class="text-muted mb-4">
                        Your user account does not have access to any active company/site yet. Please ask an administrator to assign company and site access from User Management.
                    </p>
                    <?php if (auth()->user()?->can('users.manage') || auth()->user()?->inGroup('superadmin')): ?>
                        <a href="<?= site_url('admin/users') ?>" class="btn btn-primary">
                            <i class="bx bx-user me-1"></i> Open User Management
                        </a>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card border-0 h-100 erp-uat-card">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 p-4">
                    <div>
                        <span class="badge bg-light text-primary mb-2">ERP Core UAT</span>
                        <h4 class="mb-2 text-white fw-bold">Development Status & UAT Flow Board</h4>
                        <p class="mb-0 erp-uat-subtitle">
                            Pantau progress modul, core guardrail, dan jalur test PO/SO/Inventory/GL dari satu halaman.
                        </p>
                    </div>
                    <a href="<?= site_url('system/development-status') ?>" class="btn btn-light text-primary fw-semibold shadow-sm">
                        <i class="bx bx-line-chart me-1"></i> Open Development Status
                    </a>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100 erp-quick-audit-card">
                <div class="card-body p-4">
                    <h5 class="font-size-15 mb-1 erp-quick-audit-title">Quick Audit</h5>
                    <p class="text-muted small mb-3">Akses cepat ke audit inventory dan jurnal.</p>
                    <div class="d-grid gap-2">
                        <a href="<?= site_url('inventory/stock-card') ?>" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bx bx-package me-1"></i> Stock Card Audit
                        </a>
                        <a href="<?= site_url('gl/entries') ?>" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bx bx-book me-1"></i> GL Entries Audit
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="dashboard-section dashboard-section-summary">
        <div class="dashboard-section-header">
            <div>
                <h5 class="dashboard-section-title mb-1">Operational Summary</h5>
                <p class="dashboard-section-subtitle text-muted mb-0">Ringkasan nilai transaksi, approval, OCR, dan alert aktif.</p>
            </div>
        </div>

        <div class="row dashboard-metric-row g-3">
            <?php foreach ($metrics as $label => $value): ?>
                <?php
                $isMoney = in_array($label, $metricMoney ?? [], true);
                $displayValue = number_format((float) $value, 0, ',', '.');
                $route = $metricLinks[$label] ?? 'dashboard';
                ?>
                <div class="col-xl-4 col-md-6 col-sm-6">
                    <a href="<?= site_url($route) ?>" class="text-reset dashboard-metric-card-link">
                        <div class="card mini-stats-wid dashboard-metric-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="flex-grow-1 dashboard-metric-content">
                                        <p class="fw-medium mb-2 dashboard-metric-label"><?= esc($label) ?></p>
                                        <h4 class="mb-2 dashboard-metric-value"><?= $isMoney ? 'Rp ' : '' ?><?= esc($displayValue) ?></h4>
                                        <span class="btn btn-sm btn-outline-primary dashboard-metric-link">
                                            <i class="bx bx-right-arrow-alt me-1"></i> Open Module
                                        </span>
                                    </div>
                                    <div class="avatar-sm rounded-circle bg-primary flex-shrink-0 mini-stat-icon dashboard-metric-icon">
                                        <span class="avatar-title rounded-circle bg-primary">
                                            <i class="bx bx-bar-chart-alt-2 font-size-24"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <?php if (! empty($financialSnapshot)): ?>
        <section class="dashboard-section dashboard-section-financial mt-3">
            <div class="dashboard-section-header">
                <div>
                    <h5 class="dashboard-section-title mb-1">Financial Snapshot</h5>
                    <p class="dashboard-section-subtitle text-muted mb-0">Ringkasan posisi AR/AP, kas/bank, inventory value, dan validasi GL aktif.</p>
                </div>
            </div>
            <div class="row g-3">
                <?php foreach ($financialSnapshot as $snapshot): ?>
                    <?php
                    $isMoney = (bool) ($snapshot['money'] ?? false);
                    $displayValue = number_format((float) ($snapshot['value'] ?? 0), $isMoney ? 0 : 0, ',', '.');
                    $tone = $snapshot['tone'] ?? 'primary';
                    ?>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <a href="<?= site_url($snapshot['route'] ?? 'dashboard') ?>" class="text-reset">
                            <div class="card dashboard-financial-card h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <span class="avatar-title rounded bg-<?= esc($tone) ?> bg-soft text-<?= esc($tone) ?> font-size-20">
                                            <i class="<?= esc($snapshot['icon'] ?? 'bx bx-bar-chart') ?>"></i>
                                        </span>
                                        <span class="badge bg-<?= esc($tone) ?> bg-soft text-<?= esc($tone) ?>">Live</span>
                                    </div>
                                    <p class="dashboard-financial-label small mb-1"><?= esc($snapshot['label'] ?? '-') ?></p>
                                    <h5 class="dashboard-financial-value mb-0"><?= $isMoney ? 'Rp ' : '' ?><?= esc($displayValue) ?></h5>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>

    <?php if (! empty($agingShortcuts)): ?>
        <section class="dashboard-section dashboard-section-aging mt-3">
            <div class="dashboard-section-header">
                <div>
                    <h5 class="dashboard-section-title mb-1">Aging Shortcuts</h5>
                    <p class="dashboard-section-subtitle text-muted mb-0">Shortcut AR/AP aging dengan nilai outstanding dan jumlah invoice open.</p>
                </div>
            </div>
            <div class="row g-3">
                <?php foreach ($agingShortcuts as $shortcut): ?>
                    <?php $tone = $shortcut['tone'] ?? 'primary'; ?>
                    <div class="col-xl-6">
                        <a href="<?= site_url($shortcut['route'] ?? 'dashboard') ?>" class="text-reset">
                            <div class="card dashboard-core-card h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start justify-content-between gap-3">
                                        <div>
                                            <p class="dashboard-core-label small mb-1"><?= esc($shortcut['description'] ?? '-') ?></p>
                                            <h5 class="dashboard-core-title mb-2"><?= esc($shortcut['label'] ?? '-') ?></h5>
                                            <h4 class="mb-0 text-<?= esc($tone) ?>">Rp <?= esc(number_format((float) ($shortcut['amount'] ?? 0), 0, ',', '.')) ?></h4>
                                        </div>
                                        <div class="text-end">
                                            <span class="avatar-title rounded bg-<?= esc($tone) ?> bg-soft text-<?= esc($tone) ?> font-size-24 mb-2">
                                                <i class="<?= esc($shortcut['icon'] ?? 'bx bx-file') ?>"></i>
                                            </span>
                                            <span class="badge bg-<?= esc($tone) ?>"><?= esc(number_format((float) ($shortcut['count'] ?? 0), 0, ',', '.')) ?> invoice</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>

    <section class="dashboard-section dashboard-section-core-detail mt-3">
        <div class="dashboard-section-header">
            <div>
                <h5 class="dashboard-section-title mb-1">ERP Core Exception Monitor</h5>
                <p class="dashboard-section-subtitle text-muted mb-0">Invoice tertinggi yang pending, stock alert prioritas, dan jurnal tidak balance.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-6">
                <div class="card dashboard-core-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0 fw-bold text-dark">Top Pending Invoices</h4>
                            <a href="<?= site_url('ar/aging') ?>" class="btn btn-sm btn-outline-primary">AR/AP Aging</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table dashboard-table-sm table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Invoice</th>
                                        <th>Partner</th>
                                        <th class="text-end">Outstanding</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topPendingInvoices ?? [] as $invoice): ?>
                                        <tr>
                                            <td><span class="badge bg-<?= ($invoice['type'] ?? '') === 'AR' ? 'primary' : 'danger' ?>"><?= esc($invoice['type'] ?? '-') ?></span></td>
                                            <td>
                                                <a href="<?= site_url($invoice['route'] ?? 'dashboard') ?>" class="fw-semibold text-reset"><?= esc($invoice['document_no'] ?? '-') ?></a>
                                                <div class="text-muted small">Due: <?= esc($invoice['due_date'] ?? '-') ?></div>
                                            </td>
                                            <td><?= esc($invoice['partner_name'] ?? '-') ?></td>
                                            <td class="text-end fw-semibold">Rp <?= esc(number_format((float) ($invoice['amount'] ?? 0), 0, ',', '.')) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if (empty($topPendingInvoices)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No pending invoice found.</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card dashboard-core-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0 fw-bold text-dark">Top Stock Alerts</h4>
                            <a href="<?= site_url('inventory/stock-alerts') ?>" class="btn btn-sm btn-outline-danger">Open Alert</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table dashboard-table-sm table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Available</th>
                                        <th class="text-end">Threshold</th>
                                        <th class="text-end">Shortage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topStockAlerts ?? [] as $stock): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= site_url($stock['route'] ?? 'dashboard') ?>" class="fw-semibold text-reset"><?= esc($stock['item_code'] ?? '-') ?></a>
                                                <div class="text-muted small"><?= esc($stock['item_name'] ?? '-') ?></div>
                                            </td>
                                            <td class="text-end"><?= esc(number_format((float) ($stock['qty_available'] ?? 0), 2, ',', '.')) ?></td>
                                            <td class="text-end"><?= esc(number_format((float) ($stock['threshold'] ?? 0), 2, ',', '.')) ?></td>
                                            <td class="text-end text-danger fw-semibold"><?= esc(number_format((float) ($stock['shortage'] ?? 0), 2, ',', '.')) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if (empty($topStockAlerts)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No stock alert found.</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card dashboard-core-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0 fw-bold text-dark">Monthly Sales/Purchase Trend</h4>
                            <span class="badge bg-info bg-soft text-info">Last 6 months</span>
                        </div>
                        <?php
                        $trendMax = 0.0;
                        foreach ($monthlyTrend ?? [] as $trend) {
                            $trendMax = max($trendMax, (float) ($trend['sales'] ?? 0), (float) ($trend['purchase'] ?? 0));
                        }
                        ?>
                        <div class="table-responsive">
                            <table class="table dashboard-table-sm table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th>Sales</th>
                                        <th>Purchase</th>
                                        <th class="text-end">Net</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyTrend ?? [] as $trend): ?>
                                        <?php
                                        $sales = (float) ($trend['sales'] ?? 0);
                                        $purchase = (float) ($trend['purchase'] ?? 0);
                                        $salesPct = $trendMax > 0 ? max(4, min(100, ($sales / $trendMax) * 100)) : 0;
                                        $purchasePct = $trendMax > 0 ? max(4, min(100, ($purchase / $trendMax) * 100)) : 0;
                                        ?>
                                        <tr>
                                            <td class="fw-semibold"><?= esc($trend['month'] ?? '-') ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="text-primary fw-semibold">Rp <?= esc(number_format($sales, 0, ',', '.')) ?></span>
                                                    <div class="dashboard-trend-bar flex-grow-1 text-primary"><div class="dashboard-trend-fill" style="width: <?= esc((string) $salesPct) ?>%"></div></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="text-danger fw-semibold">Rp <?= esc(number_format($purchase, 0, ',', '.')) ?></span>
                                                    <div class="dashboard-trend-bar flex-grow-1 text-danger"><div class="dashboard-trend-fill" style="width: <?= esc((string) $purchasePct) ?>%"></div></div>
                                                </div>
                                            </td>
                                            <td class="text-end fw-semibold <?= ((float) ($trend['net'] ?? 0)) < 0 ? 'text-danger' : 'text-success' ?>">Rp <?= esc(number_format((float) ($trend['net'] ?? 0), 0, ',', '.')) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if (empty($monthlyTrend)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No trend data found.</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card dashboard-core-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0 fw-bold text-dark">GL Unbalanced Detail</h4>
                            <a href="<?= site_url('gl/entries') ?>" class="btn btn-sm btn-outline-warning">Open GL</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table dashboard-table-sm table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Entry</th>
                                        <th>Date</th>
                                        <th class="text-end">Variance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($glUnbalancedEntries ?? [] as $entry): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= site_url($entry['route'] ?? 'dashboard') ?>" class="fw-semibold text-reset"><?= esc($entry['entry_no'] ?? '-') ?></a>
                                                <div class="text-muted small">D <?= esc(number_format((float) ($entry['debit'] ?? 0), 0, ',', '.')) ?> / C <?= esc(number_format((float) ($entry['credit'] ?? 0), 0, ',', '.')) ?></div>
                                            </td>
                                            <td><?= esc($entry['entry_date'] ?? '-') ?></td>
                                            <td class="text-end text-warning fw-semibold">Rp <?= esc(number_format((float) ($entry['variance'] ?? 0), 0, ',', '.')) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if (empty($glUnbalancedEntries)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">No unbalanced GL entry found.</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (! empty($workflowQueues)): ?>
        <section class="dashboard-section dashboard-section-workflow mt-3">
            <div class="dashboard-section-header">
                <div>
                    <h5 class="dashboard-section-title mb-1">Workflow Queue</h5>
                    <p class="dashboard-section-subtitle text-muted mb-0">Outstanding item yang perlu ditindaklanjuti oleh user.</p>
                </div>
            </div>

            <div class="row dashboard-workflow-row g-3">
                <?php foreach ($workflowQueues as $queue): ?>
                    <div class="col-xl-4 col-md-6">
                        <a href="<?= site_url($queue['route'] ?? 'dashboard') ?>" class="text-reset dashboard-queue-card-link">
                            <div class="card dashboard-queue-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between gap-3">
                                        <div>
                                            <h5 class="font-size-15 mb-1 fw-semibold text-dark"><?= esc($queue['label'] ?? '-') ?></h5>
                                            <p class="text-muted mb-0 dashboard-queue-description"><?= esc($queue['description'] ?? '-') ?></p>
                                        </div>
                                        <span class="badge bg-<?= esc($queue['badge'] ?? 'primary') ?> font-size-14 dashboard-queue-badge">
                                            <?= esc(number_format((float) ($queue['count'] ?? 0), 0, ',', '.')) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>

    <section class="dashboard-section dashboard-section-activity mt-3">
        <div class="dashboard-section-header">
            <div>
                <h5 class="dashboard-section-title mb-1">Activity & Pending Work</h5>
                <p class="dashboard-section-subtitle text-muted mb-0">Aktivitas terakhir dan daftar pekerjaan yang masih menunggu.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0 fw-bold text-dark">Recent ERP Activity</h4>
                            <a href="<?= site_url('audit-logs') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>

                        <?php if (! empty($recentActivities)): ?>
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>Action</th>
                                            <th>Record</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <tr>
                                                <td class="text-muted small"><?= esc($activity['created_at'] ?? '-') ?></td>
                                                <td><span class="badge bg-info"><?= esc($activity['action'] ?? '-') ?></span></td>
                                                <td>
                                                    <div class="fw-semibold"><?= esc($activity['record_code'] ?? $activity['record_id'] ?? '-') ?></div>
                                                    <small class="text-muted"><?= esc($activity['table_name'] ?? $activity['module'] ?? '-') ?></small>
                                                </td>
                                                <td><?= esc($activity['description'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bx bx-time-five display-4 text-muted"></i>
                                <p class="text-muted mt-3 mb-0">No activity yet. Activity will be populated from audit trails and transaction workflow.</p>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h4 class="card-title mb-4 fw-bold text-dark">Pending Work</h4>
                        <div class="table-responsive">
                            <table class="table table-nowrap align-middle mb-0">
                                <tbody>
                                    <?php foreach ($pendingWork as $work): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= site_url($work['route'] ?? 'dashboard') ?>" class="text-reset fw-semibold">
                                                    <?= esc($work['label'] ?? '-') ?>
                                                </a>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge bg-<?= esc($work['badge'] ?? 'secondary') ?>">
                                                    <?= esc(number_format((float) ($work['count'] ?? 0), 0, ',', '.')) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if (empty($pendingWork)): ?>
                                        <tr>
                                            <td class="text-center text-muted py-4">No pending work.</td>
                                        </tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
