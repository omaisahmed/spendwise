<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class FinanceWorkflowTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
    }

    public function test_income_expenses_refunds_and_transfers_update_totals_and_balances(): void
    {
        $cash = $this->defaultAccount();
        $bank = Account::query()->create([
            'name' => 'Bank',
            'type' => 'bank',
            'icon' => 'landmark',
            'color' => '#4395F7',
            'opening_balance_minor' => 100000,
            'is_default' => false,
            'is_archived' => false,
        ]);
        $food = $this->category('expense', 'Food & Dining');
        $salary = $this->category('income', 'Salary');
        $today = now()->toDateString();

        $this->post(route('transactions.store'), [
            'type' => 'income',
            'amount' => '5000.00',
            'category_id' => $salary->id,
            'account_id' => $cash->id,
            'occurred_at' => $today,
            'payment_method' => 'bank_transfer',
            'merchant' => 'Employer',
        ])->assertRedirect();

        $this->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => '1000.50',
            'category_id' => $food->id,
            'account_id' => $cash->id,
            'occurred_at' => $today,
            'payment_method' => 'cash',
            'merchant' => 'Restaurant',
        ])->assertRedirect();

        $this->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => '200.00',
            'category_id' => $food->id,
            'account_id' => $cash->id,
            'occurred_at' => $today,
            'payment_method' => 'cash',
            'merchant' => 'Restaurant refund',
            'is_refund' => true,
        ])->assertRedirect();

        $this->post(route('transactions.store'), [
            'type' => 'transfer',
            'amount' => '1000.00',
            'account_id' => $cash->id,
            'to_account_id' => $bank->id,
            'occurred_at' => $today,
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        $this->assertDatabaseCount('transactions', 4);
        $this->assertSame(2, Transaction::query()->where('type', 'expense')->count());

        $finance = app(FinanceService::class);
        $totals = $finance->monthTotals(now()->format('Y-m'));

        $this->assertSame(500000, $totals['income']);
        $this->assertSame(80050, $totals['expenses']);
        $this->assertSame(100050, $totals['gross_expenses']);
        $this->assertSame(20000, $totals['refunds']);
        $this->assertSame(100000, $totals['transfers']);
        $this->assertSame(419950, $totals['balance']);

        $categories = collect($finance->categoryTotals(
            now()->startOfMonth(),
            now()->startOfMonth()->addMonth()
        ))->keyBy('category_id');

        $this->assertSame(80050, $categories[$food->id]['total']);

        $balances = collect($finance->accountBalances())->keyBy(fn ($entry) => $entry['account']->id);

        $this->assertSame(319950, $balances[$cash->id]['balance']);
        $this->assertSame(200000, $balances[$bank->id]['balance']);
        $this->assertSame(519950, $finance->totalAccountBalance());
    }

    public function test_transaction_validation_prevents_invalid_financial_data(): void
    {
        $cash = $this->defaultAccount();
        $food = $this->category('expense', 'Food & Dining');
        $salary = $this->category('income', 'Salary');

        $this->from(route('transactions.create'))->post(route('transactions.store'), [
            'type' => 'transfer',
            'amount' => '50',
            'account_id' => $cash->id,
            'to_account_id' => $cash->id,
            'occurred_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('to_account_id');

        $this->from(route('transactions.create'))->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => '50',
            'category_id' => $salary->id,
            'account_id' => $cash->id,
            'occurred_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('category_id');

        $this->from(route('transactions.create'))->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => '0',
            'category_id' => $food->id,
            'account_id' => $cash->id,
            'occurred_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_editing_a_transaction_into_and_out_of_a_transfer_manages_the_transfer_group(): void
    {
        $cash = $this->defaultAccount();
        $bank = Account::query()->create([
            'name' => 'Bank',
            'type' => 'bank',
            'icon' => 'landmark',
            'color' => '#4395F7',
            'opening_balance_minor' => 0,
            'is_default' => false,
            'is_archived' => false,
        ]);
        $food = $this->category('expense', 'Food & Dining');

        $transaction = Transaction::query()->create([
            'type' => 'expense',
            'amount_minor' => 50000,
            'category_id' => $food->id,
            'account_id' => $cash->id,
            'occurred_at' => now(),
            'payment_method' => 'cash',
        ]);

        $this->put(route('transactions.update', $transaction), [
            'type' => 'transfer',
            'amount' => '500.00',
            'account_id' => $cash->id,
            'to_account_id' => $bank->id,
            'occurred_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        $this->assertNotNull($transaction->fresh()->transfer_group);
        $this->assertSame($bank->id, $transaction->fresh()->to_account_id);

        $this->put(route('transactions.update', $transaction), [
            'type' => 'expense',
            'amount' => '500.00',
            'category_id' => $food->id,
            'account_id' => $cash->id,
            'occurred_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertNull($transaction->fresh()->transfer_group);
        $this->assertNull($transaction->fresh()->to_account_id);
    }
}
