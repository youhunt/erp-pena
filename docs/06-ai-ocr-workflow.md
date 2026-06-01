# AI/OCR Workflow

## Supported Document Targets

The architecture is prepared for:

- Purchase Order
- Customer Order
- Sales Order
- Invoice
- Delivery Order
- Similar ERP documents

## Processing Flow

1. User uploads PDF or image.
2. File is stored under `writable/secure_uploads/erp-documents`.
3. `document_uploads` stores original name, stored path, MIME type, file size, hash, company, site, and status.
4. Duplicate check uses `company_id + sha256_hash`.
5. OCR provider extracts raw text.
6. AI provider detects document type and extracts fields.
7. `document_extractions` stores raw OCR text, structured JSON, provider names, confidence score, and review status.
8. `document_field_mappings` stores source field, target table, target field, extracted value, corrected value, and confidence score.
9. User reviews and corrects the extraction.
10. Reviewed document is converted to an ERP transaction.
11. `document_transaction_links` stores the relation from original document to created transaction.
12. `document_processing_logs` and `audit_trails` store processing and user activity.

## Provider Abstraction

The app defines:

- `OcrProviderInterface`
- `AiExtractionProviderInterface`
- `OcrResult`
- `ExtractionResult`

This allows swapping between Tesseract, PaddleOCR, Google Vision, OpenAI Vision/LLM extraction, or another provider without changing controller logic.

## Security Rules

- Accept only PDF and image MIME types.
- Store files outside `public/`.
- Do not expose stored paths directly.
- Use hash-based duplicate detection.
- Keep provider API keys in `.env`.
- Log processing errors without leaking secrets.
