<?php

use Illuminate\Support\Facades\Route;
use Modules\Bed\Http\Controllers\BedTypeController;
use Modules\Bed\Http\Controllers\BedMasterController;
use Modules\Bed\Http\Controllers\BedAllocationController;
use Modules\Bed\Http\Controllers\BedStatusController;

// Bed Type Routes
Route::group(['prefix' => 'bed-type', 'as' => 'bed-type.'], function () {
    Route::post('bulk-action', [BedTypeController::class, 'bulk_action'])->name('bulk_action');
    Route::get('index-data', [BedTypeController::class, 'index_data'])->name('index_data');
    Route::get('index-list', [BedTypeController::class, 'index_list'])->name('index_list');
    Route::get('action/{id}', [BedTypeController::class, 'action'])->name('action');
    Route::post('update-status/{id}', [BedTypeController::class, 'update_status'])->name('update_status');
    Route::get('{id}/price', [BedTypeController::class, 'getPrice'])->name('get_price');
});

// Resource routes for Bed Type
Route::resource('bed-type', BedTypeController::class);

// Bed Master routes
Route::group(['prefix' => 'bed-master', 'as' => 'bed-master.'], function () {
    Route::post('bulk-action', [BedMasterController::class, 'bulk_action'])->name('bulk_action');
    Route::get('index-data', [BedMasterController::class, 'index_data'])->name('index_data');
    Route::get('index-list', [BedMasterController::class, 'index_list'])->name('index_list');
    Route::get('action/{id}', [BedMasterController::class, 'action'])->name('action');
    Route::post('toggle-status/{id}', [BedMasterController::class, 'toggleStatus'])->name('toggle_status');
    Route::post('toggle-maintenance/{id}', [BedMasterController::class, 'toggleMaintenance'])->name('toggle_maintenance');
    Route::post('check-duplicate', [BedMasterController::class, 'checkDuplicate'])->name('check_duplicate');
});

// Resource routes for Bed Master
Route::resource('bed-master', BedMasterController::class);

// Bed Allocation routes
Route::group(['prefix' => 'bed-allocation', 'as' => 'bed-allocation.'], function () {
    Route::post('bulk-action', [BedAllocationController::class, 'bulkAction'])->name('bulk_action');
    Route::get('index-data', [BedAllocationController::class, 'indexData'])->name('index_data');
    Route::get('action/{id}', [BedAllocationController::class, 'action'])->name('action');
    Route::post('update-status/{id}', [BedAllocationController::class, 'updateStatus'])->name('update_status');
    Route::get('get-rooms/{bedTypeId}', [BedAllocationController::class, 'getRoomsByBedType'])->name('get_rooms');
    Route::get('get-clinics-by-admin/{adminId}', [BedAllocationController::class, 'getClinicsByAdmin'])->name('get_clinics_by_admin');
    Route::get('get-patient-encounters-by-clinic/{clinicId}', [BedAllocationController::class, 'getPatientEncountersByClinic'])->name('get_patient_encounters_by_clinic');
    Route::get('encounter/{encounterId}', [BedAllocationController::class, 'getEncounterBedAllocations'])->name('encounter_allocations');
    Route::get('create/{encounterId?}', [BedAllocationController::class, 'create'])->name('create_with_encounter');
    Route::get('get-encounter-details/{encounterId}', [BedAllocationController::class, 'getEncounterDetails'])->name('get_encounter_details');
});

// Resource routes for Bed Allocation
Route::resource('bed-allocation', BedAllocationController::class);

// Bed Status routes
Route::group(['prefix' => 'bed-status', 'as' => 'bed-status.'], function () {
    Route::get('/', [BedStatusController::class, 'index'])->name('index');
    Route::get('/bed/{id}', [BedStatusController::class, 'getBedDetails'])->name('bed.details');
    Route::get('/statistics', [BedStatusController::class, 'getBedStatistics'])->name('statistics');
});