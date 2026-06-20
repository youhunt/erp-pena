# PENA ERP Transaction Status Guard

Dokumen ini mendefinisikan aturan status untuk transaksi inti. Service layer adalah sumber kebenaran. Controller melakukan pre-check untuk respons yang lebih cepat, sedangkan view hanya menyembunyikan action yang tidak relevan.

## 1. Prinsip

- Dokumen baru selalu dibuat sebagai `draft`, walaupun request mengirim status lain.
- Hanya dokumen `draft` yang boleh diubah bebas.
- Setiap action hanya menerima status asal yang telah ditentukan.
- Action replay, direct URL, dan request POST manual tetap ditolak oleh service.
- Period close guard tetap berlaku bersama status guard.
- Perubahan ini tidak memerlukan tabel atau migration baru.

## 2. Sales Order

| Status | Action yang Diizinkan | Guard Utama |
|---|---|---|
| `draft` | submit, cancel | Create selalu menghasilkan draft |
| `submitted` | approve, cancel | Tidak boleh diedit bebas |
| `approved` | reserve atau delivery sesuai flow | Tidak boleh diedit/cancel bebas |
| `partial_reserved` | reserve lanjutan | Tidak boleh submit/approve ulang |
| `reserved`, `partial_delivered` | delivery sesuai outstanding | Tidak boleh edit atau reopen |
| `delivered`, `invoiced` | action downstream yang sesuai | Tidak boleh cancel/reprocess |
| `cancelled` | reopen sebagai draft | Reopen hanya melalui action yang tersedia |

## 3. Purchase Order

| Status | Action yang Diizinkan | Guard Utama |
|---|---|---|
| `draft` | edit, submit, cancel | Edit ditolak jika pernah menerima quantity |
| `submitted` | approve, cancel | Edit melalui URL langsung ditolak |
| `approved`, `partial_received` | purchase receipt | Tidak boleh edit/cancel bebas |
| `received` | close | Tidak boleh receive ulang jika tidak ada outstanding |
| `closed`, `cancelled` | tidak ada action proses ulang | Belum ada fitur reopen PO |

## 4. Fulfillment

| Dokumen | Status | Action yang Diizinkan |
|---|---|---|
| Delivery Order | `posted` | create sales invoice atau reverse jika belum ada invoice aktif |
| Delivery Order | `invoiced` | tidak boleh reverse atau invoiced ulang |
| Delivery Order | `reversed` | tidak boleh diproses ulang |
| Purchase Receipt | `posted` | create purchase invoice atau reverse jika belum ada invoice aktif |
| Purchase Receipt | `invoiced` | tidak boleh reverse atau invoiced ulang |
| Purchase Receipt | `reversed` | tidak boleh diproses ulang |

Nomor Delivery Order dan Purchase Receipt yang sudah tersimpan ditolak sebelum stock posting dijalankan kembali.

## 5. Invoice dan Settlement

| Dokumen/Status | Action yang Diizinkan | Guard Utama |
|---|---|---|
| Invoice `open` | payment/receipt, cancel jika belum ada settlement posted | Cancel memeriksa settlement berstatus posted |
| Invoice `partial` | payment/receipt lanjutan | Tidak boleh cancel sebelum settlement dibatalkan |
| Invoice `paid` | tidak ada payment/cancel tambahan | Outstanding dan status diperiksa di service |
| Invoice `cancelled` | tidak ada action proses ulang | Payment/receipt baru ditolak |
| Settlement `posted` | cancel | Cancellation melakukan reversal dan recalculate invoice |
| Settlement `cancelled` | tidak ada action ulang | Cancel kedua ditolak |

## 6. Pesan Error

Pesan penolakan harus menyebut action yang valid atau status saat ini, misalnya:

- `Only draft purchase order can be edited. Current status: approved.`
- `Only posted delivery order can be invoiced. Current status: reversed.`
- `Sales invoice has a posted receipt. Cancel the receipt first.`
- `A/P payment has already been cancelled.`

## 7. UAT Minimum

Gunakan bagian Transaction Status Guard pada `docs/16-core-uat-status-checklist.md`. Uji melalui tombol normal dan request URL langsung untuk memastikan service tetap menolak action yang tidak valid.
