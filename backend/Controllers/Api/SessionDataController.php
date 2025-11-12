<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConcurrentSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SessionDataController extends Controller
{
    /**
     * Get current and other session data for the authenticated user.
     */
    public function index(Request $request, ConcurrentSessionManager $sessionManager): JsonResponse
    {
        $userId = Auth::id();
        $currentSessionId = session()->getId();

        // Get current session info
        $currentSession = DB::table('sessions')
            ->where('id', $currentSessionId)
            ->first();

        // Get other sessions
        $otherSessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->get()
            ->map(function ($session) use ($sessionManager) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $sessionManager->parseUserAgent($session->user_agent),
                    'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            });

        return response()->json([
            'currentDevice' => [
                'ip_address' => $currentSession->ip_address ?? $request->ip(),
                'user_agent' => $sessionManager->parseUserAgent($currentSession->user_agent ?? $request->userAgent()),
            ],
            'otherDevices' => $otherSessions,
        ]);
    }
}
