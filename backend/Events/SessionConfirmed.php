<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionConfirmed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $sessionId,
        public string $action = 'confirmed'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('session.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session-confirmed';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'action' => $this->action,
            'message' => 'Session has been confirmed on another device',
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
