<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Models;

class BulkOperationResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly int $totalOperations,
        public readonly int $successfulOperations,
        public readonly int $failedOperations,
        public readonly int $processingTimeMs,
        public readonly array $results
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            status: $response['status'],
            message: $response['message'],
            totalOperations: $response['total_operations'],
            successfulOperations: $response['successful_operations'],
            failedOperations: $response['failed_operations'],
            processingTimeMs: $response['processing_time_ms'],
            results: $response['results']
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isPartialSuccess(): bool
    {
        return $this->status === 'partial';
    }

    public function hasFailures(): bool
    {
        return $this->failedOperations > 0;
    }

    public function getFailedResults(): array
    {
        return array_filter($this->results, fn($result) => $result['status'] === 'error');
    }

    public function getSuccessfulResults(): array
    {
        return array_filter($this->results, fn($result) => $result['status'] === 'success');
    }
}