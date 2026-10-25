<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryBudget;
use App\Models\RecurringTransaction;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Support\AppSettings;
use Database\Seeders\SpendWiseDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpendWiseDefaultsSeeder::class);
        AppSetting::current()->update([
            'display_name' => 'Test User',
            'onboarding_completed' => true,
        ]);
        AppSettings::forget();
    }

    public function test_all_application_pages_render(): void
    {
        $account = Account::query()->firstOrFail();
        $expense = Category::query()->where('type', 'expense')->firstOrFail();
        $income = Category::query()->where('type', 'income')->firstOrFail();

        $transaction = Transaction::query()->create([
            'type' => 'expense',
            'amount_minor' => 125000,
            'category_id' => $expense->id,
            'account_id' => $account->id,
            'occurred_at' => now()->startOfMonth()->addDay(),
            'payment_method' => 'cash',
            'merchant' => 'Render test merchant',
        ]);

        $month = now()->format('Y-m');
        $budget = Budget::query()->create([
            'name' => 'Test monthly plan',
            'month' => $month,
            'amount_minor' => 5000000,
            'warning_threshold' => 80,
        ]);
        CategoryBudget::query()->create([
            'budget_id' => $budget->id,
            'category_id' => $expense->id,
            'amount_minor' => 1000000,
        ]);

        $goal = SavingsGoal::query()->create([
            'name' => 'Test goal',
            'icon' => 'piggy-bank',
            'color' => '#16A878',
            'target_minor' => 1000000,
            'target_date' => now()->addMonth()->format('Y-m-d'),
            'status' => 'active',
        ]);
        SavingsContribution::query()->create([
            'savings_goal_id' => $goal->id,
            'amount_minor' => 250000,
            'occurred_at' => now()->startOfMonth()->addDay(),
            'account_id' => $account->id,
        ]);

        $recurring = RecurringTransaction::query()->create([
            'type' => 'expense',
            'amount_minor' => 50000,
            'category_id' => $expense->id,
            'account_id' => $account->id,
            'frequency' => 'monthly',
            'anchor_day' => 1,
            'start_date' => now()->startOfMonth(),
            'next_due_date' => now()->subDay(),
            'merchant' => 'Test subscription',
            'payment_method' => 'cash',
            'reminder_enabled' => true,
            'status' => 'active',
        ]);

        AppNotification::record('Test notification', 'This is a render test.', 'info');

        $this->withSession(['spendwise_unlocked' => true]);

        $urls = [
            route('dashboard'),
            route('transactions.index'),
            route('transactions.create'),
            route('transfers.create'),
            route('transactions.edit', $transaction),
            route('accounts.index'),
            route('accounts.create'),
            route('accounts.edit', $account),
            route('categories.index', ['type' => 'expense']),
            route('categories.index', ['type' => 'income']),
            route('categories.create', ['type' => 'expense']),
            route('categories.edit', $expense),
            route('budgets.index', ['month' => $month]),
            route('budgets.create', ['month' => now()->addMonth()->format('Y-m')]),
            route('budgets.edit', $budget),
            route('savings.index'),
            route('savings.create'),
            route('savings.edit', $goal),
            route('recurring.index'),
            route('recurring.create'),
            route('recurring.edit', $recurring),
            route('insights.index'),
            route('notifications.index'),
            route('settings.index'),
            route('reports.index'),
            route('reports.index', ['type' => 'expense']),
            route('reports.index', ['type' => 'category']),
            route('reports.index', ['type' => 'account']),
            route('reports.index', ['type' => 'budget']),
            route('reports.index', ['type' => 'custom']),
        ];

        foreach ($urls as $url) {
            $this->assertSame(200, $this->get($url)->status(), 'Failed rendering: '.$url);
        }

        $this->assertNotNull($income);
    }

    public function test_report_exports_download(): void
    {
        $this->withSession(['spendwise_unlocked' => true]);

        $this->get(route('reports.csv', ['type' => 'monthly_summary']))
            ->assertOk()
            ->assertDownload();

        $this->get(route('reports.pdf', ['type' => 'monthly_summary']))
            ->assertOk()
            ->assertDownload();
    }
}
