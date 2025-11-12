<?php

namespace App\Http\Controllers;

use App\Services\ConcurrentSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmDeviceController extends Controller
{
    /**
     * Show the device confirmation page.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $currentSession = DB::table('sessions')
            ->where('id', session()->getId())
            ->first();

        $otherSessions = DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', '!=', session()->getId())
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $this->parseUserAgent($session->user_agent),
                    'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            });
        
        if (!session('awaiting_confirmation') && $otherSessions->count() === 0) {
            return redirect('/');
        }

        return Inertia::render('auth/ConfirmDevice', [
            'currentDevice' => [
                'ip_address' => $currentSession->ip_address ?? $request->ip(),
                'user_agent' => $this->parseUserAgent($currentSession->user_agent ?? $request->userAgent()),
            ],
            'otherDevices' => $otherSessions,
            'newLoginAt' => session('new_login_at'),
        ]);
    }

    /**
     * Handle the device confirmation choice.
     */
    public function confirm(Request $request, ConcurrentSessionManager $sessionManager): RedirectResponse
    {
        $validated = $request->validate([
            'choice' => 'required|in:keep_current,keep_other',
        ]);

        $choice = $validated['choice'];
        $userId = Auth::id();
        $currentSessionId = session()->getId();

        if ($choice === 'keep_current') {
            $sessionManager->terminateOtherSessions($userId, $currentSessionId, 'replaced_by_new_device');         

            session()->forget(['awaiting_confirmation', 'new_login_at', 'other_sessions_count']);

            $user = Auth::user();
            if ($user->hasRole('teacher')) {
                $redirect = route('teacher.dashboard');
            } elseif ($user->hasRole('student')) {
                $redirect = route('student.dashboard');
            } else {
                $redirect = '/';
            }

            return redirect($redirect)->with('success', 'You are now signed in on this device. All other sessions have been terminated.');
        } else {
            // Broadcast to other sessions that this session is confirmed
            $sessionManager->broadcastSessionConfirmed($userId, $currentSessionId);

            // Wait a moment for broadcast to be sent
            usleep(500000); // 500ms delay

            // remove flag from the other concurrent sessions
            $sessionManager->clearConfirmationFlags($userId, $currentSessionId);

            // Log out current session
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
            
            return redirect('/')->with('message', 'You have switched devices.');
        }
    }

    /**
     * Parse user agent to get browser and OS info.
     */
    private function parseUserAgent(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown device';
        }

        if (str_contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            $browser = 'Edge';
        } else {
            $browser = 'Unknown browser';
        }

        if (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $os = 'Mac';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'iOS')) {
            $os = 'iOS';
        } else {
            $os = 'Unknown OS';
        }

        return "{$browser} on {$os}";
    }
}
