<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
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
    <div class="row dashboard-metric-row">
        <?php foreach ($metrics as $label => $value): ?>
            <?php
                $isMoney = in_array($label, $metricMoney ?? [], true);
                $displayValue = number_format((float) $value, 0, ',', '.');
                $route = $metricLinks[$label] ?? 'dashboard';
            ?>
            <div class="col-xl-4 col-md-6 col-sm-6">
                <a href="<?= site_url($route) ?>" class="text-reset dashboard-metric-card-link">
                    <div class="card mini-stats-wid dashboard-metric-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="flex-grow-1 dashboard-metric-content">
                                    <p class="text-muted fw-medium mb-2 dashboard-metric-label"><?= esc($label) ?></p>
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

    <?php if (! empty($workflowQueues)): ?>
        <div class="row">
            <?php foreach ($workflowQueues as $queue): ?>
                <div class="col-xl-4 col-md-6">
                    <a href="<?= site_url($queue['route'] ?? 'dashboard') ?>" class="text-reset">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <h5 class="font-size-15 mb-1"><?= esc($queue['label'] ?? '-') ?></h5>
                                        <p class="text-muted mb-0"><?= esc($queue['description'] ?? '-') ?></p>
                                    </div>
                                    <span class="badge bg-<?= esc($queue['badge'] ?? 'primary') ?> font-size-14">
                                        <?= esc(number_format((float) ($queue['count'] ?? 0), 0, ',', '.')) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title mb-0">Recent ERP Activity</h4>
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
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">Pending Work</h4>
                    <div class="table-responsive">
                        <table class="table table-nowrap align-middle mb-0">
                            <tbody>
                                <?php foreach ($pendingWork as $work): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= site_url($work['route'] ?? 'dashboard') ?>" class="text-reset">
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
                                    <tr><td class="text-center text-muted py-4">No pending work.</td></tr>
                                <?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
