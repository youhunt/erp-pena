# Module Workflow

## Setup

1. Super Admin creates company.
2. Company Admin creates sites, departments, warehouses, locations, currencies, tax rates, UoM, transaction codes, and prefix codes.
3. User access is assigned per company and site.

## Sales

1. Maintain customer master and terms.
2. Create sales order.
3. Approve order when approval workflow applies.
4. Allocate stock and create delivery order in later stages.
5. Generate sales invoice.

## Purchase

1. Maintain supplier master and terms.
2. Create purchase order.
3. Approve purchase order.
4. Receive goods.
5. Match vendor invoice in later stages.

## Inventory

1. Maintain items, UoM, warehouses, locations, and batches.
2. Post inventory in/out through `inventory_movements`.
3. Future stages will add stock ledger and stock balance materialization.

## General Ledger and Finance

1. Define chart of accounts and GL books in later module expansion.
2. Post journals and recurring journals.
3. Link sales invoice, vendor invoice, and cash bank transactions to GL.

## Production

1. Define BOM, work center, and routing.
2. Create work order.
3. Issue materials and receive finished goods.

## POS

1. Define POS terminal settings.
2. Capture sales transaction.
3. Post stock and invoice/cash receipt.

## Current Implementation Boundary

This stage creates the foundation tables and first pages only. Full CRUD, approvals, stock ledger, accounting posting, and transaction conversion will be expanded in the next development stages.
