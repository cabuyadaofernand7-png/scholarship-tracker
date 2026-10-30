<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\FailedAlertController;
use App\Http\Controllers\Api\NotificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::get('/applicants/{applicant}/notifications', [NotificationController::class, 'index']);
Route::get('/staff/failed-alerts', [FailedAlertController::class, 'index']);
Route::post('/staff/failed-alerts/{failedAlert}/resolve', [FailedAlertController::class, 'resolve']);
Route::post('/applications/{application}/assign-reviewer', [ApplicationController::class, 'assignReviewer']);
Route::patch('/applications/{application}/status', [ApplicationController::class, 'updateStatus']);
