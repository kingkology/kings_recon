<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IpValidatorController;

Route::get('/', [IpValidatorController::class, 'index'])->name('home');
Route::get('/upload', [IpValidatorController::class, 'upload'])->name('upload');
Route::post('/upload', [IpValidatorController::class, 'store'])->name('upload.store');
Route::get('/scan/{batchId}', [IpValidatorController::class, 'show'])->name('scan.show');
Route::get('/scan/{batchId}/status', [IpValidatorController::class, 'status'])->name('scan.status');
Route::get('/scan/{batchId}/report', [IpValidatorController::class, 'report'])->name('scan.report');
Route::get('/scan/{batchId}/export', [IpValidatorController::class, 'exportReport'])->name('scan.export');
