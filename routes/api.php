<?php

use App\Http\Controllers\Shopper\ShopperQueueController;
use App\Http\Controllers\Store\Location\LocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/check-in', [ShopperQueueController::class, 'checkIn']);
Route::post('/check-out', [ShopperQueueController::class, 'checkOut']);
Route::get('/location/{locationUuid}/fresh', [ShopperQueueController::class, 'refreshQueue']);
Route::patch('/location/{locationUuid}', [LocationController::class, 'update']);
