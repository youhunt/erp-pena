<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<p><a class="button" href="<?= site_url('ai-documents/upload') ?>">Upload Document</a></p>

<div class="panel">
    <table class="table">
        <thead>
            <tr>
                <th>File</th>
                <th>Type</th>
                <th>Status</th>
                <th>Hash</th>
                <th>Uploaded</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documents as $document): ?>
                <tr>
                    <td><?= esc($document['original_name']) ?></td>
                    <td><?= esc($document['document_type'] ?? '-') ?></td>
                    <td><?= esc($document['status']) ?></td>
                    <td><?= esc(substr($document['sha256_hash'], 0, 12)) ?></td>
                    <td><?= esc($document['created_at'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
