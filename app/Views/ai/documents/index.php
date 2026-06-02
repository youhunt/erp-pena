<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="card-title mb-1">Document Processing Queue</h4>
                <p class="text-muted mb-0">Uploaded ERP documents waiting for OCR and AI extraction.</p>
            </div>
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
                        <th>Size</th>
                        <th>Hash</th>
                        <th>Uploaded</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($documents as $document): ?>
                    <?php
                    $status = $document['status'] ?? 'uploaded';
                    $badge = match ($status) {
                        'duplicate' => 'warning',
                        'processed' => 'success',
                        'failed' => 'danger',
                        default => 'info',
                    };
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($document['original_name']) ?></td>
                        <td><?= esc($document['document_type'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= esc($badge) ?>"><?= esc($status) ?></span></td>
                        <td><?= esc(number_format(((int) ($document['file_size'] ?? 0)) / 1024, 2)) ?> KB</td>
                        <td><code><?= esc(substr($document['sha256_hash'], 0, 12)) ?></code></td>
                        <td><?= esc($document['created_at'] ?? '-') ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= site_url('ai-documents/' . $document['id']) ?>">
                                <i class="bx bx-show"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($documents === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No documents uploaded yet.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
