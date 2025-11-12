<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionDisconnected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $sessionId,
        public string $reason = 'disconnected_by_new_login'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('session.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session-disconnected';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'reason' => $this->reason,
            'message' => 'Your session has been disconnected. You logged in from another device.',
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
