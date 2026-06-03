# AI/OCR Pending Backlog

AI/OCR development is intentionally paused while the core ERP transaction engine is developed.

## Current AI/OCR Status

Implemented foundation:

- Document upload and secure storage
- Duplicate document hash checking
- OCR service interface
- Local command OCR engine for Tesseract and pdftotext
- Rule-based extraction parser
- Human review screen
- Convert reviewed extraction to PO/SO
- OCR diagnostics page
- Printable sample PO/SO pages

## Pending AI/OCR Work

Resume later after ERP core is stable:

1. Improve OCR provider configuration through `.env`.
2. Add OpenAI Vision / LLM extraction provider.
3. Add scanned PDF to image conversion before Tesseract.
4. Add document type specific extraction profiles.
5. Add confidence per field and per line item.
6. Add review queue filters by document type/status/company/site.
7. Add conversion mapping for invoice, delivery order, vendor invoice, and customer order.
8. Add background queue worker for OCR processing.
9. Add retry and failed processing dashboard.
10. Add side-by-side document preview during review.

## Reason for Pause

The ERP transaction engine must exist before AI/OCR can provide real business value. The focus moves to:

- Inventory stock ledger
- Purchase receiving
- Sales delivery
- Invoice lifecycle
- Approval workflow
- GL posting
- Costing
