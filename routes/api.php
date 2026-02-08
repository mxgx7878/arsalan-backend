<?php
// ============================================================================
// routes/api.php
// ============================================================================
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\PartyController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (No authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (Authentication required)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Your other protected routes will go here
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleController::class, 'index']);
        Route::post('/', [VehicleController::class, 'store']);
        Route::get('/{id}', [VehicleController::class, 'show']);
        Route::put('/{id}', [VehicleController::class, 'update']);
        Route::patch('/{id}/status', [VehicleController::class, 'updateStatus']);
        Route::delete('/{id}', [VehicleController::class, 'destroy']);
    });
    Route::prefix('parties')->group(function () {
        Route::get('/', [PartyController::class, 'index']);
        Route::post('/', [PartyController::class, 'store']);
        Route::get('/{id}', [PartyController::class, 'show']);
        Route::put('/{id}', [PartyController::class, 'update']);
        Route::patch('/{id}/status', [PartyController::class, 'updateStatus']);
        Route::delete('/{id}', [PartyController::class, 'destroy']);
    });
    
});