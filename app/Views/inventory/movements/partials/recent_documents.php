<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-3">Recent Posted Documents</h4>
        <div class="table-responsive">
            <table class="table table-sm table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Document</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Value</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentDocuments as $document): ?>
                    <tr>
                        <td><?= esc($document['document_date'] ?? '-') ?></td>
                        <td><a href="<?= site_url('inventory/movement-documents/' . $document['id']) ?>"><?= esc($document['document_no'] ?? '-') ?></a></td>
                        <td><?= esc($document['document_type'] ?? '-') ?></td>
                        <td><span class="badge bg-success"><?= esc($document['status'] ?? 'posted') ?></span></td>
                        <td class="text-end"><?= esc(number_format((float) ($document['total_qty'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($document['total_value'] ?? 0), 2)) ?></td>
                    </tr>
                <?php endforeach ?>

                <?php if ($recentDocuments === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No posted document yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
