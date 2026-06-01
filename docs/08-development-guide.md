# Development Guide

## Coding Standards

- Keep controllers thin.
- Put business logic in services.
- Use models for database access.
- Add migrations for schema changes.
- Add seeders for baseline data.
- Use validation rules for form input.
- Use database transactions for important posting flows.
- Keep uploads outside `public/`.
- Use `company_id` and `site_id` filters for tenant-scoped data.

## Adding a Module

1. Add or update migration.
2. Add model.
3. Add service for business logic.
4. Add controller with validation.
5. Add views.
6. Add permission to `AuthGroups`.
7. Add menu item in `PenaErpSeeder`.
8. Add documentation and tests.

## Adding an OCR Provider

1. Implement `App\Services\Ai\OcrProviderInterface`.
2. Return `OcrResult`.
3. Register provider through config or factory.
4. Keep credentials in `.env`.
5. Add provider-specific error handling and logs.

## Adding an AI Extraction Provider

1. Implement `App\Services\Ai\AiExtractionProviderInterface`.
2. Return `ExtractionResult`.
3. Map extracted fields to ERP target fields through `document_field_mappings`.
4. Store confidence scores and raw structured JSON.

## Git Workflow

Recommended commit stages:

- `init codeigniter erp foundation`
- `add shield authentication and rbac`
- `add database migrations from data dictionary`
- `add ai ocr document processing module`
- `add skote dashboard layout`
