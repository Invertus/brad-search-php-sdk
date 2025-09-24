<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Models;

readonly class ReindexCancelResponse
{
    public function __construct(
        public string $status,
        public string $message,
        public string $taskId,
        public ?string $error = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'message' => $this->message,
            'task_id' => $this->taskId,
            'error' => $this->error,
        ], fn($value) => $value !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            message: $data['message'],
            taskId: $data['task_id'],
            error: $data['error'] ?? null
        );
    }
}