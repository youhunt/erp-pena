<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-3">Recent Stock Movements</h4>
        <div class="table-responsive">
            <table class="table table-sm table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Dir</th>
                        <th class="text-end">Qty</th>
                        <th>Ref</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentMovements as $movement): ?>
                    <tr>
                        <td><?= esc($movement['movement_date'] ?? '-') ?></td>
                        <td><?= esc($movement['movement_type'] ?? '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= esc($movement['item_code'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($movement['item_name'] ?? '-') ?></small>
                        </td>
                        <td><span class="badge bg-<?= ($movement['direction'] ?? '') === 'in' ? 'success' : 'danger' ?>"><?= esc($movement['direction'] ?? '-') ?></span></td>
                        <td class="text-end"><?= esc(number_format((float) ($movement['qty'] ?? 0), 4)) ?></td>
                        <td><?= esc($movement['reference_no'] ?? '-') ?></td>
                    </tr>
                <?php endforeach ?>

                <?php if ($recentMovements === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No stock movement yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
