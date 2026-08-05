<?php

namespace App\Services\Shipping\Xperta;

final class XpertaGuideResponseNormalizer
{
    public function normalize(array $response): array
    {
        $data = $this->apiData($response);
        $petition = data_get($data, 'data.labelPetitionResult');
        $element = is_array($petition) ? (array) data_get($petition, 'elements.0', []) : [];
        $messages = (array) data_get($data, 'message', []);
        $requestNumber = null;
        foreach ($messages as $message) {
            if (is_string($message) && preg_match('/\bID\s+(\d+)\b/i', $message, $matches)) {
                $requestNumber = $matches[1];
                break;
            }
        }

        $document = data_get($data, 'data.data');
        return [
            'success' => data_get($data, 'success') === true,
            'provider_status' => (int) data_get($petition, 'result.code', -1) === 0 ? 'GENERADA' : 'ERROR',
            'result_code' => data_get($petition, 'result.code'),
            'waybill' => $this->string($element['wayBill'] ?? null),
            'tracking' => $this->string($element['trackingCode'] ?? null),
            'provider_reference' => $this->string(data_get($petition, 'result.description')),
            'provider_request_number' => $requestNumber,
            'destination_address' => data_get($petition, 'destinationAddress'),
            'elements_count' => data_get($petition, 'elementsCount'),
            'messages' => $messages,
            'document' => $document,
            'document_type' => $this->documentType($document),
        ];
    }

    public function isRecoverable(array $normalized): bool
    {
        return $normalized['success'] === true
            && $normalized['provider_status'] === 'GENERADA'
            && $normalized['waybill'] !== null
            && $normalized['tracking'] !== null;
    }

    private function apiData(array $response): array
    {
        if (array_key_exists('http_status', $response) && is_array($response['data'] ?? null)) {
            return $response['data'];
        }
        return $response;
    }

    private function documentType(mixed $document): string
    {
        if ($document === '[CONTENT_OMITTED]') return 'omitted';
        if (!is_string($document) || trim($document) === '') return 'none';
        if (filter_var($document, FILTER_VALIDATE_URL)) return 'url';
        $encoded = preg_replace('#^data:application/pdf;base64,#i', '', trim($document));
        $binary = base64_decode($encoded, true);
        if ($binary !== false && str_starts_with($binary, '%PDF-')) return 'pdf_base64';
        if ($binary !== false) return 'base64_other';
        return 'other';
    }

    private function string(mixed $value): ?string
    {
        return (is_string($value) || is_numeric($value)) && trim((string) $value) !== ''
            ? trim((string) $value)
            : null;
    }
}
