<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1">Data Import Export Center</h4>
                        <p class="text-muted mb-0">Download template, import CSV, and export master data for ERP implementation.</p>
                    </div>
                    <span class="badge bg-primary">Phase 1 - Master Data</span>
                </div>

                <div class="alert alert-info mb-4">
                    <strong>Recommended flow:</strong> download template, fill data, import CSV, then review data in each module. Transaction import will be handled in later phases using preview and draft posting.
                </div>

                <h5 class="mb-3">Master Data</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-nowrap align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Group</th>
                                <th>Module</th>
                                <th>Route</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($masters as $module): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark"><?= esc($module['group']) ?></span></td>
                                <td class="fw-semibold"><?= esc($module['name']) ?></td>
                                <td><code><?= esc($module['route']) ?></code></td>
                                <td class="text-end">
                                    <a href="<?= site_url($module['route'] . '/template') ?>" class="btn btn-sm btn-outline-secondary">Template</a>
                                    <a href="<?= site_url($module['route'] . '/import') ?>" class="btn btn-sm btn-outline-primary">Import</a>
                                    <a href="<?= site_url($module['route'] . '/export') ?>" class="btn btn-sm btn-outline-success">Export</a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>

                <h5 class="mb-3">Finance Master</h5>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Group</th>
                                <th>Module</th>
                                <th>Route</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($finance as $module): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark"><?= esc($module['group']) ?></span></td>
                                <td class="fw-semibold"><?= esc($module['name']) ?></td>
                                <td><code><?= esc($module['route']) ?></code></td>
                                <td class="text-end">
                                    <a href="<?= site_url($module['route'] . '/template') ?>" class="btn btn-sm btn-outline-secondary">Template</a>
                                    <a href="<?= site_url($module['route'] . '/import') ?>" class="btn btn-sm btn-outline-primary">Import</a>
                                    <a href="<?= site_url($module['route'] . '/export') ?>" class="btn btn-sm btn-outline-success">Export</a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
