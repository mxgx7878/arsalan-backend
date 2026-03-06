<?php
// ============================================================================
// routes/api.php
// ============================================================================
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\RideExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\CompanyExpenseController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\PartnerLedgerController;
use App\Http\Controllers\Api\VehicleExpenseController;
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
        Route::get('/partner/{partnerId}', [VehicleController::class, 'getByPartner']);
        Route::get('/personal', [VehicleController::class, 'getPersonalVehicles']);
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
    Route::prefix('partners')->group(function () {
        Route::get('/', [PartnerController::class, 'index']);
        Route::post('/', [PartnerController::class, 'store']);
        Route::get('/{id}', [PartnerController::class, 'show']);
        Route::put('/{id}', [PartnerController::class, 'update']);
        Route::patch('/{id}/status', [PartnerController::class, 'updateStatus']);
        Route::delete('/{id}', [PartnerController::class, 'destroy']);
    });
    // Rides
    Route::prefix('rides')->group(function () {
        Route::get('/statistics', [RideController::class, 'statistics']);
        Route::get('/', [RideController::class, 'index']);
        Route::post('/', [RideController::class, 'store']);
        Route::get('/{id}', [RideController::class, 'show']);
        Route::put('/{id}', [RideController::class, 'update']);
        Route::delete('/{id}', [RideController::class, 'destroy']);
        Route::put('/{id}/complete', [RideController::class, 'markAsCompleted']);
        
        // Nested expenses
        Route::get('/{rideId}/expenses', [RideExpenseController::class, 'getRideExpenses']);
        Route::post('/{rideId}/expenses', [RideExpenseController::class, 'store']);
    });
    
    // Expenses
    Route::prefix('expenses')->group(function () {
        Route::get('/statistics', [RideExpenseController::class, 'statistics']);
        Route::get('/{id}', [RideExpenseController::class, 'show']);
        Route::put('/{id}', [RideExpenseController::class, 'update']);
        Route::delete('/{id}', [RideExpenseController::class, 'destroy']);
    });

    // Invoices
    Route::prefix('invoices')->group(function () {
        Route::get('/statistics', [InvoiceController::class, 'statistics']);
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::put('/{id}', [InvoiceController::class, 'update']);
        Route::delete('/{id}', [InvoiceController::class, 'destroy']);
        Route::put('/{id}/mark-as-paid', [InvoiceController::class, 'markAsPaid']);
    });
    Route::prefix('expense-categories')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'index']);
        Route::post('/', [ExpenseCategoryController::class, 'store']);
        Route::get('/{id}', [ExpenseCategoryController::class, 'show']);
        Route::put('/{id}', [ExpenseCategoryController::class, 'update']);
        Route::delete('/{id}', [ExpenseCategoryController::class, 'destroy']);
        Route::put('/{id}/toggle-active', [ExpenseCategoryController::class, 'toggleActive']);
    });

    // ===================================================================
    // COMPANY EXPENSES - Full CRUD with Categories
    // ===================================================================
    Route::prefix('company-expenses')->group(function () {
        Route::get('/statistics', [CompanyExpenseController::class, 'statistics']);
        Route::get('/', [CompanyExpenseController::class, 'index']);
        Route::post('/', [CompanyExpenseController::class, 'store']);
        Route::get('/{id}', [CompanyExpenseController::class, 'show']);
        Route::put('/{id}', [CompanyExpenseController::class, 'update']);
        Route::delete('/{id}', [CompanyExpenseController::class, 'destroy']);
    });

    Route::prefix('vehicle-expenses')->group(function () {
        Route::get('/statistics', [VehicleExpenseController::class, 'statistics']);
        Route::get('/', [VehicleExpenseController::class, 'index']);
        Route::post('/', [VehicleExpenseController::class, 'store']);
        Route::get('/{id}', [VehicleExpenseController::class, 'show']);
        Route::put('/{id}', [VehicleExpenseController::class, 'update']);
        Route::delete('/{id}', [VehicleExpenseController::class, 'destroy']);
    });

    Route::prefix('partners/{partner_id}')->group(function () {
    
        // Get ledger entries for a partner
        Route::get('/ledger', [PartnerLedgerController::class, 'index']);
        
        // Add new ledger entry
        Route::post('/ledger', [PartnerLedgerController::class, 'store']);
        
        // Get specific ledger entry
        Route::get('/ledger/{entry_id}', [PartnerLedgerController::class, 'show']);
        
        // Update ledger entry
        Route::put('/ledger/{entry_id}', [PartnerLedgerController::class, 'update']);
        
        // Delete ledger entry
        Route::delete('/ledger/{entry_id}', [PartnerLedgerController::class, 'destroy']);
        
        // Get ledger summary
        Route::get('/ledger-summary', [PartnerLedgerController::class, 'summary']);
        
        // Set opening balance
        Route::post('/ledger/opening-balance', [PartnerLedgerController::class, 'setOpeningBalance']);
    });
});