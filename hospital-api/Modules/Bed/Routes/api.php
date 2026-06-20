<?php
use Illuminate\Support\Facades\Route;
use Modules\Bed\Http\Controllers\BedTypeController;
use Modules\Bed\Http\Controllers\BedMasterController;
use Modules\Bed\Http\Controllers\BedStatusController;
use Modules\Bed\Http\Controllers\BedAllocationController;
use Modules\Bed\Http\Controllers\Api\BedAllocationApiController as ApiBedAllocationController;
use Modules\Bed\Http\Controllers\Api\BedStatusController as ApiBedStatusController;


// Route::group(['middleware' => 'auth:sanctum'], function () {
    
    Route::group(['prefix' => 'bed-type', 'as' => 'bed-type.'], function () {
        Route::post('bulk-action', [BedTypeController::class, 'bulk_action'])->name('bulk_action');
        Route::get('index-data', [BedTypeController::class, 'index_data'])->name('index_data');
        Route::get('action/{id}', [BedTypeController::class, 'action'])->name('action');
        Route::post('update-status/{id}', [BedTypeController::class, 'update_status'])->name('update_status');

    });
    
    // Resource routes - this will handle create, store, edit, update, destroy
    Route::get('bed-type-list', [BedTypeController::class,'index_list']);
    Route::apiResource('bed-type', BedTypeController::class);

    Route::group(['middleware' => 'auth:sanctum'], function () {
        // Bed Master routes
        Route::group(['prefix' => 'bed-master', 'as' => 'bed-master.'], function () {
            Route::post('bulk-action', [BedMasterController::class, 'bulk_action'])->name('bulk_action');
            Route::get('index-data', [BedMasterController::class, 'index_data'])->name('index_data');
            Route::get('action/{id}', [BedMasterController::class, 'action'])->name('action');
            Route::post('update-status/{id}', [BedMasterController::class, 'update_status'])->name('update_status');
        });

        // Resource routes for Bed Master (CRUD)
        Route::get('bed-master-list', [BedMasterController::class,'index_list']);
        Route::apiResource('bed-master', BedMasterController::class);

            // Bed Allocation routes
        Route::group(['prefix' => 'bed-allocation', 'as' => 'bed-allocation.'], function () {
            Route::get('index-data', [BedAllocationController::class, 'indexData'])->name('index_data');
            Route::post('bulk-action', [BedAllocationController::class, 'bulkAction'])->name('bulk_action');
            Route::get('action/{id}', [BedAllocationController::class, 'action'])->name('action');
            Route::post('update-status/{id}', [BedAllocationController::class, 'updateStatus'])->name('update_status');
            Route::get('get-rooms/{bedTypeId}', [BedAllocationController::class, 'getRoomsByBedType'])->name('get_rooms');
            Route::get('index-list', [BedAllocationController::class, 'index_list'])->name('index_list');
        });

        // Resource routes for Bed Allocation (CRUD)
        Route::apiResource('bed-allocation', BedAllocationController::class);
        Route::get('bed-allocation-list', [ApiBedAllocationController::class, 'bedAllocationList'])->name('bed_allocation_list');

    });
     //  Route::get('bed-assign',[BedAllocationController::class, 'store']);
    // Bed Status API Routes
    Route::group(['prefix' => 'bed-status', 'as' => 'bed-status.'], function () {
        Route::get('/statistics', [BedStatusController::class, 'getBedStatistics'])->name('statistics');
        Route::get('/bed/{id}', [BedStatusController::class, 'getBedDetails'])->name('bed.details');
    });

    // Bed Status Routes - require authentication to filter by user's role
    Route::prefix('bed-status')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [ApiBedStatusController::class, 'index']);
        Route::get('/available', [ApiBedStatusController::class, 'getAvailableBeds']);
        Route::get('/occupied', [ApiBedStatusController::class, 'getOccupiedBeds']);
        Route::get('/maintenance', [ApiBedStatusController::class, 'getMaintenanceBeds']);
        Route::get('/all', [ApiBedStatusController::class, 'getAllBeds']);
        Route::get('/bed/{id}', [ApiBedStatusController::class, 'getBedDetails']);
        Route::post('/bed/{id}/toggle-maintenance', [ApiBedStatusController::class, 'toggleMaintenance']);
    });

    Route::get('bed/getrooms/{bedTypeId}', [BedAllocationController::class, 'getRoomsByBedType']);

// });

// Bed Allocation API Routes
// Route::group(['prefix' => 'bed-allocation', 'middleware' => ['auth:sanctum']], function () {
//     // Basic CRUD operations
//     // Route::get('/', [BedAllocationController::class, 'apiIndex']);
//     // Route::post('/', [BedAllocationController::class, 'apiStore']);
//     // Route::get('/{id}', [BedAllocationController::class, 'apiShow']);
//     // Route::put('/{id}', [BedAllocationController::class, 'apiUpdate']);
//     // Route::delete('/{id}', [BedAllocationController::class, 'apiDestroy']);

//     // Additional endpoints
//     // Route::get('/get-rooms/{bedTypeId}', [BedAllocationController::class, 'getRoomsByBedType']);
//     Route::get('/bed-types', [BedAllocationController::class, 'apiGetBedTypes']);
// });