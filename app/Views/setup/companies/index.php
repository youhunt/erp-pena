<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-4">Company Master</h4>
        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Currency</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($companies as $company): ?>
                <tr>
                    <td><?= esc($company['code']) ?></td>
                    <td><?= esc($company['name']) ?></td>
                    <td><?= esc($company['base_currency']) ?></td>
                    <td><span class="badge bg-<?= (int) $company['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $company['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
