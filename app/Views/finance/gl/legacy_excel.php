<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Legacy GL Excel Import Export</h4>
                <p class="text-muted mb-0">Import source GL tables from Excel, preview before posting, then sync to modern GL tables from GL Utilities.</p>
            </div>
            <a href="<?= site_url('gl/utilities') ?>" class="btn btn-outline-primary">GL Utilities</a>
        </div>

        <div class="alert alert-info">
            Recommended flow: download template, fill Excel, preview import, post valid rows, then run Sync COA / Sync GL Book from GL Utilities.
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Source Table</th>
                        <th>Title</th>
                        <th class="text-end">Rows</th>
                        <th>Fields</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resources as $key => $resource): ?>
                    <tr>
                        <td class="fw-semibold"><code><?= esc($resource['table']) ?></code></td>
                        <td><?= esc($resource['title']) ?></td>
                        <td class="text-end"><?= number_format((int) ($resource['count'] ?? 0)) ?></td>
                        <td>
                            <?php foreach (array_slice($resource['fields'], 0, 6) as $field): ?>
                                <code class="me-1 small"><?= esc($field) ?></code>
                            <?php endforeach ?>
                            <?php if (count($resource['fields']) > 6): ?>
                                <span class="text-muted">+<?= count($resource['fields']) - 6 ?> more</span>
                            <?php endif ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= site_url('gl/legacy-excel/' . $key . '/template') ?>" class="btn btn-sm btn-outline-secondary">Template</a>
                            <a href="<?= site_url('gl/legacy-excel/' . $key . '/export') ?>" class="btn btn-sm btn-outline-secondary">Export</a>
                            <a href="<?= site_url('gl/legacy-excel/' . $key . '/import') ?>" class="btn btn-sm btn-success">Import</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
