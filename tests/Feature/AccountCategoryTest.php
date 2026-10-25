<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class AccountCategoryTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
    }

    public function test_removing_default_from_an_account_reassigns_a_new_default(): void
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

        $this->put(route('accounts.update', $cash), [
            'name' => 'Cash',
            'type' => 'cash',
            'opening_balance' => '0.00',
            'color' => '#635BFF',
            'icon' => 'wallet',
        ])->assertRedirect();

        $this->assertFalse($cash->fresh()->is_default);
        $this->assertTrue($bank->fresh()->is_default);
        $this->assertSame($bank->id, AppSetting::current()->default_account_id);
    }

    public function test_the_last_default_account_stays_default_when_unchecked(): void
    {
        $cash = $this->defaultAccount();

        $this->put(route('accounts.update', $cash), [
            'name' => 'Cash',
            'type' => 'cash',
            'opening_balance' => '0.00',
            'color' => '#635BFF',
            'icon' => 'wallet',
        ])->assertRedirect();

        $this->assertTrue($cash->fresh()->is_default);
    }

    public function test_unused_categories_can_change_type(): void
    {
        $category = Category::query()->create([
            'name' => 'Freelance projects',
            'type' => 'expense',
            'icon' => 'laptop',
            'color' => '#4395F7',
            'is_essential' => false,
        ]);

        $this->put(route('categories.update', $category), [
            'name' => 'Freelance projects',
            'type' => 'income',
            'icon' => 'laptop',
            'color' => '#4395F7',
        ])->assertRedirect(route('categories.index', ['type' => 'income']));

        $this->assertSame('income', $category->fresh()->type);
        $this->assertFalse($category->fresh()->is_essential);
    }

    public function test_referenced_categories_cannot_change_type(): void
    {
        $category = $this->category('expense', 'Food & Dining');
        $account = $this->defaultAccount();

        Transaction::query()->create([
            'type' => 'expense',
            'amount_minor' => 10000,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'occurred_at' => now(),
            'payment_method' => 'cash',
        ]);

        $this->from(route('categories.edit', $category))
            ->put(route('categories.update', $category), [
                'name' => 'Food & Dining',
                'type' => 'income',
                'icon' => 'utensils',
                'color' => '#F05B6B',
            ])
            ->assertSessionHasErrors('type');

        $this->assertSame('expense', $category->fresh()->type);
    }
}
