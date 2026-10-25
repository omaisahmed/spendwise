<?php

namespace Tests\Feature;

use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class SavingsWorkflowTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
    }

    public function test_contributions_and_withdrawals_keep_a_running_saved_total(): void
    {
        $goal = $this->goal();

        $this->post(route('savings.contributions.store', $goal), [
            'amount' => '1000.00',
            'occurred_at' => now()->toDateString(),
            'note' => 'First deposit',
        ])->assertRedirect();

        $this->post(route('savings.contributions.store', $goal), [
            'amount' => '-250.00',
            'occurred_at' => now()->toDateString(),
            'note' => 'Withdrawal',
        ])->assertRedirect();

        $this->assertSame(75000, $goal->savedMinor());
        $this->assertDatabaseCount('savings_contributions', 2);
    }

    public function test_withdrawals_cannot_exceed_the_saved_total(): void
    {
        $goal = $this->goal();

        $this->post(route('savings.contributions.store', $goal), [
            'amount' => '100.00',
            'occurred_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->from(route('savings.index'))->post(route('savings.contributions.store', $goal), [
            'amount' => '-150.00',
            'occurred_at' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(10000, $goal->savedMinor());
        $this->assertDatabaseCount('savings_contributions', 1);
    }

    public function test_a_contribution_cannot_be_deleted_if_it_would_make_the_balance_negative(): void
    {
        $goal = $this->goal();

        $deposit = SavingsContribution::query()->create([
            'savings_goal_id' => $goal->id,
            'amount_minor' => 10000,
            'occurred_at' => now(),
        ]);
        $withdrawal = SavingsContribution::query()->create([
            'savings_goal_id' => $goal->id,
            'amount_minor' => -3000,
            'occurred_at' => now(),
        ]);

        $this->from(route('savings.index'))
            ->delete(route('savings.contributions.destroy', [$goal, $deposit]))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('savings_contributions', ['id' => $deposit->id]);
        $this->assertSame(7000, $goal->savedMinor());

        $this->delete(route('savings.contributions.destroy', [$goal, $withdrawal]))->assertRedirect();

        $this->assertSame(10000, $goal->savedMinor());

        $this->delete(route('savings.contributions.destroy', [$goal, $deposit]))->assertRedirect();

        $this->assertSame(0, $goal->savedMinor());
    }

    private function goal(): SavingsGoal
    {
        $this->post(route('savings.store'), [
            'name' => 'Emergency fund',
            'target' => '5000.00',
            'target_date' => now()->addYear()->toDateString(),
            'icon' => 'piggy-bank',
            'color' => '#16A878',
            'status' => 'active',
        ])->assertRedirect();

        return SavingsGoal::query()->firstOrFail();
    }
}
