<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\CategoryBudget;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\NotificationService;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
    }

    public function test_notifications_without_a_dedupe_key_are_always_created(): void
    {
        AppNotification::record('First', 'First body');
        AppNotification::record('Second', 'Second body');

        $this->assertDatabaseCount('app_notifications', 2);
    }

    public function test_notifications_with_the_same_dedupe_key_are_created_once(): void
    {
        $first = AppNotification::record('Reminder', 'Body', 'warning', [], 'due-2026-01-01');
        $second = AppNotification::record('Reminder again', 'Other body', 'warning', [], 'due-2026-01-01');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Reminder', $second->title);
        $this->assertDatabaseCount('app_notifications', 1);
    }

    public function test_budget_notifications_are_created_after_a_transaction_mutation(): void
    {
        $month = now()->format('Y-m');
        $budget = Budget::query()->create([
            'name' => 'Monthly spending',
            'month' => $month,
            'amount_minor' => 100000,
            'warning_threshold' => 80,
        ]);

        $this->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => '850.00',
            'category_id' => $this->category('expense', 'Food & Dining')->id,
            'account_id' => $this->defaultAccount()->id,
            'occurred_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'dedupe_key' => 'budget-'.$budget->id.'-warning-'.$month,
            'type' => 'budget',
        ]);

        $this->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => '200.00',
            'category_id' => $this->category('expense', 'Food & Dining')->id,
            'account_id' => $this->defaultAccount()->id,
            'occurred_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'dedupe_key' => 'budget-'.$budget->id.'-exceeded-'.$month,
            'type' => 'budget',
        ]);
    }

    public function test_category_budgets_and_completed_savings_goals_notify_once(): void
    {
        $month = now()->format('Y-m');
        $category = $this->category('expense', 'Food & Dining');
        $budget = Budget::query()->create([
            'name' => 'Monthly spending',
            'month' => $month,
            'amount_minor' => 500000,
            'warning_threshold' => 80,
        ]);
        $categoryBudget = CategoryBudget::query()->create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'amount_minor' => 10000,
            'warning_threshold' => 80,
        ]);

        Transaction::query()->create([
            'type' => Transaction::TYPE_EXPENSE,
            'amount_minor' => 12000,
            'category_id' => $category->id,
            'account_id' => $this->defaultAccount()->id,
            'occurred_at' => now(),
            'payment_method' => 'cash',
        ]);

        $goal = SavingsGoal::query()->create([
            'name' => 'Emergency fund',
            'target_minor' => 50000,
            'target_date' => now()->addYear()->toDateString(),
            'icon' => 'piggy-bank',
            'color' => '#16A878',
            'status' => SavingsGoal::STATUS_ACTIVE,
        ]);
        SavingsContribution::query()->create([
            'savings_goal_id' => $goal->id,
            'amount_minor' => 50000,
            'occurred_at' => now(),
        ]);

        $this->assertSame(2, app(NotificationService::class)->refreshAll());
        $this->assertSame(0, app(NotificationService::class)->refreshAll());

        $this->assertDatabaseHas('app_notifications', [
            'dedupe_key' => 'category-budget-'.$categoryBudget->id.'-exceeded-'.$month,
            'type' => 'budget',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'dedupe_key' => 'savings-'.$goal->id.'-50000',
            'type' => 'savings',
        ]);
    }

    public function test_disabled_notifications_prevent_new_records(): void
    {
        AppSetting::current()->update(['notifications_enabled' => false]);
        AppSettings::forget();

        Budget::query()->create([
            'name' => 'Monthly spending',
            'month' => now()->format('Y-m'),
            'amount_minor' => 10000,
            'warning_threshold' => 80,
        ]);

        $this->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => '90.00',
            'category_id' => $this->category('expense', 'Food & Dining')->id,
            'account_id' => $this->defaultAccount()->id,
            'occurred_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_notification_rows_support_swipe_to_delete(): void
    {
        $notification = AppNotification::record('Budget alert', 'Body', 'budget');

        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('data-swipe-row', false)
            ->assertSee('data-swipe-content', false)
            ->assertSee('data-swipe-form', false)
            ->assertSee('action="'.route('notifications.destroy', $notification).'"', false);

        $this->delete(route('notifications.destroy', $notification))
            ->assertRedirect(route('notifications.index'));

        $this->assertDatabaseMissing('app_notifications', ['id' => $notification->id]);
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_locally(): void
    {
        $notification = AppNotification::record('Budget alert', 'Body', 'budget', [
            'url' => route('settings.index'),
        ]);

        $this->post(route('notifications.open', $notification))
            ->assertRedirect(route('settings.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_opening_a_notification_rejects_external_urls(): void
    {
        $notification = AppNotification::record('Budget alert', 'Body', 'budget', [
            'url' => 'https://example.com/phishing',
        ]);

        $this->post(route('notifications.open', $notification))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
