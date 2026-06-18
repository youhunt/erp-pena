<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$order ??= [];
$lines ??= [];
$isEdit = (bool) ($isEdit ?? false);
$action ??= $isEdit ? site_url('purchase/orders/' . (int) ($order['id'] ?? 0)) : site_url('purchase/orders');

$value = static fn (string $field, mixed $default = ''): string => (string) old($field, $order[$field] ?? $default);
$lineValue = static function (array $line, int $index, string $field, mixed $default = ''): string {
    $old = old($field . '.' . $index);
    if ($old !== null) {
        return (string) $old;
    }

    return (string) ($line[$field] ?? $default);
};

$lineRows = $lines !== [] ? $lines : array_fill(0, 3, []);
?>
<style>
    .po-lines-scroll {
        overflow-x: auto;
        overflow-y: visible;
        padding-bottom: .35rem;
    }

    #poLinesTable {
        min-width: 1240px;
        table-layout: fixed;
    }

    #poLinesTable th,
    #poLinesTable td {
        vertical-align: middle;
    }

    #poLinesTable th:nth-child(1),
    #poLinesTable td:nth-child(1) { width: 76px; min-width: 76px; }
    #poLinesTable th:nth-child(2),
    #poLinesTable td:nth-child(2) { width: 260px; min-width: 260px; }
    #poLinesTable th:nth-child(3),
    #poLinesTable td:nth-child(3) { width: 220px; min-width: 220px; }
    #poLinesTable th:nth-child(4),
    #poLinesTable td:nth-child(4) { width: 280px; min-width: 280px; }
    #poLinesTable th:nth-child(5),
    #poLinesTable td:nth-child(5) { width: 120px; min-width: 120px; }
    #poLinesTable th:nth-child(6),
    #poLinesTable td:nth-child(6) { width: 100px; min-width: 100px; }
    #poLinesTable th:nth-child(7),
    #poLinesTable td:nth-child(7) { width: 140px; min-width: 140px; }
    #poLinesTable th:nth-child(8),
    #poLinesTable td:nth-child(8) { width: 150px; min-width: 150px; }
    #poLinesTable th:nth-child(9),
    #poLinesTable td:nth-child(9) { width: 70px; min-width: 70px; }

    #poLinesTable .form-control,
    #poLinesTable .form-select {
        min-height: 36px;
        color: #212529;
        background-color: #fff;
        padding-top: .35rem;
        padding-bottom: .35rem;
    }

    #poLinesTable .line-number,
    #poLinesTable input[name="qty[]"],
    #poLinesTable input[name="uom_code[]"],
    #poLinesTable input[name="unit_price[]"] {
        min-width: 100%;
        padding-left: .45rem;
        padding-right: .45rem;
    }

    #poLinesTable .line-total {
        white-space: nowrap;
        color: #212529;
    }

    #poLinesTable .select2-container {
        min-width: 100% !important;
        width: 100% !important;
    }

    #poLinesTable .select2-selection__rendered {
        padding-right: 28px !important;
    }
</style>
<form method="post" action="<?= esc($action, 'attr') ?>">
    <?= csrf_field() ?>

    <div class="card"><div class="card-body"><div class="alert alert-danger">File restore interrupted. Please pull the next commit.</div></div></div>
</form>
<?= $this->endSection() ?>
