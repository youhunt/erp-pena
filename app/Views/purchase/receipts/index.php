<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Purchase Receipts</h4>
                <p class="text-muted mb-0">Posted receiving documents that increased inventory stock.</p>
            </div>
            <a href="<?= site_url('purchase/orders') ?>" class="btn btn-primary">
                <i class="bx bx-cart me-1"></i> Open Purchase Orders
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>PO No</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Posted At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($receipts as $receipt): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($receipt['receipt_no'] ?? '-') ?></td>
                        <td><?= esc($receipt['receipt_date'] ?? '-') ?></td>
                        <td><a href="<?= site_url('purchase/orders/' . $receipt['purchase_order_id']) ?>"><?= esc($receipt['po_no'] ?? '-') ?></a></td>
                        <td>
                            <div><?= esc($receipt['supplier_name'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($receipt['supplier_code'] ?? '-') ?></small>
                        </td>
                        <td><span class="badge bg-success"><?= esc($receipt['status'] ?? '-') ?></span></td>
                        <td><?= esc($receipt['posted_at'] ?? '-') ?></td>
                        <td class="text-end">
                            <a href="<?= site_url('purchase/receipts/' . $receipt['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($receipts === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No purchase receipt posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
