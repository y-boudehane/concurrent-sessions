<?php

use App\Http\Controllers\ConfirmDeviceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Concurrent Session Web Routes
|--------------------------------------------------------------------------
|
| These routes handle the concurrent session confirmation flow.
| Add these routes to your main routes/web.php file.
|
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/confirm-device', [ConfirmDeviceController::class, 'show'])->name('confirm-device');
    Route::post('/confirm-device', [ConfirmDeviceController::class, 'confirm']);
});
