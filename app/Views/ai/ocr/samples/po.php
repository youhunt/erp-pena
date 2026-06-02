<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Order OCR Sample</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111;margin:40px;background:#fff}.doc{max-width:900px;margin:auto;border:1px solid #ddd;padding:36px}.header{display:flex;justify-content:space-between;border-bottom:3px solid #222;padding-bottom:18px;margin-bottom:24px}.title{font-size:32px;font-weight:700}.meta td{padding:4px 0 4px 16px}.section{margin-top:22px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.box{border:1px solid #ddd;padding:14px;min-height:82px}table.items{width:100%;border-collapse:collapse;margin-top:14px}table.items th,table.items td{border:1px solid #999;padding:8px;font-size:13px}table.items th{background:#f2f2f2}.right{text-align:right}.totals{width:320px;margin-left:auto;margin-top:18px}.totals td{padding:6px 8px;border-bottom:1px solid #ddd}.total{font-weight:700;font-size:18px}@media print{body{margin:0}.doc{border:0}}
    </style>
</head>
<body>
<div class="doc">
    <div class="header">
        <div>
            <div class="title">PURCHASE ORDER</div>
            <div>PENA ERP Demo Company</div>
            <div>Jl. ERP No. 1, Jakarta</div>
        </div>
        <table class="meta">
            <tr><td><strong>PO No</strong></td><td>: PO-OCR-001</td></tr>
            <tr><td><strong>PO Date</strong></td><td>: 2026-06-01</td></tr>
            <tr><td><strong>Currency</strong></td><td>: IDR</td></tr>
        </table>
    </div>

    <div class="grid">
        <div class="box">
            <strong>Supplier</strong><br>
            PT Supplier Contoh<br>
            Kawasan Industri Demo Blok A1<br>
            Bekasi, Indonesia
        </div>
        <div class="box">
            <strong>Ship To</strong><br>
            Gudang Utama PENA ERP<br>
            Jl. Warehouse No. 10<br>
            Tangerang, Indonesia
        </div>
    </div>

    <div class="section">
        <table class="items">
            <thead>
            <tr>
                <th>No</th><th>Item Code</th><th>Description</th><th class="right">Qty</th><th>UoM</th><th class="right">Unit Price</th><th class="right">Discount</th><th class="right">Tax</th><th class="right">Total</th>
            </tr>
            </thead>
            <tbody>
            <tr><td>1</td><td>ITEM-001</td><td>Kertas A4 80gsm</td><td class="right">10</td><td>RIM</td><td class="right">55000</td><td class="right">0</td><td class="right">60500</td><td class="right">610500</td></tr>
            <tr><td>2</td><td>ITEM-002</td><td>Pulpen Hitam</td><td class="right">20</td><td>PCS</td><td class="right">5000</td><td class="right">0</td><td class="right">11000</td><td class="right">111000</td></tr>
            </tbody>
        </table>
    </div>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">650000</td></tr>
        <tr><td>Discount</td><td class="right">0</td></tr>
        <tr><td>Tax</td><td class="right">71500</td></tr>
        <tr class="total"><td>Total</td><td class="right">721500</td></tr>
    </table>

    <div class="section">
        <strong>Notes</strong><br>
        Contoh Purchase Order untuk testing OCR PENA ERP.
    </div>
</div>
</body>
</html>
