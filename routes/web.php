<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\TransactionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(User::exists() ? 'dashboard' : 'register'));

Route::post('/locale', LocaleController::class)->name('locale');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::resource('transactions', TransactionController::class)->except('show');

    Route::get('/import', [ImportController::class, 'create'])->name('imports.create');
    Route::post('/import', [ImportController::class, 'upload'])->name('imports.upload');
    Route::get('/import/{token}', [ImportController::class, 'review'])->name('imports.review');
    Route::post('/import/{token}', [ImportController::class, 'commit'])->name('imports.commit');

    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('rules', RuleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('recurring', RecurringController::class)->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['recurring' => 'recurring']);

    Route::get('/settle-up', [SettlementController::class, 'index'])->name('settle.index');
    Route::post('/settle-up', [SettlementController::class, 'store'])->name('settle.store');
    Route::delete('/settle-up/{settlement}', [SettlementController::class, 'destroy'])->name('settle.destroy');

    Route::resource('members', MemberController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
