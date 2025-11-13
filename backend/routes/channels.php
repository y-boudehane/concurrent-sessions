<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel for session-specific events (login detection)
Broadcast::channel('session.{sessionId}', function ($user, $sessionId) {
    return $sessionId === session()->getId();
});
