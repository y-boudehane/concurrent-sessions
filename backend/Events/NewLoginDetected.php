<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewLoginDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $existingSessionId;
    public array $newDeviceInfo;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, string $existingSessionId, array $newDeviceInfo)
    {
        $this->userId = $userId;
        $this->existingSessionId = $existingSessionId;
        $this->newDeviceInfo = $newDeviceInfo;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('session.' . $this->existingSessionId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'new-login-detected';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'new_device' => $this->newDeviceInfo,
            'message' => 'A new login was detected from another device',
        ];
    }
}
