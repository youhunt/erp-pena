<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="panel">
    <table class="table">
        <thead>
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
                    <td><?= (int) $site['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
