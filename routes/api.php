<?php

use App\Http\Controllers\Api\SchoolApiController;
use App\Http\Controllers\BiometricController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/biometric/events', [BiometricController::class, 'receive'])->middleware(['throttle:biometric', 'device.token']);

Route::middleware('api.token:read')->group(function () {
    Route::get('/v1/me', [SchoolApiController::class, 'me']);
    Route::get('/v1/students', [SchoolApiController::class, 'students']);
    Route::get('/v1/students/{student}', [SchoolApiController::class, 'student']);
    Route::get('/v1/students/{student}/attendance', [SchoolApiController::class, 'attendance']);
    Route::get('/v1/students/{student}/fees', [SchoolApiController::class, 'fees']);
    Route::get('/v1/students/{student}/results', [SchoolApiController::class, 'results']);
    Route::get('/v1/students/{student}/timetable', [SchoolApiController::class, 'timetable']);
    Route::get('/v1/notices', [SchoolApiController::class, 'notices']);
});
