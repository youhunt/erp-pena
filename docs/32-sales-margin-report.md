# Sales Margin Report

Tanggal: 2026-06-22

Dokumen ini menjelaskan laporan margin penjualan untuk melihat banyak invoice sekaligus.

## 1. File SQL

```text
database/hosting/2026-06-22_report_sales_margin_summary.sql
```

## 2. Tujuan

Report ini dipakai untuk melihat:

- invoice penjualan,
- delivery order terkait,
- nilai invoice,
- nilai COGS dari GL Delivery,
- gross profit/loss,
- gross margin percentage,
- status margin.

## 3. Cara Pakai

Ubah tanggal di bagian atas file SQL:

```sql
SET @date_from := '2026-06-01';
SET @date_to := '2026-06-30';
```

Lalu jalankan SQL di phpMyAdmin atau MySQL client.

## 4. Output

Script menghasilkan tiga output:

### A. Sales margin detail by invoice

Menampilkan daftar invoice dan margin masing-masing transaksi.

Kolom penting:

- invoice_no
- customer_code
- customer_name
- delivery_no
- invoice_amount
- cogs_amount
- gross_profit_loss
- gross_margin_pct
- margin_status

### B. Sales margin summary by status

Rekap total per status margin.

Status:

- PROFIT_OK
- LOSS_REVIEW_COST_OR_PRICE
- MISSING_COGS_GL
- MISSING_DELIVERY

### C. Top loss invoices

Menampilkan daftar transaksi rugi terbesar dari yang paling minus.

## 5. Cara Membaca

Jika `margin_status = PROFIT_OK`, transaksi profit secara gross margin.

Jika `margin_status = LOSS_REVIEW_COST_OR_PRICE`, COGS lebih besar dari nilai invoice. Perlu cek harga jual, avg cost, atau master item cost.

Jika `margin_status = MISSING_COGS_GL`, Delivery belum punya jurnal HPP.

Jika `margin_status = MISSING_DELIVERY`, Invoice tidak terhubung ke Delivery.

## 6. Catatan UAT

Untuk transaksi UAT `SI/202606/0001`, sistem sudah menunjukkan flow Sales E2E valid, tetapi margin loss karena COGS lebih besar dari invoice. Ini bukan error posting, melainkan bahan audit harga/cost.
