<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="metric-grid">
    <?php foreach ($metrics as $label => $value): ?>
        <div class="panel">
            <div><?= esc($label) ?></div>
            <h2><?= esc((string) $value) ?></h2>
        </div>
    <?php endforeach ?>
</div>

<div class="panel" style="margin-top: 18px;">
    <h3>Recent Activity</h3>
    <p>No activity yet. Activity will be populated from audit trails and transaction workflow.</p>
</div>
<?= $this->endSection() ?>
