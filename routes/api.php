<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Read-only API for the admin mobile app. Every route but sign-in needs a
| Sanctum token belonging to an active admin.
*/

// Shares Fortify's login limiter: five attempts a minute per email and IP, 2FA codes included.
Route::post('auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:login')->name('api.auth.token');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('api.auth.logout');
    Route::get('me', fn (Request $request) => new UserResource($request->user()))->name('api.me');

    Route::middleware('can:admin')->prefix('admin')->name('api.admin.')->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
        Route::get('tickets', [Admin\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [Admin\TicketController::class, 'show'])->name('tickets.show');
        Route::get('reports', Admin\ReportController::class)->name('reports');
        Route::get('lookups', Admin\LookupController::class)->name('lookups');

        // Claude calls cost money: a few a minute per admin, on top of the server-wide daily cap.
        Route::middleware('throttle:6,1')->group(function () {
            Route::post('tickets/{ticket}/summary', [Admin\AiController::class, 'summary'])->name('tickets.summary');
            Route::post('ask', [Admin\AiController::class, 'ask'])->name('ask');
        });
    });
});
