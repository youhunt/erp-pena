# Sales E2E Audit Checklist

Tanggal: 2026-06-22

Checklist ini dipakai untuk memastikan flow Sales E2E sudah lengkap dari SO sampai GL.

## 1. Sales Order

- SO status approved/reserved/partial_delivered.
- Line item punya outstanding quantity.
- Customer valid.
- Price dan UoM valid.

## 2. Delivery Order

Expected setelah post delivery:

- Delivery status: `posted` atau `invoiced`.
- Delivered quantity sesuai SO.
- Stock movement muncul di setiap line.
- Stock card menunjukkan movement out untuk item yang dikirim.
- Jika COGS posting aktif, Delivery detail punya COGS GL Entry.

Catatan:

Jika Stock Movement ada tetapi COGS GL Entry kosong, AR flow masih bisa lanjut untuk UAT, tetapi inventory accounting/HPP perlu dicek.

## 3. AR Invoice

Expected setelah create invoice dari DO:

- Source: `sales_delivery`.
- DO No terisi.
- SO No terisi.
- Invoice line sama dengan delivery line.
- GL Entry invoice terbentuk.
- Journal balanced:
  - Debit: Accounts Receivable.
  - Credit: Sales Revenue.

## 4. AR Receipt

Expected setelah receipt:

- Invoice status paid/settled.
- Outstanding = 0.
- Receipt History muncul di invoice detail.
- Receipt status posted.
- Cash/Bank code terisi.
- GL receipt terbentuk.

## 5. GL Audit

Minimal GL yang dicek:

1. Sales Invoice GL:
   - Debit Accounts Receivable.
   - Credit Sales Revenue.
   - Total debit = total credit.

2. AR Receipt / Cash Bank GL:
   - Debit Cash/Bank.
   - Credit Accounts Receivable.
   - Total debit = total credit.

3. COGS GL, jika inventory accounting aktif:
   - Debit Cost of Goods Sold.
   - Credit Inventory.
   - Total debit = total credit.

## 6. Screenshot Evidence dari UAT

Checklist evidence yang perlu disimpan:

- Delivery Order Detail.
- AR Invoice Detail.
- Receipt History.
- Sales Invoice GL Entry Detail.
- Cash/Bank GL Entry Detail.
- Stock Card item terkait.

## 7. Status dari Screenshot UAT 2026-06-22

Terlihat sudah OK:

- Delivery Order `DO/202606/0001` status invoiced.
- AR Invoice `SI/202606/0001` sudah paid/outstanding 0.
- Receipt `ARR/202606/0001` posted.
- Sales Invoice GL `GL-SI/202606/0001` balanced Debit/Credit 40,937.50.

Masih perlu ditindaklanjuti:

- Delivery COGS GL Entry masih kosong di screenshot.
- Perlu validasi Stock Card dan/atau implementasi posting COGS otomatis jika inventory accounting diwajibkan.
