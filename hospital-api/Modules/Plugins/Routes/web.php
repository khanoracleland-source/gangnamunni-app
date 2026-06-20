<?php

use Illuminate\Support\Facades\Route;
use Modules\Plugins\Http\Controllers\PluginsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'app', 'as' => 'backend.', 'middleware' => ['auth']], function () {
    /*
    * These routes need view-backend permission
    * (good if you want to allow more than one group in the backend,
    * then limit the backend features by different roles or permissions)
    *
    * Note: Administrator has all permissions so you do not have to specify the administrator role everywhere.
    */

    /*
     *
     *  Backend Coupons Routes
     *
     * ---------------------------------------------------------------------
     */

    Route::group(['prefix' => 'plugins', 'as' => 'plugins.'], function () {
        Route::get("activate/{id}", [PluginsController::class, 'activate'])->name("activate");
        Route::get("deactivate/{id}", [PluginsController::class, 'deactivate'])->name("deactivate");
        Route::get("delete/{id}", [PluginsController::class, 'delete'])->name("delete");
        Route::get("update-plugin/{id}", [PluginsController::class, 'update'])->name("update-plugin");
        Route::get("logs/{id}", [PluginsController::class, 'changeLogs'])->name("logs");
    });

    Route::resource("plugins", PluginsController::class)->names('plugins')->middleware(['role.access:admin,demo_admin']);
});

// Route::group([], function () {
//     Route::resource('plugins', PluginsController::class)->names('plugins');
// });
