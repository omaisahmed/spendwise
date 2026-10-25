<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavingsContributionRequest;
use App\Http\Requests\SavingsGoalRequest;
use App\Models\Account;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Services\FinanceService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SavingsGoalController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    public function index()
    {
        $overview = $this->finance->savingsOverview();

        return view('savings.index', [
            'overview' => $overview,
            'accounts' => Account::query()->active()->orderBy('name')->get(),
            'icons' => ['piggy-bank', 'house', 'car', 'plane', 'graduation-cap', 'heart-pulse', 'gift', 'laptop', 'sparkles', 'shield-check'],
            'colors' => ['#635BFF', '#16A878', '#4395F7', '#F2AE43', '#F05B6B', '#8B7CFF', '#14B8A6'],
        ]);
    }

    public function create()
    {
        return view('savings.form', [
            'goal' => null,
            'icons' => ['piggy-bank', 'house', 'car', 'plane', 'graduation-cap', 'heart-pulse', 'gift', 'laptop', 'sparkles', 'shield-check'],
            'colors' => ['#635BFF', '#16A878', '#4395F7', '#F2AE43', '#F05B6B', '#8B7CFF', '#14B8A6'],
        ]);
    }

    public function store(SavingsGoalRequest $request)
    {
        SavingsGoal::query()->create($this->payload($request));

        return redirect()->route('savings.index')->with('success', 'Savings goal created.');
    }

    public function edit(SavingsGoal $goal)
    {
        return view('savings.form', [
            'goal' => $goal,
            'icons' => ['piggy-bank', 'house', 'car', 'plane', 'graduation-cap', 'heart-pulse', 'gift', 'laptop', 'sparkles', 'shield-check'],
            'colors' => ['#635BFF', '#16A878', '#4395F7', '#F2AE43', '#F05B6B', '#8B7CFF', '#14B8A6'],
        ]);
    }

    public function update(SavingsGoalRequest $request, SavingsGoal $goal)
    {
        $goal->update($this->payload($request));

        return redirect()->route('savings.index')->with('success', 'Savings goal updated.');
    }

    public function destroy(SavingsGoal $goal)
    {
        $goal->delete();

        return redirect()->route('savings.index')->with('success', 'Savings goal and its contribution history were deleted.');
    }

    public function contribute(SavingsContributionRequest $request, SavingsGoal $goal)
    {
        $amount = Money::parse($request->input('amount'));

        DB::transaction(function () use ($request, $goal, $amount) {
            $saved = (int) SavingsContribution::query()
                ->where('savings_goal_id', $goal->id)
                ->lockForUpdate()
                ->sum('amount_minor');

            if ($saved + $amount < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'You cannot withdraw more than the '.Money::format($saved).' saved so far.',
                ]);
            }

            $goal->contributions()->create([
                'amount_minor' => $amount,
                'occurred_at' => CarbonImmutable::parse($request->input('occurred_at'), config('app.timezone'))->setTimeFrom(now()),
                'note' => $request->input('note'),
                'account_id' => $request->integer('account_id') ?: null,
            ]);
        });

        return redirect()->route('savings.index')->with('success', $amount > 0 ? 'Contribution added.' : 'Withdrawal recorded.');
    }

    public function destroyContribution(SavingsGoal $goal, SavingsContribution $contribution)
    {
        abort_unless($contribution->savings_goal_id === $goal->id, 404);

        DB::transaction(function () use ($goal, $contribution) {
            $saved = (int) SavingsContribution::query()
                ->where('savings_goal_id', $goal->id)
                ->lockForUpdate()
                ->sum('amount_minor');

            if ($saved - $contribution->amount_minor < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Remove the related withdrawal first so the saved total does not fall below zero.',
                ]);
            }

            $contribution->delete();
        });

        return redirect()->route('savings.index')->with('success', 'Contribution removed.');
    }

    private function payload(SavingsGoalRequest $request): array
    {
        return [
            'name' => $request->input('name'),
            'target_minor' => Money::parse($request->input('target')),
            'target_date' => $request->input('target_date') ?: null,
            'icon' => $request->input('icon'),
            'color' => $request->input('color'),
            'status' => $request->input('status'),
            'notes' => $request->input('notes'),
        ];
    }
}
