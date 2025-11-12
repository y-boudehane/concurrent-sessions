<?php

use App\Http\Controllers\Api\SessionDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Concurrent Session API Routes
|--------------------------------------------------------------------------
|
| These API routes provide session data for the concurrent session dialog.
| Add these routes to your main routes/api.php file.
|
*/

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/session-data', [SessionDataController::class, 'index']);
});
