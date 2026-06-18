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
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; padding: 10px 14px; background: #111827; }
        .toolbar button { border: 0; border-radius: 6px; padding: 8px 12px; background: #fff; color: #111827; cursor: pointer; font-weight: 600; }
        .page { width: 210mm; min-height: 297mm; margin: 14px auto; padding: 15mm; background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, .12); }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #111827; padding-bottom: 14px; }
        .company-name { font-size: 20px; font-weight: 800; }
        .company-meta { margin-top: 4px; line-height: 1.45; color: #374151; max-width: 420px; }
        .doc-title { text-align: right; min-width: 210px; }
        .doc-title h1 { margin: 0; font-size: 22px; text-transform: uppercase; }
        .doc-no { margin-top: 8px; font-size: 14px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 18px; }
        .box { border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; }
        .box-title { font-weight: 800; margin-bottom: 8px; text-transform: uppercase; font-size: 11px; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .meta td:first-child { color: #6b7280; width: 118px; }
        .lines { margin-top: 18px; }
        .lines th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 7px 6px; text-align: left; font-size: 11px; }
        .lines td { border: 1px solid #d1d5db; padding: 7px 6px; vertical-align: top; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw { font-weight: 700; }
        .muted { color: #6b7280; }
        .document-footer { display: grid; grid-template-columns: 1fr 46%; gap: 18px; align-items: start; margin-top: 18px; }
        .totals td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .totals tr:last-child td { border-top: 2px solid #111827; border-bottom: 0; font-weight: 800; font-size: 14px; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 30px; }
        .signature { text-align: center; min-height: 86px; }
        .signature .line { margin-top: 58px; border-top: 1px solid #111827; padding-top: 6px; font-weight: 700; }
        @media print { body { background: #fff; } .toolbar { display: none; } .page { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; } }
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
$showAmounts = (bool) ($cfg['show_amounts'] ?? true);
$showLineCommercial = (bool) ($cfg['show_line_commercial'] ?? false);
$showHeaderCommercial = (bool) ($cfg['show_header_commercial'] ?? false);
$showUnitPrice = (bool) ($cfg['show_unit_price'] ?? $showAmounts);
$subtotal = (float) ($h['subtotal_amount'] ?? 0);
$discountPercent = (float) ($h['discount_percent'] ?? 0);
$discountPercentAmount = round($subtotal * $discountPercent / 100, 2);
$manualDiscountAmount = (float) ($h['discount_amount'] ?? 0);
$totalDiscountAmount = round($discountPercentAmount + $manualDiscountAmount, 2);
$freight = (float) ($h['freight_amount'] ?? 0);
$other = (float) ($h['other_amount'] ?? 0);
$special = (float) ($h['special_charge_amount'] ?? 0);
$tax = (float) ($h['vat_amount'] ?? $h['tax_amount'] ?? 0);
$wht = (float) ($h['wht_amount'] ?? 0);
$total = (float) ($h['total_amount'] ?? 0);
$money = static fn (mixed $v): string => number_format((float) $v, 2);
$qty = static fn (mixed $v): string => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
$signatures = $cfg['signatures'] ?? ['Prepared By', 'Checked By', 'Approved By'];
$lineColspan = 5 + ($showUnitPrice ? 1 : 0) + ($showLineCommercial ? 2 : 0) + ($showAmounts ? 1 : 0);
?>
<div class="toolbar"><button type="button" onclick="window.print()">Print / Save PDF</button></div>
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
        <div class="doc-title"><h1><?= esc($cfg['title'] ?? 'Document') ?></h1><div class="doc-no"><?= esc($docNo) ?></div><div class="muted">Status: <?= esc($status) ?></div></div>
    </div>
    <div class="grid">
        <div class="box"><div class="box-title">Document Info</div><table class="meta">
            <tr><td>No</td><td class="fw"><?= esc($docNo) ?></td></tr>
            <tr><td><?= esc($cfg['date_label'] ?? 'Date') ?></td><td><?= esc($docDate ?: '-') ?></td></tr>
            <?php if (! empty($h['delivery_date'])): ?><tr><td>Delivery Date</td><td><?= esc($h['delivery_date']) ?></td></tr><?php endif ?>
            <?php if (! empty($h['arrive_date'])): ?><tr><td>Arrive Date</td><td><?= esc($h['arrive_date']) ?></td></tr><?php endif ?>
            <?php if ($sourceLabel !== '' && $sourceField !== ''): ?><tr><td><?= esc($sourceLabel) ?></td><td><?= esc($h[$sourceField] ?? '-') ?></td></tr><?php endif ?>
            <tr><td>Currency</td><td><?= esc($h['currency_code'] ?? 'IDR') ?></td></tr>
        </table></div>
        <div class="box"><div class="box-title"><?= esc($cfg['partner_label'] ?? 'Partner') ?></div><table class="meta">
            <tr><td>Code</td><td class="fw"><?= esc($partnerCode ?: '-') ?></td></tr>
            <tr><td>Name</td><td><?= esc($partnerName ?: '-') ?></td></tr>
            <tr><td>Terms</td><td><?= esc($h['terms_code'] ?? '-') ?></td></tr>
        </table></div>
    </div>
    <table class="lines"><thead><tr>
        <th class="text-center" style="width:34px;">#</th><th style="width:110px;">Item Code</th><th>Item / Description</th><th class="text-end" style="width:86px;"><?= esc($cfg['qty_label'] ?? 'Qty') ?></th><th style="width:60px;">UoM</th>
        <?php if ($showUnitPrice): ?><th class="text-end" style="width:90px;"><?= esc($cfg['price_label'] ?? 'Price') ?></th><?php endif ?>
        <?php if ($showLineCommercial): ?><th class="text-end" style="width:82px;">Disc</th><th class="text-end" style="width:82px;">Tax</th><?php endif ?>
        <?php if ($showAmounts): ?><th class="text-end" style="width:98px;">Amount</th><?php endif ?>
    </tr></thead><tbody>
    <?php foreach (($lines ?? []) as $line): ?>
        <?php $lineNo = $line['line_no'] ?? $line['po_line'] ?? $line['so_line'] ?? ''; $lineQty = $line[$cfg['qty'] ?? 'qty'] ?? $line['qty'] ?? 0; $linePrice = $line[$cfg['price'] ?? 'unit_price'] ?? $line['unit_price'] ?? $line['unit_cost'] ?? 0; $lineAmount = array_key_exists('line_total', $line) ? (float) $line['line_total'] : ((float) $lineQty * (float) $linePrice); ?>
        <tr>
            <td class="text-center"><?= esc($lineNo) ?></td><td><?= esc($line['item_code'] ?? '-') ?></td>
            <td><div class="fw"><?= esc($line['item_name'] ?? '-') ?></div><?php if (! empty($line['description'])): ?><div class="muted"><?= esc($line['description']) ?></div><?php endif ?><?php if (! empty($line['batch_no'])): ?><div class="muted">Batch: <?= esc($line['batch_no']) ?></div><?php endif ?></td>
            <td class="text-end"><?= esc($qty($lineQty)) ?></td><td><?= esc($line['uom_code'] ?? '-') ?></td>
            <?php if ($showUnitPrice): ?><td class="text-end"><?= esc($money($linePrice)) ?></td><?php endif ?>
            <?php if ($showLineCommercial): ?><td class="text-end"><?= esc($money($line['discount_amount'] ?? 0)) ?></td><td class="text-end"><?= esc($money($line['vat_amount'] ?? $line['tax_amount'] ?? 0)) ?></td><?php endif ?>
            <?php if ($showAmounts): ?><td class="text-end fw"><?= esc($money($lineAmount)) ?></td><?php endif ?>
        </tr>
    <?php endforeach ?>
    <?php if (($lines ?? []) === []): ?><tr><td colspan="<?= (int) $lineColspan ?>" class="text-center muted">No line data.</td></tr><?php endif ?>
    </tbody></table>
    <div class="document-footer">
        <div><?php if (! empty($h['notes']) || ! empty($h['remarks'])): ?><div class="box"><div class="box-title">Notes / Remarks</div><?php if (! empty($h['notes'])): ?><div><?= esc($h['notes']) ?></div><?php endif ?><?php if (! empty($h['remarks'])): ?><div><?= nl2br(esc($h['remarks'])) ?></div><?php endif ?></div><?php endif ?></div>
        <?php if ($showAmounts): ?><table class="totals">
            <tr><td>Subtotal</td><td class="text-end"><?= esc($money($subtotal)) ?></td></tr>
            <?php if ($showHeaderCommercial || $totalDiscountAmount != 0.0): ?>
                <tr>
                    <td>Discount<?= $showHeaderCommercial && $discountPercent != 0.0 ? ' (' . esc(number_format($discountPercent, 4)) . '% + Amount)' : '' ?></td>
                    <td class="text-end"><?= esc($money($totalDiscountAmount)) ?></td>
                </tr>
            <?php endif ?>
            <?php if ($freight != 0.0): ?><tr><td>Freight</td><td class="text-end"><?= esc($money($freight)) ?></td></tr><?php endif ?>
            <?php if ($other != 0.0): ?><tr><td>Other Amount</td><td class="text-end"><?= esc($money($other)) ?></td></tr><?php endif ?>
            <?php if ($special != 0.0): ?><tr><td>Special Charge</td><td class="text-end"><?= esc($money($special)) ?></td></tr><?php endif ?>
            <tr><td><?= $type === 'purchase-order' ? 'VAT' : 'Tax' ?></td><td class="text-end"><?= esc($money($tax)) ?></td></tr>
            <?php if ($wht != 0.0): ?><tr><td>WHT</td><td class="text-end">(<?= esc($money($wht)) ?>)</td></tr><?php endif ?>
            <tr><td>Grand Total</td><td class="text-end"><?= esc($money($total)) ?></td></tr>
        </table><?php endif ?>
    </div>
    <div class="signatures"><?php foreach ($signatures as $signature): ?><div class="signature"><div class="line"><?= esc($signature) ?></div></div><?php endforeach ?></div>
</div>
<script>window.addEventListener('load', function () { if (new URLSearchParams(window.location.search).get('auto') === '1') window.print(); });</script>
</body>
</html>
