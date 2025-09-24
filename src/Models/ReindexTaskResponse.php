<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Models;

readonly class ReindexTaskResponse
{
    public function __construct(
        public string $status,
        public string $message,
        public string $taskId,
        public string $statusUrl
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'task_id' => $this->taskId,
            'status_url' => $this->statusUrl,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            message: $data['message'],
            taskId: $data['task_id'],
            statusUrl: $data['status_url']
        );
    }
}