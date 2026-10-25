<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryBudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\LockController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
Route::post('onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');

Route::middleware('onboarded')->group(function () {
    Route::get('lock', [LockController::class, 'show'])->name('lock.show');
    Route::post('lock/unlock', [LockController::class, 'unlock'])->name('lock.unlock');
});

Route::middleware(['onboarded', 'unlocked', 'reminders'])->group(function () {
    Route::post('lock', [LockController::class, 'lock'])->name('lock.lock');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('transfers/create', [TransactionController::class, 'createTransfer'])->name('transfers.create');
    Route::resource('transactions', TransactionController::class)->except(['show']);

    Route::patch('accounts/{account}/restore', [AccountController::class, 'restore'])->name('accounts.restore');
    Route::resource('accounts', AccountController::class)->except(['show']);

    Route::patch('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::resource('categories', CategoryController::class)->except(['show']);

    Route::resource('budgets', BudgetController::class)->except(['show']);
    Route::post('budgets/{budget}/categories', [CategoryBudgetController::class, 'store'])->name('budgets.categories.store');
    Route::put('budgets/{budget}/categories/{categoryBudget}', [CategoryBudgetController::class, 'update'])->name('budgets.categories.update');
    Route::delete('budgets/{budget}/categories/{categoryBudget}', [CategoryBudgetController::class, 'destroy'])->name('budgets.categories.destroy');

    Route::post('savings/{goal}/contributions', [SavingsGoalController::class, 'contribute'])->name('savings.contributions.store');
    Route::delete('savings/{goal}/contributions/{contribution}', [SavingsGoalController::class, 'destroyContribution'])->name('savings.contributions.destroy');
    Route::resource('savings', SavingsGoalController::class)->except(['show'])->parameters(['savings' => 'goal']);

    Route::post('recurring/{recurring}/paid', [RecurringTransactionController::class, 'markPaid'])->name('recurring.paid');
    Route::post('recurring/{recurring}/skipped', [RecurringTransactionController::class, 'markSkipped'])->name('recurring.skipped');
    Route::patch('recurring/{recurring}/pause', [RecurringTransactionController::class, 'pause'])->name('recurring.pause');
    Route::patch('recurring/{recurring}/resume', [RecurringTransactionController::class, 'resume'])->name('recurring.resume');
    Route::resource('recurring', RecurringTransactionController::class)->except(['show']);

    Route::get('insights', [InsightsController::class, 'index'])->name('insights.index');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
    Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
    Route::post('settings/restore', [SettingsController::class, 'restore'])->name('settings.restore');
    Route::put('settings/security', [SettingsController::class, 'security'])->name('settings.security');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});
