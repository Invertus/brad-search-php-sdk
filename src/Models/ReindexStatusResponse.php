<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Models;

readonly class ReindexStatusResponse
{
    public function __construct(
        public string $taskId,
        public string $status,
        public string $message,
        public ?array $progress = null,
        public ?array $result = null,
        public ?string $error = null,
        public ?string $startedAt = null,
        public ?string $updatedAt = null,
        public ?string $completedAt = null,
        public ?string $failedAt = null
    ) {}

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    public function toArray(): array
    {
        return array_filter([
            'task_id' => $this->taskId,
            'status' => $this->status,
            'message' => $this->message,
            'progress' => $this->progress,
            'result' => $this->result,
            'error' => $this->error,
            'started_at' => $this->startedAt,
            'updated_at' => $this->updatedAt,
            'completed_at' => $this->completedAt,
            'failed_at' => $this->failedAt,
        ], fn($value) => $value !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            taskId: $data['task_id'],
            status: $data['status'],
            message: $data['message'],
            progress: $data['progress'] ?? null,
            result: $data['result'] ?? null,
            error: $data['error'] ?? null,
            startedAt: $data['started_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            completedAt: $data['completed_at'] ?? null,
            failedAt: $data['failed_at'] ?? null
        );
    }
}