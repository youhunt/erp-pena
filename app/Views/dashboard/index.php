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
    <div class="row">
        <?php foreach ($metrics as $label => $value): ?>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card mini-stats-wid">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <p class="text-muted fw-medium mb-2"><?= esc($label) ?></p>
                                <h4 class="mb-0"><?= esc((string) $value) ?></h4>
                            </div>
                            <div class="avatar-sm rounded-circle bg-primary align-self-center mini-stat-icon">
                                <span class="avatar-title rounded-circle bg-primary">
                                    <i class="bx bx-bar-chart-alt-2 font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>

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
                                <tr>
                                    <td>Approvals</td>
                                    <td class="text-end"><span class="badge bg-warning">0</span></td>
                                </tr>
                                <tr>
                                    <td>OCR Reviews</td>
                                    <td class="text-end"><span class="badge bg-info">0</span></td>
                                </tr>
                                <tr>
                                    <td>Stock Alerts</td>
                                    <td class="text-end"><span class="badge bg-danger">0</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
