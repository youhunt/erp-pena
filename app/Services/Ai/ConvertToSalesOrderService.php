<?php

namespace App\Services\Ai;

use App\Services\AuditLogService;
use App\Services\Sales\SalesOrderService;
use Config\Database;
use RuntimeException;

final class ConvertToSalesOrderService
{
    public function convert(int $documentId, ?int $userId = null): int
    {
        $db = Database::connect();
        $document = $db->table('document_uploads')->where('id', $documentId)->get()->getRowArray();

        if ($document === null) {
            throw new RuntimeException('Document was not found.');
        }

        if (($document['duplicate_of_id'] ?? null) !== null) {
            throw new RuntimeException('Duplicate document cannot be converted. Convert the original document instead.');
        }

        $extraction = $db->table('document_extractions')
            ->where('document_upload_id', $documentId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        if ($extraction === null) {
            throw new RuntimeException('Extraction result was not found. Process OCR/AI first.');
        }

        if (($extraction['review_status'] ?? '') !== 'reviewed') {
            throw new RuntimeException('Document must be reviewed before conversion.');
        }

        $existing = $db->table('sales_orders')->where('source_document_upload_id', $documentId)->get()->getRowArray();
        if ($existing !== null) {
            throw new RuntimeException('This document has already been converted to SO ' . ($existing['so_no'] ?? ('#' . $existing['id'])) . '.');
        }

        $fields = $this->jsonArray($extraction['extracted_fields'] ?? null);
        $lineItems = $this->jsonArray($extraction['line_items'] ?? null);
        $header = $this->mapHeader($document, $fields);
        $lines = $this->mapLines($lineItems);

        $soId = (new SalesOrderService())->create($header, $lines, $userId);

        $db->table('document_processing_logs')->insert([
            'document_upload_id' => $documentId,
            'step' => 'convert_so',
            'status' => 'completed',
            'message' => 'Reviewed document converted to sales order.',
            'context' => json_encode(['sales_order_id' => $soId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditLogService())->log('ai.document', 'document.convert_so', [
            'company_id' => $document['company_id'] ?? null,
            'site_id' => $document['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'sales_orders',
            'record_id' => $soId,
            'record_code' => $header['so_no'] ?? null,
            'description' => 'Reviewed AI/OCR document converted to sales order.',
            'new_values' => [
                'document_upload_id' => $documentId,
                'sales_order_id' => $soId,
                'mapped_header' => $header,
                'mapped_lines' => $lines,
            ],
        ]);

        return $soId;
    }

    private function mapHeader(array $document, array $fields): array
    {
        $soNo = $this->pick($fields, ['so_no', 'sales_order_no', 'customer_order_no', 'document_no', 'nomor_so', 'nomor_dokumen']);
        $soDate = $this->pick($fields, ['so_date', 'sales_order_date', 'customer_order_date', 'document_date', 'tanggal_so', 'tanggal_dokumen']);
        $customerName = $this->pick($fields, ['customer_name', 'buyer_name', 'nama_customer', 'nama_pelanggan']);
        $currency = $this->pick($fields, ['currency_code', 'currency', 'mata_uang']) ?: 'IDR';
        $notes = $this->pick($fields, ['notes', 'remarks', 'catatan']);

        return [
            'company_id' => (int) $document['company_id'],
            'site_id' => isset($document['site_id']) ? (int) $document['site_id'] : null,
            'so_no' => $soNo ?: ('AI-SO-' . date('Ymd') . '-' . $document['id']),
            'so_date' => $this->dateOrToday($soDate),
            'customer_name' => $customerName ?: 'Unknown Customer',
            'currency_code' => strtoupper((string) $currency),
            'status' => 'draft',
            'source_document_upload_id' => (int) $document['id'],
            'notes' => $notes ?: 'Converted from AI/OCR document review.',
        ];
    }

    private function mapLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemCode = $this->pick($item, ['item_code', 'code', 'sku', 'kode_item']);
            $itemName = $this->pick($item, ['item_name', 'description', 'name', 'nama_item', 'deskripsi']);
            $qty = (float) ($this->pick($item, ['qty', 'quantity', 'jumlah']) ?: 0);
            $uom = $this->pick($item, ['uom_code', 'uom', 'unit', 'satuan']) ?: 'PCS';
            $price = (float) ($this->pick($item, ['unit_price', 'price', 'harga', 'harga_satuan']) ?: 0);
            $discount = (float) ($this->pick($item, ['discount_amount', 'discount', 'diskon']) ?: 0);
            $tax = (float) ($this->pick($item, ['tax_amount', 'tax', 'ppn']) ?: 0);

            if ($qty <= 0 && $itemName === null && $itemCode === null) {
                continue;
            }

            $lines[] = [
                'item_code' => $itemCode,
                'item_name' => $itemName ?: ($itemCode ?: 'Unknown Item'),
                'qty' => $qty > 0 ? $qty : 1,
                'uom_code' => $uom,
                'unit_price' => $price,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('No valid line items found in reviewed extraction.');
        }

        return $lines;
    }

    private function pick(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    private function jsonArray(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dateOrToday(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return date('Y-m-d');
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? date('Y-m-d') : date('Y-m-d', $timestamp);
    }
}
