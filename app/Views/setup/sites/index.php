<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-4">Site / Branch Master</h4>
        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Company ID</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sites as $site): ?>
                <tr>
                    <td><?= esc($site['code']) ?></td>
                    <td><?= esc($site['name']) ?></td>
                    <td><?= esc((string) $site['company_id']) ?></td>
                    <td><span class="badge bg-<?= (int) $site['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $site['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
