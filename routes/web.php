<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AdminController;

/**
 * Auth Endpoints
 */
// This endpoint is called by the Cafe24 oAuth when the app is opened in the admin/merchant's Cafe24 EC Admin dashboard.
// It will redirect to the Cafe24 Authentication endpoint to request for an authorization code which will be used to request for an access token.
Route::get('/', [AuthController::class, 'checkAuth']);
// This endpoint is called by the Cafe24 oAuth to give the PG app the authorization code and request for an access token.
Route::get('/auth/token', [AuthController::class, 'handleCallback']);

/**
 * Admin Endpoints
 */
Route::group(['prefix' => 'admin'], function () {
    // This endpoint displays the admin settings page
    Route::get('/display', [AdminController::class, 'displayAdmin']);
    // This endpoint will validate and store the merchant's PG Company credentials
    Route::post('/link', [AdminController::class, 'linkPGAccount'])->name('link-pg-account');
    // This endpoint will clear the stored PG Company credentials of the merchant from the app's data
    Route::post('/unlink', [AdminController::class, 'unlinkPGAccount'])->name('unlink-pg-account');
    // This endpoint will enable/disable the Payment Company in the specified Cafe24 shop
    Route::post('/shop/toggle', [AdminController::class, 'toggleShops'])->name('toggle-shop');
});

