<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="card-title mb-0">Document Processing Queue</h4>
            <a class="btn btn-primary waves-effect waves-light" href="<?= site_url('ai-documents/upload') ?>">
                <i class="bx bx-upload me-1"></i> Upload Document
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
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
                    <td><span class="badge bg-info"><?= esc($document['status']) ?></span></td>
                    <td><?= esc(substr($document['sha256_hash'], 0, 12)) ?></td>
                    <td><?= esc($document['created_at'] ?? '-') ?></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
