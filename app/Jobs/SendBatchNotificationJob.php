<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\RealTimeNotification;
use App\Events\UpdateNotificationCounters;
use App\Models\User;
use App\Notifications\InAppMessage;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

/**
 * SendBatchNotificationJob
 * 
 * Optimized job for sending notifications to multiple users in batch.
 * Supports queuing for better performance with large user groups.
 */
class SendBatchNotificationJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public int $backoff = 30;

    /**
     * Create a new job instance
     */
    public function __construct(
        public array $userIds,
        public string $title,
        public string $message,
        public array $data = [],
        public bool $broadcastRealtime = true
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Process in chunks to manage memory
        $chunks = array_chunk($this->userIds, 100);
        
        foreach ($chunks as $chunk) {
            $this->processChunk($chunk);
        }
    }

    /**
     * Process a chunk of user IDs
     */
    protected function processChunk(array $userIds): void
    {
        // Load all users at once
        $users = User::whereIn('id', $userIds)->get();
        
        foreach ($users as $user) {
            $this->sendToUser($user);
        }
    }

    /**
     * Send notification to a single user
     */
    protected function sendToUser(User $user): void
    {
        try {
            // Send in-app notification
            $user->notify(new InAppMessage($this->title, $this->message, $this->data));
            
            // Broadcast real-time if enabled
            if ($this->broadcastRealtime) {
                event(new RealTimeNotification(
                    userId: $user->id,
                    title: $this->title,
                    message: $this->message,
                    type: $this->data['type'] ?? 'info',
                    link: $this->data['link'] ?? null,
                    data: $this->data
                ));
            }
            
            // Update notification counter
            event(new UpdateNotificationCounters($user->id));
        } catch (\Throwable $e) {
            logger()->warning('Failed to send notification to user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job
     */
    public function tags(): array
    {
        return [
            'notifications',
            'batch',
            'users:' . count($this->userIds),
        ];
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        logger()->error('Batch notification job failed', [
            'user_count' => count($this->userIds),
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Static helper to dispatch notifications to many users with optimal batching
     */
    public static function dispatchToUsers(
        array $userIds,
        string $title,
        string $message,
        array $data = [],
        bool $broadcastRealtime = true
    ): void {
        // For small groups, dispatch directly
        if (count($userIds) <= 100) {
            static::dispatch($userIds, $title, $message, $data, $broadcastRealtime);
            return;
        }

        // For large groups, use batching
        $chunks = array_chunk($userIds, 100);
        $jobs = [];
        
        foreach ($chunks as $chunk) {
            $jobs[] = new static($chunk, $title, $message, $data, $broadcastRealtime);
        }

        Bus::batch($jobs)
            ->name("Batch Notification: {$title}")
            ->onQueue('notifications')
            ->allowFailures()
            ->dispatch();
    }
}
