# Cash/Bank Integrity Hardening

Tanggal update: 2026-06-22

## 1. Tujuan

Menjaga Cash/Bank subledger, bank statement, reconciliation, dan General Ledger tetap konsisten pada request normal, direct endpoint, kegagalan GL, dan posting paralel.

## 2. Aturan Posting Entry

| Rule | Perilaku |
|---|---|
| Entry number | Wajib dan unik per company |
| Entry type | Hanya `cash_in`, `cash_out`, `bank_in`, atau `bank_out` |
| Amount | Harus lebih besar dari nol |
| Counter GL account | Wajib agar setiap entry bernilai menghasilkan jurnal |
| Currency | Harus sama dengan currency Cash/Bank account |
| Account balance | Row account dikunci `FOR UPDATE` sebelum saldo dihitung |
| GL failure | Entry dan perubahan saldo di-rollback |

## 3. Statement Adjustment

Adjustment yang dibuat dari unmatched bank statement line diproses oleh `BankStatementImportService`. Source line menentukan company, site, bank, tanggal, direction, amount, currency, dan reference. Cash/Bank entry, GL, line matching, serta refresh status import berada dalam satu transaksi database.

Statement yang sudah `reconciled`, line yang sudah matched, line zero-value, atau line di luar active company/site ditolak pada service layer.

## 4. Reconciliation

- Reconciliation number wajib dan unik per company.
- Jika memakai statement import, statement date, closing balance, dan reference diambil dari source import.
- Semua statement line wajib matched satu-ke-satu dengan bank entry yang berbeda.
- Entry harus posted, bertipe bank, belum direconcile, dan sesuai company/site/account.
- Statement import dan selected entries dikunci selama proses posting.
- `difference_amount` wajib nol sebelum reconciliation dapat diposting.
- Periode Cash/Bank harus open.

## 5. File Utama

| File | Perubahan |
|---|---|
| `app/Services/Support/CashBankIntegrityGuard.php` | Validasi entry, currency, tanggal, dan reconciliation difference |
| `app/Services/Finance/CashBankService.php` | Mandatory GL, account row lock, unique number, dan currency guard |
| `app/Services/Finance/BankStatementImportService.php` | Atomic statement adjustment dan reconciled-status guard |
| `app/Services/Finance/BankReconciliationService.php` | Source-authoritative, site/status guard, row locks, dan zero-difference rule |
| `app/Controllers/Finance/CashBankController.php` | Controller tipis; statement adjustment dipindah ke service |
| `tests/unit/CashBankIntegrityGuardTest.php` | Unit test business guard |

## 6. Database

Tidak ada migration atau SQL hosting baru. Patch memakai field dan unique index Cash/Bank yang sudah tersedia.

## 7. UAT Minimum

Jalankan skenario pada bagian Cash/Bank di `docs/16-core-uat-status-checklist.md`. UAT dianggap pass hanya jika Cash/Bank Entry, account balance, Bank Statement, Bank Reconciliation, dan GL Entries menunjukkan nilai yang konsisten.
