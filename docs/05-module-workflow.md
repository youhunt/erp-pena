# Module Workflow

## Setup

1. Super Admin creates company.
2. Company Admin creates sites, departments, warehouses, locations, currencies, tax rates, UoM, transaction codes, and prefix codes.
3. User access is assigned per company and site.

## Sales

1. Maintain customer master and terms.
2. Create sales order.
3. Approve order when approval workflow applies.
4. Create allocation order and allocation lines to reserve stock.
5. Create delivery order from allocated/reserved stock.
6. Generate sales invoice from delivery order.
7. Open AR receivable for customer collection.
8. Post customer receipt to reduce AR outstanding.

## Purchase

1. Maintain supplier master and terms.
2. Create purchase order.
3. Approve purchase order.
4. Receive goods.
5. Create purchase invoice from receipt.
6. Open AP payable for supplier payment.
7. Post supplier payment to reduce AP outstanding.

## Inventory

1. Maintain items, UoM, warehouses, locations, and batches.
2. Post inventory in/out through `inventory_movements`.
3. Future stages will add stock ledger and stock balance materialization.

## General Ledger and Finance

1. Define chart of accounts and GL books in later module expansion.
2. Post journals and recurring journals.
3. Link sales invoice, vendor invoice, and cash bank transactions to GL.
4. AP payment and AR receipt are baseline settlement points before final auto-journal integration.

## Production

1. Define BOM, work center, and routing.
2. Maintain work center machine and cost detail.
3. Create work order.
4. Issue materials and receive finished goods.

## POS

1. Define POS terminal settings.
2. Capture sales transaction.
3. Post stock and invoice/cash receipt.

## Current Implementation Boundary

This stage has moved beyond foundation tables into transaction core. Purchase Order, Purchase Receipt, Purchase Invoice/AP Payable/AP Payment, Sales Order, Delivery Order, Sales Invoice/AR Receivable/AR Receipt, inventory stock core, and production work order core are now available as baseline flows. Remaining work is approval enforcement, stock-card reporting, journal generation, document PDF print, and period close.
