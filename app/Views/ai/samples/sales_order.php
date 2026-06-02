<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sample Sales Order</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;background:#f4f6f8}.page{width:210mm;min-height:297mm;margin:20px auto;background:#fff;padding:18mm;box-shadow:0 8px 30px rgba(0,0,0,.12)}.top{display:flex;justify-content:space-between;border-bottom:3px solid #111;padding-bottom:14px}.brand{font-size:26px;font-weight:700}.title{font-size:30px;font-weight:700;text-align:right}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:24px}.box{border:1px solid #222;padding:12px}.box h3{margin:0 0 10px;font-size:14px;text-transform:uppercase}.meta table,.items{width:100%;border-collapse:collapse}.meta td{padding:4px 0}.label{font-weight:700;width:150px}.items{margin-top:26px}.items th,.items td{border:1px solid #222;padding:8px;font-size:13px}.items th{background:#efefef;text-transform:uppercase}.right{text-align:right}.summary{width:320px;margin-left:auto;margin-top:16px;border-collapse:collapse}.summary td{border:1px solid #222;padding:8px}.summary .total{font-weight:700;background:#efefef}.notes{margin-top:24px;border:1px solid #222;padding:12px;min-height:60px}.actions{width:210mm;margin:20px auto;text-align:right}.btn{display:inline-block;padding:10px 14px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px}@media print{body{background:#fff}.page{box-shadow:none;margin:0;width:auto;min-height:auto}.actions{display:none}}
    </style>
</head>
<body>
<div class="actions"><a href="javascript:window.print()" class="btn">Print / Save PDF</a></div>
<div class="page">
    <div class="top">
        <div>
            <div class="brand">PENA ERP DEMO COMPANY</div>
            <div>Jl. Contoh ERP No. 1, Jakarta</div>
            <div>NPWP: 00.000.000.0-000.000</div>
        </div>
        <div class="title">SALES ORDER</div>
    </div>

    <div class="grid">
        <div class="box meta">
            <h3>Sales Order Info</h3>
            <table>
                <tr><td class="label">SO No</td><td>: SO-OCR-001</td></tr>
                <tr><td class="label">SO Date</td><td>: 2026-06-01</td></tr>
                <tr><td class="label">Currency</td><td>: IDR</td></tr>
                <tr><td class="label">Payment Terms</td><td>: NET 14</td></tr>
            </table>
        </div>
        <div class="box meta">
            <h3>Customer</h3>
            <table>
                <tr><td class="label">Customer</td><td>: PT Customer Contoh</td></tr>
                <tr><td class="label">Address</td><td>: Jl. Pelanggan Raya No. 20</td></tr>
                <tr><td class="label">Phone</td><td>: 021-654321</td></tr>
            </table>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>No</th><th>Item Code</th><th>Description</th><th class="right">Qty</th><th>UoM</th><th class="right">Unit Price</th><th class="right">Discount</th><th class="right">Tax</th><th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>1</td><td>ITEM-001</td><td>Produk Contoh A</td><td class="right">5</td><td>PCS</td><td class="right">150000</td><td class="right">0</td><td class="right">82500</td><td class="right">832500</td></tr>
            <tr><td>2</td><td>ITEM-002</td><td>Produk Contoh B</td><td class="right">3</td><td>PCS</td><td class="right">200000</td><td class="right">50000</td><td class="right">60500</td><td class="right">610500</td></tr>
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Subtotal</td><td class="right">1350000</td></tr>
        <tr><td>Discount</td><td class="right">50000</td></tr>
        <tr><td>Tax</td><td class="right">143000</td></tr>
        <tr class="total"><td>Total</td><td class="right">1443000</td></tr>
    </table>

    <div class="notes">
        <strong>Notes:</strong><br>
        Contoh Sales Order untuk testing OCR PENA ERP. Screenshot halaman ini atau print menjadi PDF lalu upload ke AI/OCR Documents.
    </div>
</div>
</body>
</html>
