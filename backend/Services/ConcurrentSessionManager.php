<?php

namespace App\Services;

use App\Events\NewLoginDetected;
use App\Events\SessionDisconnected;
use App\Events\SessionConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reusable Concurrent Session Manager
 *
 * Handles single-session enforcement with real-time notifications.
 * Can be easily integrated into any Laravel project.
 */
class ConcurrentSessionManager
{
    /**
     * Check for concurrent sessions and broadcast alerts if found.
     *
     * @param int $userId
     * @param string $currentSessionId
     * @param array $newDeviceInfo
     * @return bool True if concurrent sessions were found
     */
    public function detectAndNotifyConcurrentSessions(
        int $userId,
        string $currentSessionId,
        array $newDeviceInfo
    ): bool {
        $otherSessions = $this->getOtherSessions($userId, $currentSessionId);

        if ($otherSessions->isEmpty()) {
            return false;
        }

        // Broadcast to all other sessions
        foreach ($otherSessions as $session) {
            $this->broadcastNewLoginDetected($userId, $session->id, $newDeviceInfo);
        }

        return true;
    }

    /**
     * Terminate other sessions and notify them.
     *
     * @param int $userId
     * @param string $currentSessionId
     * @param string $reason
     * @return int Number of sessions terminated
     */
    public function terminateOtherSessions(
        int $userId,
        string $currentSessionId,
        string $reason = 'replaced_by_new_device'
    ): int {
        $otherSessions = $this->getOtherSessions($userId, $currentSessionId);

        if ($otherSessions->isEmpty()) {
            return 0;
        }

        // Broadcast disconnection before deleting
        foreach ($otherSessions as $session) {
            $this->broadcastSessionDisconnected($userId, $session->id, $reason);
        }

        // CRITICAL: Wait a moment to ensure broadcast is sent before deleting sessions
        // This is necessary because once the session is deleted, channel authorization fails
        usleep(500000); // 500ms delay

        // Delete sessions
        $deleted = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return $deleted;
    }

    public function clearConfirmationFlags(int $userId, string $currentSessionId): void
    {
        $otherSessions = $this->getOtherSessions($userId, $currentSessionId);

        foreach ($otherSessions as $session) {
            $this->updateSessionPayload($session->id, function ($payload) {
                unset(
                    $payload['awaiting_confirmation'],
                    $payload['new_login_at'],
                    $payload['other_sessions_count']
                );
                return $payload;
            });
        }
    }

    /**
     * Get all other sessions for a user.
     *
     * @param int $userId
     * @param string $excludeSessionId
     * @return \Illuminate\Support\Collection
     */
    protected function getOtherSessions(int $userId, string $excludeSessionId)
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $excludeSessionId)
            ->get();
    }

    /**
     * Update session payload with a callback.
     *
     * @param string $sessionId
     * @param callable $callback
     * @return void
     */
    protected function updateSessionPayload(string $sessionId, callable $callback): void
    {
        $session = DB::table('sessions')->where('id', $sessionId)->first();

        if (!$session) {
            return;
        }

        try {
            $payload = unserialize(base64_decode($session->payload));
            $payload = $callback($payload);
            $newPayload = base64_encode(serialize($payload));

            DB::table('sessions')
                ->where('id', $sessionId)
                ->update(['payload' => $newPayload]);
        } catch (\Exception $e) {
            Log::error('Failed to update session payload', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast new login detected event.
     *
     * @param int $userId
     * @param string $sessionId
     * @param array $newDeviceInfo
     * @return void
     */
    protected function broadcastNewLoginDetected(
        int $userId,
        string $sessionId,
        array $newDeviceInfo
    ): void {

        broadcast(new NewLoginDetected($userId, $sessionId, $newDeviceInfo));
    }

    /**
     * Broadcast session disconnected event.
     *
     * @param int $userId
     * @param string $sessionId
     * @param string $reason
     * @return void
     */
    protected function broadcastSessionDisconnected(
        int $userId,
        string $sessionId,
        string $reason
    ): void {
        broadcast(new SessionDisconnected($userId, $sessionId, $reason));
    }

    /**
     * Broadcast session confirmed event to other sessions.
     *
     * @param int $userId
     * @param string $currentSessionId
     * @return void
     */
    public function broadcastSessionConfirmed(
        int $userId,
        string $currentSessionId
    ): void {
        $otherSessions = $this->getOtherSessions($userId, $currentSessionId);

        foreach ($otherSessions as $session) {
            broadcast(new SessionConfirmed($userId, $session->id, 'keep_other_device'));
        }
    }

    /**
     * Parse user agent to get browser and OS info.
     *
     * @param string|null $userAgent
     * @return string
     */
    public function parseUserAgent(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown device';
        }

        // Browser detection
        $browser = match (true) {
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'Unknown browser',
        };

        // OS detection
        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iOS') => 'iOS',
            default => 'Unknown OS',
        };

        return "{$browser} on {$os}";
    }
}
