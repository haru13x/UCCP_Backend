<?php

use App\Http\Controllers\AccountGroupController;
use App\Http\Controllers\Auth\ForgotPasswordController;
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

    Route::post('/update-roles/{id}', [RoleController::class, 'update']);
    Route::put('/roles/{id}', [PermissionController::class, 'update']);

    Route::post('/get-events', [EventController::class, 'index']);
    Route::post('/store-events', [EventController::class, 'store']);
    Route::post('/update-events', [EventController::class, 'update']);
    Route::post('/event-registration/{id}', [EventController::class, 'eventRegisteration']);
    Route::get('/isregistered/{id}', [EventController::class, 'isRegistered']);
    Route::get('get-organizer',[UserController::class ,'getOrganizer']);
    Route::post('get-event-registered/{id}', [EventController::class, 'getEventRegisteredUsers']);
    Route::post('event-registration-multiple', [EventController::class, 'eventMultipleRegisteration']);
    Route::post('mark-attend', [EventController::class, 'attendance']);



    Route::get('/dashboard/chart', [DashboardController::class, 'chart']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);


    Route::get('/account-groups', [AccountGroupController::class, 'getGroups']);
    Route::get('/account-types/{groupId}', [AccountGroupController::class, 'getTypesByGroup']);
    Route::post('/upload-users', [UserController::class, 'uploadUsers']);

    Route::get('/qrcodes/{eventId}', [QRCodeController::class, 'get']);
    Route::post('/qrcodes/generate/{eventId}', [QRCodeController::class, 'generate']);

     Route::post('events-list/{type}', [EventController::class, 'list']);
    Route::post('scan-event', [EventController::class, 'scanEvent']);
    Route::get('my-events-list/{type}', [EventController::class, 'myEventList']);
    Route::put('/cancel-event/{id}', [EventController::class, 'cancelEvent']);
    Route::post('myCalendarList', [EventController::class, 'myCalendarList']);
    // routes/api.php
    Route::post('/generate-event-report', [EventController::class, 'generatePdf']);
        Route::get('/notifications/new', [EventController::class, 'getNewNotifications']);
    Route::get('/events/{eventId}/reviews', [EventController::class, 'getReviews']);
        Route::get('/events/{eventId}/reviews', [EventController::class, 'getReviews']);
    Route::put('events/{eventId}/review', [EventController::class, 'updateReview']);   // PUT: Update
    Route::post('/approve-request/{id}', [UserController::class, 'approveRequest']);
    Route::post('/get-users', [UserController::class, 'index']);
    Route::post('/search-users', [UserController::class, 'searchUsers']);
    Route::post('/request-registration', [UserController::class, 'requestRegistration']);
    Route::post('/update-users', [UserController::class, 'update']);
      Route::post('/update-user-status', [UserController::class, 'updateStatus']);
    Route::get('/get-event/{eventId}', [EventController::class, 'getEvent']);

});
Route::get('/account-groups', [AccountGroupController::class, 'getGroups']);
Route::get('/account-types/{groupId}', [AccountGroupController::class, 'getTypesByGroup']);
Route::get('/get-roles', [RoleController::class, 'index']);


Route::post("/login", [UserController::class, "login"]);

Route::post("/register", [UserController::class, "register"]);

Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
Route::post('verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
    Route::get('/generate-event-reports', [EventController::class, 'generatePdf']);