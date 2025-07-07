<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;

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
Route::middleware('auth.token')->group(function () {
Route::get('/permissions', [PermissionController::class, 'index']);
Route::post('/roles', [PermissionController::class, 'store']);

Route::get('/get-roles', [RoleController::class, 'index']);

Route::post('/update-roles/{id}', [RoleController::class, 'update']);
Route::put('/roles/{id}', [PermissionController::class, 'update']);


Route::get('/get-events', [EventController::class, 'index']);
Route::post('/store-events', [EventController::class, 'store']);
Route::post('/update-events', [EventController::class, 'update']);
Route::post('event/register/{id}', [EventController::class, 'register']);


Route::get('/dashboard/chart', [DashboardController::class, 'chart']);
Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

Route::get('/events/today', [EventController::class, 'today']);
Route::get('/events/upcoming', [EventController::class, 'upcoming']);
Route::get('/events/past', [EventController::class, 'past']);
Route::get('/qrcodes/{eventId}', [QRCodeController::class, 'get']);
Route::post('/qrcodes/generate/{eventId}', [QRCodeController::class, 'generate']);

Route::get('/get-users', [UserController::class, 'index']);
});
Route::post("/register",[UserController::class,"register"]);
Route::post("/login",[UserController::class,"login"]);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
