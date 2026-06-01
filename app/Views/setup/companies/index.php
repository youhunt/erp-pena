<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="panel">
    <table class="table">
        <thead>
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
                    <td><?= (int) $company['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
