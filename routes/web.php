<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IpValidatorController;
use App\Http\Controllers\PentestController;

Route::get('/', [IpValidatorController::class, 'index'])->name('home');
Route::get('/upload', [IpValidatorController::class, 'upload'])->name('upload');
Route::post('/upload', [IpValidatorController::class, 'store'])->name('upload.store');
Route::get('/scan/{batchId}', [IpValidatorController::class, 'show'])->name('scan.show');
Route::get('/scan/{batchId}/status', [IpValidatorController::class, 'status'])->name('scan.status');
Route::get('/scan/{batchId}/report', [IpValidatorController::class, 'report'])->name('scan.report');
Route::get('/scan/{batchId}/export', [IpValidatorController::class, 'exportReport'])->name('scan.export');

// Pentest routes
Route::get('/pentest/batch/{batchId}/select-targets', [PentestController::class, 'selectTargets'])->name('pentest.select');
Route::post('/pentest/batch/{batchId}/create', [PentestController::class, 'createSession'])->name('pentest.create');
Route::get('/pentest/sessions', [PentestController::class, 'sessions'])->name('pentest.sessions');
Route::get('/pentest/session/{sessionId}/results', [PentestController::class, 'results'])->name('pentest.results');
Route::get('/pentest/session/{sessionId}/export', [PentestController::class, 'export'])->name('pentest.export');
Route::post('/pentest/session/{sessionId}/cancel', [PentestController::class, 'cancelSession'])->name('pentest.cancel');
