<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Print Document') ?></title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 12px; background: #f3f4f6; }
        .toolbar { position: sticky; top: 0; display: flex; justify-content: flex-end; gap: 8px; padding: 10px 14px; background: #111827; z-index: 5; }
        .toolbar a, .toolbar button { border: 0; border-radius: 6px; padding: 8px 12px; background: #fff; color: #111827; text-decoration: none; cursor: pointer; font-weight: 600; }
        .page { width: 210mm; min-height: 297mm; margin: 14px auto; padding: 16mm; background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, .12); }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #111827; padding-bottom: 14px; }
        .company-name { font-size: 20px; font-weight: 800; letter-spacing: .2px; }
        .company-meta { margin-top: 4px; line-height: 1.45; color: #374151; max-width: 420px; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 22px; text-transform: uppercase; letter-spacing: 1px; }
        .doc-title .doc-no { margin-top: 8px; font-size: 14px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 18px; }
        .box { border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; }
        .box-title { font-weight: 800; margin-bottom: 8px; text-transform: uppercase; font-size: 11px; color: #374151; letter-spacing: .4px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .meta td:first-child { color: #6b7280; width: 110px; }
        .lines { margin-top: 18px; }
        .lines th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 7px 6px; text-align: left; font-size: 11px; }
        .lines td { border: 1px solid #d1d5db; padding: 7px 6px; vertical-align: top; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw { font-weight: 700; }
        .muted { color: #6b7280; }
        .totals { width: 45%; margin-left: auto; margin-top: 14px; }
        .totals td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .totals tr:last-child td { border-top: 2px solid #111827; border-bottom: 0; font-weight: 800; font-size: 14px; }
        .notes { margin-top: 18px; min-height: 55px; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 30px; }
        .signature { text-align: center; min-height: 86px; border-top: 0; }
        .signature .line { margin-top: 58px; border-top: 1px solid #111827; padding-top: 6px; font-weight: 700; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
<?php
$h = $header ?? [];
$cfg = $config ?? [];
$type = (string) ($type ?? '');
$company = $company ?? [];
$docNo = (string) ($h[$cfg['number'] ?? ''] ?? '');
$docDate = (string) ($h[$cfg['date'] ?? ''] ?? '');
$status = (string) ($h['document_status'] ?? $h['status'] ?? '-');
$partnerCode = (string) ($h[$cfg['partner_code'] ?? ''] ?? '');
$partnerName = (string) ($h[$cfg['partner_name'] ?? ''] ?? '');
$sourceLabel = (string) ($cfg['source_label'] ?? '');
$sourceField = (string) ($cfg['source_field'] ?? '');
$subtotal = (float) ($h['subtotal_amount'] ?? 0);
$discount = (float) ($h['discount_amount'] ?? 0);
$freight = (float) ($h['freight_amount'] ?? 0);
$other = (float) ($h['other_amount'] ?? 0);
$special = (float) ($h['special_charge_amount'] ?? 0);
$tax = (float) ($h['vat_amount'] ?? $h['tax_amount'] ?? 0);
$wht = (float) ($h['wht_amount'] ?? 0);
$total = (float) ($h['total_amount'] ?? 0);
$showLineCommercial = ! in_array($type, ['purchase-order', 'purchase-receipt', 'sales-delivery'], true);
$money = static fn (mixed $v): string => number_format((float) $v, 2);
$qty = static fn (mixed $v): string => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
$backUrl = $_SERVER['HTTP_REFERER'] ?? '#';
?>
<div class="toolbar">
    <a href="<?= esc($backUrl, 'attr') ?>">Back</a>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="page">
    <div class="header">
        <div>
            <div class="company-name"><?= esc($company['name'] ?? $company['legal_name'] ?? $h['company'] ?? 'PENA ERP') ?></div>
            <div class="company-meta">
                <?= esc($company['address'] ?? '') ?><br>
                <?php if (! empty($company['tax_number'])): ?>NPWP: <?= esc($company['tax_number']) ?><br><?php endif ?>
                Company/Site: <?= esc(($h['company'] ?? $h['company_id'] ?? '-') . ' / ' . ($h['site'] ?? $h['site_id'] ?? '-')) ?>
            </div>
        </div>
        <div class="doc-title">
            <h1><?= esc($cfg['title'] ?? 'Document') ?></h1>
            <div class="doc-no"><?= esc($docNo) ?></div>
            <div class="muted">Status: <?= esc($status) ?></div>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <div class="box-title">Document Info</div>
            <table class="meta">
                <tr><td>No</td><td class="fw"><?= esc($docNo) ?></td></tr>
                <tr><td>Date</td><td><?= esc($docDate ?: '-') ?></td></tr>
                <?php if (! empty($h['delivery_date'])): ?><tr><td>Delivery Date</td><td><?= esc($h['delivery_date']) ?></td></tr><?php endif ?>
                <?php if (! empty($h['arrive_date'])): ?><tr><td>Arrive Date</td><td><?= esc($h['arrive_date']) ?></td></tr><?php endif ?>
                <?php if ($sourceLabel !== '' && $sourceField !== ''): ?><tr><td><?= esc($sourceLabel) ?></td><td><?= esc($h[$sourceField] ?? '-') ?></td></tr><?php endif ?>
                <tr><td>Currency</td><td><?= esc($h['currency_code'] ?? 'IDR') ?></td></tr>
            </table>
        </div>
        <div class="box">
            <div class="box-title"><?= esc($cfg['partner_label'] ?? 'Partner') ?></div>
            <table class="meta">
                <tr><td>Code</td><td class="fw"><?= esc($partnerCode ?: '-') ?></td></tr>
                <tr><td>Name</td><td><?= esc($partnerName ?: '-') ?></td></tr>
                <tr><td>Terms</td><td><?= esc($h['terms_code'] ?? '-') ?></td></tr>
            </table>
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th class="text-center" style="width:34px;">#</th>
                <th style="width:110px;">Item Code</th>
                <th>Item / Description</th>
                <th class="text-end" style="width:70px;">Qty</th>
                <th style="width:60px;">UoM</th>
                <th class="text-end" style="width:84px;">Price</th>
                <?php if ($showLineCommercial): ?>
                    <th class="text-end" style="width:84px;">Disc</th>
                    <th class="text-end" style="width:84px;">Tax</th>
                <?php endif ?>
                <th class="text-end" style="width:94px;">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($lines ?? []) as $line): ?>
            <?php
            $lineNo = $line['line_no'] ?? $line['po_line'] ?? $line['so_line'] ?? '';
            $lineQty = $line[$cfg['qty'] ?? 'qty'] ?? $line['qty'] ?? 0;
            $linePrice = $line[$cfg['price'] ?? 'unit_price'] ?? $line['unit_price'] ?? $line['unit_cost'] ?? 0;
            ?>
            <tr>
                <td class="text-center"><?= esc($lineNo) ?></td>
                <td><?= esc($line['item_code'] ?? '-') ?></td>
                <td>
                    <div class="fw"><?= esc($line['item_name'] ?? '-') ?></div>
                    <?php if (! empty($line['description'])): ?><div class="muted"><?= esc($line['description']) ?></div><?php endif ?>
                    <?php if (! empty($line['batch_no'])): ?><div class="muted">Batch: <?= esc($line['batch_no']) ?></div><?php endif ?>
                </td>
                <td class="text-end"><?= esc($qty($lineQty)) ?></td>
                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                <td class="text-end"><?= esc($money($linePrice)) ?></td>
                <?php if ($showLineCommercial): ?>
                    <td class="text-end"><?= esc($money($line['discount_amount'] ?? 0)) ?></td>
                    <td class="text-end"><?= esc($money($line['vat_amount'] ?? $line['tax_amount'] ?? 0)) ?></td>
                <?php endif ?>
                <td class="text-end fw"><?= esc($money($line['line_total'] ?? 0)) ?></td>
            </tr>
        <?php endforeach ?>
        <?php if (($lines ?? []) === []): ?>
            <tr><td colspan="<?= $showLineCommercial ? 9 : 7 ?>" class="text-center muted">No line data.</td></tr>
        <?php endif ?>
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="text-end"><?= esc($money($subtotal)) ?></td></tr>
        <tr><td>Discount</td><td class="text-end"><?= esc($money($discount)) ?></td></tr>
        <?php if ($freight != 0.0): ?><tr><td>Freight</td><td class="text-end"><?= esc($money($freight)) ?></td></tr><?php endif ?>
        <?php if ($other != 0.0): ?><tr><td>Other Amount</td><td class="text-end"><?= esc($money($other)) ?></td></tr><?php endif ?>
        <?php if ($special != 0.0): ?><tr><td>Special Charge</td><td class="text-end"><?= esc($money($special)) ?></td></tr><?php endif ?>
        <tr><td><?= $type === 'purchase-order' ? 'VAT' : 'VAT / Tax' ?></td><td class="text-end"><?= esc($money($tax)) ?></td></tr>
        <?php if ($wht != 0.0): ?><tr><td>WHT</td><td class="text-end">(<?= esc($money($wht)) ?>)</td></tr><?php endif ?>
        <tr><td>Grand Total</td><td class="text-end"><?= esc($money($total)) ?></td></tr>
    </table>

    <?php if (! empty($h['notes']) || ! empty($h['remarks'])): ?>
        <div class="box notes">
            <div class="box-title">Notes / Remarks</div>
            <?php if (! empty($h['notes'])): ?><div><?= esc($h['notes']) ?></div><?php endif ?>
            <?php if (! empty($h['remarks'])): ?><div><?= nl2br(esc($h['remarks'])) ?></div><?php endif ?>
        </div>
    <?php endif ?>

    <div class="signatures">
        <div class="signature"><div class="line">Prepared By</div></div>
        <div class="signature"><div class="line">Checked By</div></div>
        <div class="signature"><div class="line">Approved By</div></div>
    </div>
</div>
<script>
    window.addEventListener('load', function () {
        if (new URLSearchParams(window.location.search).get('auto') === '1') {
            window.print();
        }
    });
</script>
</body>
</html>
