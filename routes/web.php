<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoodEntryController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::post('/entries', [FoodEntryController::class, 'store'])->name('entries.store');
Route::post('/entries/photo', [FoodEntryController::class, 'storePhoto'])->name('entries.photo');
Route::delete('/entries/{foodEntry}', [FoodEntryController::class, 'destroy'])->name('entries.destroy');

Route::put('/goal', [GoalController::class, 'update'])->name('goal.update');

Route::get('/history', [HistoryController::class, 'index'])->name('history');
