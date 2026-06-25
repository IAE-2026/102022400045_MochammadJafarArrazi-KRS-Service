<?php

use App\Http\Controllers\Api\V1\KrsController;
use App\Http\Middleware\EnsureIaeApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(EnsureIaeApiKey::class)->group(function (): void {
    Route::get('/krs', [KrsController::class, 'index']);
    Route::get('/krs/semester/{tahunAjaran}/{semester}', [KrsController::class, 'bySemesterOld']);
    Route::get('/krs/{id}', [KrsController::class, 'show'])->whereNumber('id');
    Route::get('/krs/{tahunAjaranSemester}', [KrsController::class, 'bySemester']);
    Route::post('/krs', [KrsController::class, 'store']);
    Route::patch('/krs/{id}/status', [KrsController::class, 'updateStatus']);
});
