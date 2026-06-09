<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1">Excel Import Export</h4>
                        <p class="text-muted mb-0">Download template Excel, import .xlsx, and export master data in native Excel format.</p>
                    </div>
                    <span class="badge bg-success">.xlsx</span>
                </div>

                <div class="alert alert-info mb-4">
                    Gunakan menu ini untuk user operasional yang lebih nyaman memakai Microsoft Excel. Format ini menghindari masalah CSV yang tampil banyak koma saat dibuka di Excel.
                </div>

                <div class="table-responsive">
                    <table class="table table-nowrap align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Module</th>
                                <th>Table</th>
                                <th>Scope</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($resources as $resource => $config): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($config['title']) ?></td>
                                <td><code><?= esc($config['table']) ?></code></td>
                                <td>
                                    <?php if (! empty($config['tenant'])): ?><span class="badge bg-light text-dark">Company</span><?php endif ?>
                                    <?php if (! empty($config['site'])): ?><span class="badge bg-light text-dark">Site</span><?php endif ?>
                                    <?php if (empty($config['tenant']) && empty($config['site'])): ?><span class="badge bg-light text-dark">Global</span><?php endif ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= site_url('system/excel-transfer/' . $resource . '/template') ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bx bx-download me-1"></i> Template
                                    </a>
                                    <a href="<?= site_url('system/excel-transfer/' . $resource . '/import') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bx bx-upload me-1"></i> Import
                                    </a>
                                    <a href="<?= site_url('system/excel-transfer/' . $resource . '/export') ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bx bx-export me-1"></i> Export
                                    </a>
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
