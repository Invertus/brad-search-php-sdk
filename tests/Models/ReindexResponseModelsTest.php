<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Models;

use BradSearch\SyncSdk\Models\ReindexTaskResponse;
use BradSearch\SyncSdk\Models\ReindexStatusResponse;
use BradSearch\SyncSdk\Models\ReindexCancelResponse;
use PHPUnit\Framework\TestCase;

class ReindexResponseModelsTest extends TestCase
{
    public function testReindexTaskResponseCreation(): void
    {
        $response = new ReindexTaskResponse(
            status: 'accepted',
            message: 'Reindex operation initiated',
            taskId: 'reindex_abc123def456',
            statusUrl: '/api/v1/sync/reindex/status/reindex_abc123def456'
        );

        $this->assertEquals('accepted', $response->status);
        $this->assertEquals('Reindex operation initiated', $response->message);
        $this->assertEquals('reindex_abc123def456', $response->taskId);
        $this->assertEquals('/api/v1/sync/reindex/status/reindex_abc123def456', $response->statusUrl);
    }

    public function testReindexTaskResponseFromArray(): void
    {
        $data = [
            'status' => 'accepted',
            'message' => 'Reindex operation initiated',
            'task_id' => 'reindex_abc123def456',
            'status_url' => '/api/v1/sync/reindex/status/reindex_abc123def456'
        ];

        $response = ReindexTaskResponse::fromArray($data);

        $this->assertEquals('accepted', $response->status);
        $this->assertEquals('Reindex operation initiated', $response->message);
        $this->assertEquals('reindex_abc123def456', $response->taskId);
        $this->assertEquals('/api/v1/sync/reindex/status/reindex_abc123def456', $response->statusUrl);
    }

    public function testReindexTaskResponseToArray(): void
    {
        $response = new ReindexTaskResponse(
            status: 'accepted',
            message: 'Reindex operation initiated',
            taskId: 'reindex_abc123def456',
            statusUrl: '/api/v1/sync/reindex/status/reindex_abc123def456'
        );

        $array = $response->toArray();
        $expected = [
            'status' => 'accepted',
            'message' => 'Reindex operation initiated',
            'task_id' => 'reindex_abc123def456',
            'status_url' => '/api/v1/sync/reindex/status/reindex_abc123def456'
        ];

        $this->assertEquals($expected, $array);
    }

    public function testReindexStatusResponseInProgress(): void
    {
        $data = [
            'task_id' => 'reindex_abc123def456',
            'status' => 'in_progress',
            'message' => 'Reindex operation is running',
            'progress' => [
                'total_docs' => 10000,
                'processed_docs' => 3500,
                'percentage' => 35.0,
                'estimated_remaining_seconds' => 120
            ],
            'started_at' => '1642244200',
            'updated_at' => '1642244500'
        ];

        $response = ReindexStatusResponse::fromArray($data);

        $this->assertEquals('reindex_abc123def456', $response->taskId);
        $this->assertEquals('in_progress', $response->status);
        $this->assertTrue($response->isInProgress());
        $this->assertFalse($response->isCompleted());
        $this->assertFalse($response->isFailed());
        $this->assertFalse($response->isFinished());
        $this->assertNotNull($response->progress);
        $this->assertEquals(10000, $response->progress['total_docs']);
    }

    public function testReindexStatusResponseCompleted(): void
    {
        $data = [
            'task_id' => 'reindex_abc123def456',
            'status' => 'completed',
            'message' => 'Reindex operation completed successfully',
            'result' => [
                'total_docs' => 10000,
                'indexed_docs' => 10000,
                'failed_docs' => 0,
                'took_milliseconds' => 45000
            ],
            'started_at' => '1642244200',
            'completed_at' => '1642244275'
        ];

        $response = ReindexStatusResponse::fromArray($data);

        $this->assertEquals('completed', $response->status);
        $this->assertFalse($response->isInProgress());
        $this->assertTrue($response->isCompleted());
        $this->assertFalse($response->isFailed());
        $this->assertTrue($response->isFinished());
        $this->assertNotNull($response->result);
        $this->assertEquals(10000, $response->result['total_docs']);
    }

    public function testReindexStatusResponseFailed(): void
    {
        $data = [
            'task_id' => 'reindex_abc123def456',
            'status' => 'failed',
            'message' => 'Reindex operation failed',
            'error' => 'Target index mapping incompatible',
            'started_at' => '1642244200',
            'failed_at' => '1642244205'
        ];

        $response = ReindexStatusResponse::fromArray($data);

        $this->assertEquals('failed', $response->status);
        $this->assertFalse($response->isInProgress());
        $this->assertFalse($response->isCompleted());
        $this->assertTrue($response->isFailed());
        $this->assertTrue($response->isFinished());
        $this->assertEquals('Target index mapping incompatible', $response->error);
    }

    public function testReindexStatusResponseToArray(): void
    {
        $response = new ReindexStatusResponse(
            taskId: 'reindex_abc123def456',
            status: 'completed',
            message: 'Reindex operation completed successfully',
            result: [
                'total_docs' => 10000,
                'indexed_docs' => 10000,
                'failed_docs' => 0
            ],
            startedAt: '1642244200',
            completedAt: '1642244275'
        );

        $array = $response->toArray();

        $this->assertEquals('reindex_abc123def456', $array['task_id']);
        $this->assertEquals('completed', $array['status']);
        $this->assertEquals('Reindex operation completed successfully', $array['message']);
        $this->assertArrayHasKey('result', $array);
        $this->assertEquals(10000, $array['result']['total_docs']);
        $this->assertEquals('1642244200', $array['started_at']);
        $this->assertEquals('1642244275', $array['completed_at']);
        // Null values should be filtered out
        $this->assertArrayNotHasKey('progress', $array);
        $this->assertArrayNotHasKey('error', $array);
    }

    public function testReindexCancelResponseSuccess(): void
    {
        $data = [
            'status' => 'success',
            'message' => 'Reindex task cancelled successfully',
            'task_id' => 'reindex_abc123def456'
        ];

        $response = ReindexCancelResponse::fromArray($data);

        $this->assertEquals('success', $response->status);
        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->isError());
        $this->assertEquals('reindex_abc123def456', $response->taskId);
        $this->assertNull($response->error);
    }

    public function testReindexCancelResponseError(): void
    {
        $data = [
            'status' => 'error',
            'message' => 'Cannot cancel completed task',
            'task_id' => 'reindex_abc123def456',
            'error' => 'task has already completed'
        ];

        $response = ReindexCancelResponse::fromArray($data);

        $this->assertEquals('error', $response->status);
        $this->assertFalse($response->isSuccess());
        $this->assertTrue($response->isError());
        $this->assertEquals('task has already completed', $response->error);
    }

    public function testReindexCancelResponseToArray(): void
    {
        $response = new ReindexCancelResponse(
            status: 'success',
            message: 'Reindex task cancelled successfully',
            taskId: 'reindex_abc123def456'
        );

        $array = $response->toArray();
        $expected = [
            'status' => 'success',
            'message' => 'Reindex task cancelled successfully',
            'task_id' => 'reindex_abc123def456'
        ];

        $this->assertEquals($expected, $array);
        // Null error should be filtered out
        $this->assertArrayNotHasKey('error', $array);
    }
}