<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BrokerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ValuationController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(User::exists() ? 'dashboard' : 'register'));

Route::post('/locale', LocaleController::class)->name('locale');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::post('/transactions/bulk', [TransactionController::class, 'bulk'])->name('transactions.bulk');
    Route::patch('/transactions/{transaction}/quick', [TransactionController::class, 'quick'])->name('transactions.quick');
    Route::resource('transactions', TransactionController::class)->except('show');

    Route::get('/import', [ImportController::class, 'create'])->name('imports.create');
    Route::post('/import', [ImportController::class, 'upload'])->name('imports.upload');
    Route::get('/import/{token}', [ImportController::class, 'review'])->name('imports.review');
    Route::post('/import/{token}', [ImportController::class, 'commit'])->name('imports.commit');

    Route::get('/banks', [BankController::class, 'index'])->name('banks.index');
    Route::post('/banks/connect', [BankController::class, 'connect'])->name('banks.connect');
    Route::get('/banks/callback', [BankController::class, 'callback'])->name('banks.callback');
    Route::get('/banks/{connection}', [BankController::class, 'show'])->name('banks.show');
    Route::post('/banks/{connection}/link', [BankController::class, 'link'])->name('banks.link');
    Route::post('/banks/{connection}/sync', [BankController::class, 'sync'])->name('banks.sync');
    Route::delete('/banks/{connection}', [BankController::class, 'destroy'])->name('banks.destroy');

    Route::get('/plan', [PlanController::class, 'index'])->name('plan.index');
    Route::post('/plan', [PlanController::class, 'store'])->name('plan.store');
    Route::put('/plan/{item}', [PlanController::class, 'update'])->name('plan.update');
    Route::delete('/plan/{item}', [PlanController::class, 'destroy'])->name('plan.destroy');

    Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('/transfers', [TransferController::class, 'confirm'])->name('transfers.confirm');

    Route::get('/investments', [BrokerController::class, 'index'])->name('brokers.index');
    Route::post('/investments/{provider}/link', [BrokerController::class, 'link'])->name('brokers.link');
    Route::post('/investments/{provider}/sync', [BrokerController::class, 'sync'])->name('brokers.sync');
    Route::delete('/investments/{provider}', [BrokerController::class, 'destroy'])->name('brokers.destroy');
    Route::post('/accounts/{account}/valuation', [ValuationController::class, 'store'])->name('valuations.store');

    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/rules/apply', [RuleController::class, 'apply'])->name('rules.apply');
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
