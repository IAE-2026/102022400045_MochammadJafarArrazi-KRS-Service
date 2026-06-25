<?php

use App\Http\Controllers\Api\V1\KrsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth.jwt')->group(function (): void {
    Route::get('/krs', [KrsController::class, 'index']);
    Route::get('/krs/semester/{tahunAjaran}/{semester}', [KrsController::class, 'bySemesterOld']);
    Route::get('/krs/{id}', [KrsController::class, 'show'])->whereNumber('id');
    Route::get('/krs/{tahunAjaranSemester}', [KrsController::class, 'bySemester']);
    Route::post('/krs', [KrsController::class, 'store'])->middleware('role:mahasiswa');
    Route::patch('/krs/{id}/status', [KrsController::class, 'updateStatus'])->middleware('role:dosen');
    Route::put('/krs/{id}/approve', [KrsController::class, 'approve'])->middleware('role:dosen');
});
