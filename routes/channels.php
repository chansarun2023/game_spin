<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Real-time points channels
Broadcast::channel('points-updates', function ($user) {
    return true; // Public channel - anyone can listen
});

Broadcast::channel('leaderboard-updates', function ($user) {
    return true; // Public channel - anyone can listen
});

// Private user channel for personal updates
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel for online users (optional)
Broadcast::channel('online-users', function ($user) {
    return $user ? [
        'id' => $user->id,
        'username' => $user->username,
        'name' => $user->name,
    ] : null;
});
