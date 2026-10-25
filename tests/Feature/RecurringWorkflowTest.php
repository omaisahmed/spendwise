<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\RecurringService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class RecurringWorkflowTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    private RecurringService $recurring;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
        $this->recurring = app(RecurringService::class);
    }

    public function test_marking_paid_is_idempotent_and_completes_at_the_end_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-31 09:15:00'));

        $schedule = $this->schedule([
            'frequency' => 'monthly',
            'anchor_day' => 31,
            'start_date' => '2026-01-31',
            'end_date' => '2026-02-28',
            'next_due_date' => '2026-01-31',
        ]);

        $first = $this->recurring->markPaid($schedule, CarbonImmutable::parse('2026-01-31'));

        $this->assertSame(50000, $first->amount_minor);
        $this->assertSame('2026-01-31 09:15:00', $first->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28', $schedule->fresh()->next_due_date->format('Y-m-d'));
        $this->assertSame(RecurringTransaction::STATUS_ACTIVE, $schedule->fresh()->status);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('recurring_occurrences', 1);

        $repeated = $this->recurring->markPaid($schedule->fresh(), CarbonImmutable::parse('2026-01-31'));

        $this->assertSame($first->id, $repeated->id);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('recurring_occurrences', 1);

        $final = $this->recurring->markPaid($schedule->fresh(), CarbonImmutable::parse('2026-02-28'));

        $this->assertSame('2026-03-31', $schedule->fresh()->next_due_date->format('Y-m-d'));
        $this->assertSame(RecurringTransaction::STATUS_COMPLETED, $schedule->fresh()->status);
        $this->assertDatabaseCount('transactions', 2);

        $finalRepeated = $this->recurring->markPaid($schedule->fresh(), CarbonImmutable::parse('2026-02-28'));

        $this->assertSame($final->id, $finalRepeated->id);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_skipping_advances_once_and_is_idempotent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-15 10:00:00'));

        $schedule = $this->schedule([
            'frequency' => 'weekly',
            'start_date' => '2026-01-15',
            'next_due_date' => '2026-01-15',
        ]);

        $this->recurring->markSkipped($schedule, CarbonImmutable::parse('2026-01-15'));
        $this->recurring->markSkipped($schedule->fresh(), CarbonImmutable::parse('2026-01-15'));

        $this->assertSame('2026-01-22', $schedule->fresh()->next_due_date->format('Y-m-d'));
        $this->assertDatabaseCount('recurring_occurrences', 1);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_only_active_schedules_can_be_processed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-15 10:00:00'));

        $schedule = $this->schedule([
            'status' => RecurringTransaction::STATUS_PAUSED,
        ]);

        try {
            $this->recurring->markPaid($schedule, CarbonImmutable::parse('2026-01-15'));
            $this->fail('A paused schedule should not be marked paid.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Only active schedules can be processed.', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->recurring->markSkipped($schedule, CarbonImmutable::parse('2026-01-15'));
    }

    public function test_due_notifications_are_deduplicated(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-15 10:00:00'));

        $this->schedule([
            'start_date' => '2026-01-10',
            'next_due_date' => '2026-01-10',
        ]);

        $this->assertSame(1, $this->recurring->refreshDueNotifications());
        $this->assertSame(0, $this->recurring->refreshDueNotifications());
        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertSame('recurring', AppNotification::query()->firstOrFail()->type);
    }

    private function schedule(array $overrides = []): RecurringTransaction
    {
        $account = $this->defaultAccount();
        $category = Category::query()->where('type', 'expense')->firstOrFail();

        return RecurringTransaction::query()->create(array_merge([
            'type' => Transaction::TYPE_EXPENSE,
            'amount_minor' => 50000,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'frequency' => 'monthly',
            'anchor_day' => 15,
            'start_date' => '2026-01-15',
            'next_due_date' => '2026-01-15',
            'merchant' => 'Test subscription',
            'payment_method' => 'cash',
            'reminder_enabled' => true,
            'status' => RecurringTransaction::STATUS_ACTIVE,
        ], $overrides));
    }
}
