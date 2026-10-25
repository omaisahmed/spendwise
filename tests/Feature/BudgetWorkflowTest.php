<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\CategoryBudget;
use App\Models\Transaction;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class BudgetWorkflowTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
    }

    public function test_budget_allocations_are_limited_by_the_overall_budget(): void
    {
        $month = now()->format('Y-m');
        $food = $this->category('expense', 'Food & Dining');
        $transport = $this->category('expense', 'Transport');

        $this->post(route('budgets.store'), [
            'name' => 'Monthly plan',
            'month' => $month,
            'amount' => '1000.00',
            'warning_threshold' => 80,
        ])->assertRedirect();

        $budget = Budget::query()->firstOrFail();

        $this->post(route('budgets.categories.store', $budget), [
            'category_id' => $food->id,
            'amount' => '600.00',
        ])->assertRedirect();

        $this->from(route('budgets.index', ['month' => $month]))
            ->post(route('budgets.categories.store', $budget), [
                'category_id' => $transport->id,
                'amount' => '500.00',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(60000, (int) $budget->categoryBudgets()->sum('amount_minor'));

        $this->from(route('budgets.edit', $budget))->put(route('budgets.update', $budget), [
            'name' => 'Monthly plan',
            'month' => $month,
            'amount' => '500.00',
            'warning_threshold' => 80,
        ])->assertSessionHasErrors('amount');
    }

    public function test_budget_status_tracks_spending_thresholds_and_category_limits(): void
    {
        $month = now()->format('Y-m');
        $food = $this->category('expense', 'Food & Dining');
        $account = $this->defaultAccount();

        $budget = Budget::query()->create([
            'name' => 'Monthly plan',
            'month' => $month,
            'amount_minor' => 100000,
            'warning_threshold' => 80,
        ]);

        $categoryBudget = CategoryBudget::query()->create([
            'budget_id' => $budget->id,
            'category_id' => $food->id,
            'amount_minor' => 50000,
            'warning_threshold' => 50,
        ]);

        Transaction::query()->create([
            'type' => 'expense',
            'amount_minor' => 55000,
            'category_id' => $food->id,
            'account_id' => $account->id,
            'occurred_at' => now(),
            'payment_method' => 'cash',
        ]);

        $status = app(FinanceService::class)->budgetStatus($budget);

        $this->assertSame(55000, $status['spent']);
        $this->assertSame(45000, $status['remaining']);
        $this->assertSame(55, $status['percent']);
        $this->assertSame('normal', $status['status']);

        $categoryStatus = $status['categories'][0];

        $this->assertSame(55000, $categoryStatus['spent']);
        $this->assertSame(110, $categoryStatus['percent']);
        $this->assertSame('exceeded', $categoryStatus['status']);

        $this->put(route('budgets.categories.update', [$budget, $categoryBudget]), [
            'category_id' => $food->id,
            'amount' => '500.00',
        ])->assertRedirect();

        $this->assertSame(50, $categoryBudget->fresh()->warning_threshold);
    }

    public function test_deleting_a_budget_removes_limits_but_preserves_transactions(): void
    {
        $month = now()->format('Y-m');
        $food = $this->category('expense', 'Food & Dining');
        $account = $this->defaultAccount();

        $budget = Budget::query()->create([
            'name' => 'Monthly plan',
            'month' => $month,
            'amount_minor' => 100000,
            'warning_threshold' => 80,
        ]);

        CategoryBudget::query()->create([
            'budget_id' => $budget->id,
            'category_id' => $food->id,
            'amount_minor' => 50000,
        ]);

        $transaction = Transaction::query()->create([
            'type' => 'expense',
            'amount_minor' => 25000,
            'category_id' => $food->id,
            'account_id' => $account->id,
            'occurred_at' => now(),
            'payment_method' => 'cash',
        ]);

        $this->delete(route('budgets.destroy', $budget))->assertRedirect();

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
        $this->assertDatabaseMissing('category_budgets', ['budget_id' => $budget->id]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }
}
