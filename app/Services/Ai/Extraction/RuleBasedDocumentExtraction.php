<?php

namespace App\Services\Ai\Extraction;

final class RuleBasedDocumentExtraction implements AiExtractionInterface
{
    public function extractFields(string $ocrText, array $documentContext = []): array
    {
        $text = $this->normalizeText($ocrText);
        $documentType = $this->detectDocumentType($text);
        $fields = $this->extractHeaderFields($text, $documentType);
        $lineItems = $this->extractLineItems($text);
        $confidence = $this->score($fields, $lineItems, $text);

        return [
            'provider' => 'rule_based_parser',
            'document_type' => $documentType,
            'fields' => $fields,
            'line_items' => $lineItems,
            'confidence' => $confidence,
            'raw_response' => [
                'text_length' => strlen($ocrText),
                'context' => $documentContext,
                'parser_version' => 'v1',
            ],
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function detectDocumentType(string $text): ?string
    {
        $upper = strtoupper($text);

        if (str_contains($upper, 'PURCHASE ORDER') || preg_match('/\bPO\s*(NO|NUMBER|#|:)\b/i', $text)) {
            return 'purchase_order';
        }

        if (str_contains($upper, 'SALES ORDER') || str_contains($upper, 'CUSTOMER ORDER') || preg_match('/\bSO\s*(NO|NUMBER|#|:)\b/i', $text)) {
            return 'sales_order';
        }

        if (str_contains($upper, 'INVOICE')) {
            return 'invoice';
        }

        if (str_contains($upper, 'DELIVERY ORDER') || preg_match('/\bDO\s*(NO|NUMBER|#|:)\b/i', $text)) {
            return 'delivery_order';
        }

        return null;
    }

    private function extractHeaderFields(string $text, ?string $documentType): array
    {
        $fields = [];

        $docNo = $this->matchFirst($text, [
            '/(?:PO|Purchase Order)\s*(?:No|Number|#)?\s*[:\-]?\s*([A-Z0-9\-\/\.]+)/i',
            '/(?:SO|Sales Order|Customer Order)\s*(?:No|Number|#)?\s*[:\-]?\s*([A-Z0-9\-\/\.]+)/i',
            '/(?:Document|Dokumen|Nomor Dokumen|Nomor)\s*(?:No|Number|#)?\s*[:\-]?\s*([A-Z0-9\-\/\.]+)/i',
        ]);

        $docDate = $this->matchFirst($text, [
            '/(?:PO|SO|Order|Document|Tanggal|Date)\s*(?:Date)?\s*[:\-]?\s*([0-9]{4}\-[0-9]{2}\-[0-9]{2})/i',
            '/(?:PO|SO|Order|Document|Tanggal|Date)\s*(?:Date)?\s*[:\-]?\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i',
        ]);

        $supplier = $this->matchFirst($text, [
            '/(?:Supplier|Vendor|Nama Supplier|Nama Vendor)\s*[:\-]?\s*(.+)/i',
        ]);

        $customer = $this->matchFirst($text, [
            '/(?:Customer|Buyer|Pelanggan|Nama Customer|Nama Pelanggan)\s*[:\-]?\s*(.+)/i',
        ]);

        $currency = $this->matchFirst($text, [
            '/(?:Currency|Mata Uang)\s*[:\-]?\s*([A-Z]{3})/i',
        ]) ?: 'IDR';

        if ($documentType === 'purchase_order') {
            $fields['po_no'] = $docNo;
            $fields['po_date'] = $this->normalizeDate($docDate);
            $fields['supplier_name'] = $supplier ?: $this->partyAfterKeyword($text, 'SUPPLIER');
        } elseif ($documentType === 'sales_order') {
            $fields['so_no'] = $docNo;
            $fields['so_date'] = $this->normalizeDate($docDate);
            $fields['customer_name'] = $customer ?: $this->partyAfterKeyword($text, 'CUSTOMER');
        } else {
            $fields['document_no'] = $docNo;
            $fields['document_date'] = $this->normalizeDate($docDate);
            if ($supplier !== null) {
                $fields['supplier_name'] = $supplier;
            }
            if ($customer !== null) {
                $fields['customer_name'] = $customer;
            }
        }

        $fields['currency_code'] = strtoupper($currency);

        $total = $this->matchFirst($text, [
            '/(?:Grand Total|Total Amount|Total)\s*[:\-]?\s*(?:IDR|Rp)?\s*([0-9\.,]+)/i',
        ]);
        if ($total !== null) {
            $fields['total_amount'] = $this->number($total);
        }

        return array_filter($fields, static fn ($value) => $value !== null && $value !== '');
    }

    private function extractLineItems(string $text): array
    {
        $items = [];
        $lines = preg_split('/\n+/', $text) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! preg_match('/\d/', $line)) {
                continue;
            }

            if (preg_match('/^\s*\d+\s+[|\-]?\s*([A-Z0-9\-_.]+)\s+(.+?)\s+(\d+(?:[\.,]\d+)?)\s+([A-Z]{2,10})\s+([0-9\.,]+)(?:\s+([0-9\.,]+))?(?:\s+([0-9\.,]+))?(?:\s+([0-9\.,]+))?\s*$/i', $line, $m)) {
                $items[] = [
                    'item_code' => $m[1],
                    'item_name' => trim($m[2]),
                    'qty' => $this->number($m[3]),
                    'uom_code' => strtoupper($m[4]),
                    'unit_price' => $this->number($m[5]),
                    'discount_amount' => isset($m[6]) ? $this->number($m[6]) : 0,
                    'tax_amount' => isset($m[7]) ? $this->number($m[7]) : 0,
                    'line_total' => isset($m[8]) ? $this->number($m[8]) : null,
                ];
                continue;
            }

            if (preg_match('/([A-Z0-9\-_.]+)\s+(.+?)\s+(\d+(?:[\.,]\d+)?)\s+([A-Z]{2,10})\s+([0-9\.,]+)/i', $line, $m)) {
                $items[] = [
                    'item_code' => $m[1],
                    'item_name' => trim($m[2]),
                    'qty' => $this->number($m[3]),
                    'uom_code' => strtoupper($m[4]),
                    'unit_price' => $this->number($m[5]),
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ];
            }
        }

        return $items;
    }

    private function matchFirst(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim((string) ($matches[1] ?? ''));
            }
        }

        return null;
    }

    private function partyAfterKeyword(string $text, string $keyword): ?string
    {
        $lines = preg_split('/\n+/', strtoupper($text)) ?: [];
        foreach ($lines as $index => $line) {
            if (str_contains($line, $keyword) && isset($lines[$index + 1])) {
                return trim($lines[$index + 1]);
            }
        }

        return null;
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime(str_replace('/', '-', $value));

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function number(string|float|int|null $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9,\.\-]/', '', (string) $value) ?? '0';
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace(',', '', $clean);
        } elseif (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        }

        return (float) $clean;
    }

    private function score(array $fields, array $lineItems, string $text): float
    {
        $score = 20.0;
        if ($text !== '') {
            $score += 20.0;
        }
        if (isset($fields['po_no']) || isset($fields['so_no']) || isset($fields['document_no'])) {
            $score += 20.0;
        }
        if (isset($fields['po_date']) || isset($fields['so_date']) || isset($fields['document_date'])) {
            $score += 15.0;
        }
        if ($lineItems !== []) {
            $score += 25.0;
        }

        return min(100.0, $score);
    }
}
