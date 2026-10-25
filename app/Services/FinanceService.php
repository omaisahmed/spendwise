<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Support\AppSettings;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class FinanceService
{
    public function __construct(private PeriodService $periods) {}

    public function rangeTotals(CarbonInterface $start, CarbonInterface $endExclusive): array
    {
        return $this->totalsForQuery(Transaction::query()->between($start, $endExclusive));
    }

    public function totalsForQuery(Builder $query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->selectRaw("type, COALESCE(SUM(CASE WHEN type = 'expense' AND is_refund = 1 THEN -amount_minor ELSE amount_minor END), 0) as total")
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = (int) ($rows[Transaction::TYPE_INCOME] ?? 0);
        $expenses = (int) ($rows[Transaction::TYPE_EXPENSE] ?? 0);
        $transfers = (int) ($rows[Transaction::TYPE_TRANSFER] ?? 0);
        $refunds = (int) (clone $query)
            ->reorder()
            ->expenses()
            ->where('is_refund', true)
            ->sum('amount_minor');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'gross_expenses' => $expenses + $refunds,
            'refunds' => $refunds,
            'transfers' => $transfers,
            'balance' => $income - $expenses,
            'savings_rate' => $income > 0 ? (int) round((($income - $expenses) / $income) * 100) : null,
        ];
    }

    public function monthTotals(string $month): array
    {
        [$start, $end] = $this->periods->monthRange($month);

        return $this->rangeTotals($start, $end);
    }

    public function comparisonTotals(CarbonInterface $start, CarbonInterface $endExclusive): array
    {
        $days = max(1, (int) $start->diffInDays($endExclusive));
        $previousEnd = CarbonImmutable::instance($start);
        $previousStart = $previousEnd->subDays($days);

        return [
            'range' => [$previousStart, $previousEnd],
            'totals' => $this->rangeTotals($previousStart, $previousEnd),
        ];
    }

    public function categoryTotals(CarbonInterface $start, CarbonInterface $endExclusive, string $type = Transaction::TYPE_EXPENSE): array
    {
        return $this->categoryTotalsForQuery(Transaction::query()->between($start, $endExclusive), $type);
    }

    public function categoryTotalsForQuery(Builder $query, string $type = Transaction::TYPE_EXPENSE): array
    {
        $sign = $type === Transaction::TYPE_EXPENSE
            ? 'CASE WHEN transactions.is_refund = 1 THEN -transactions.amount_minor ELSE transactions.amount_minor END'
            : 'transactions.amount_minor';

        return (clone $query)
            ->reorder()
            ->where('transactions.type', $type)
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw("categories.id as category_id, categories.name, categories.color, categories.icon, categories.is_essential, SUM({$sign}) as total, COUNT(*) as entries")
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon', 'categories.is_essential')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category_id' => (int) $row->category_id,
                'name' => $row->name,
                'color' => $row->color,
                'icon' => $row->icon,
                'is_essential' => (bool) $row->is_essential,
                'total' => (int) $row->total,
                'entries' => (int) $row->entries,
            ])
            ->all();
    }

    public function dailyNetExpenses(CarbonInterface $start, CarbonInterface $endExclusive): array
    {
        $rows = Transaction::query()
            ->between($start, $endExclusive)
            ->expenses()
            ->selectRaw("strftime('%Y-%m-%d', occurred_at) as day, SUM(CASE WHEN is_refund = 1 THEN -amount_minor ELSE amount_minor END) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $out = [];
        $cursor = CarbonImmutable::instance($start)->startOfDay();
        $endDay = CarbonImmutable::instance($endExclusive)->subDay()->startOfDay();

        while ($cursor->lessThanOrEqualTo($endDay)) {
            $key = $cursor->format('Y-m-d');
            $out[] = ['date' => $key, 'label' => $cursor->format('d'), 'total' => (int) ($rows[$key] ?? 0)];
            $cursor = $cursor->addDay();
        }

        return $out;
    }

    public function series(CarbonInterface $start, CarbonInterface $endExclusive, string $granularity = 'day'): array
    {
        $daily = $this->dailyNetExpenses($start, $endExclusive);

        if ($granularity === 'day') {
            return $daily;
        }

        $buckets = [];
        foreach ($daily as $point) {
            $date = CarbonImmutable::parse($point['date'], config('app.timezone'));

            if ($granularity === 'week') {
                $key = $date->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d');
                $label = 'Wk '.$date->startOfWeek(CarbonInterface::MONDAY)->format('d M');
            } elseif ($granularity === 'month') {
                $key = $date->format('Y-m');
                $label = $date->format('M');
            } else {
                $key = $date->format('Y-m-d');
                $label = $date->format('d M');
            }

            if (! isset($buckets[$key])) {
                $buckets[$key] = ['date' => $key, 'label' => $label, 'total' => 0];
            }

            $buckets[$key]['total'] += $point['total'];
        }

        return array_values($buckets);
    }

    public function lastMonthsTrend(int $count = 6): array
    {
        $current = $this->periods->currentMonthKey();
        $months = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $key = $this->periods->shiftMonth($current, -$i);
            $totals = $this->monthTotals($key);
            $months[] = [
                'month' => $key,
                'label' => $this->periods->monthLabel($key, 'M'),
                'income' => $totals['income'],
                'expenses' => $totals['expenses'],
            ];
        }

        return $months;
    }

    public function accountBalances(): array
    {
        $accounts = Account::query()->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name')->get();

        $outgoing = Transaction::query()
            ->selectRaw("account_id, SUM(CASE
                WHEN type = 'income' THEN amount_minor
                WHEN type = 'expense' AND is_refund = 1 THEN amount_minor
                WHEN type = 'expense' THEN -amount_minor
                WHEN type = 'transfer' THEN -amount_minor
                ELSE 0 END) as movement")
            ->groupBy('account_id')
            ->pluck('movement', 'account_id');

        $incoming = Transaction::query()
            ->whereNotNull('to_account_id')
            ->selectRaw('to_account_id, SUM(amount_minor) as movement')
            ->groupBy('to_account_id')
            ->pluck('movement', 'to_account_id');

        return $accounts->map(function (Account $account) use ($outgoing, $incoming) {
            $balance = $account->opening_balance_minor
                + (int) ($outgoing[$account->id] ?? 0)
                + (int) ($incoming[$account->id] ?? 0);

            return [
                'account' => $account,
                'balance' => $balance,
            ];
        })->all();
    }

    public function totalAccountBalance(): int
    {
        return array_sum(array_column($this->accountBalances(), 'balance'));
    }

    public function budgetStatus(Budget $budget): array
    {
        [$start, $end] = $this->periods->monthRange($budget->month);
        $totals = $this->rangeTotals($start, $end);
        $spent = $totals['expenses'];
        $remaining = $budget->amount_minor - $spent;
        $percent = Money::percent($spent, $budget->amount_minor);
        $threshold = $budget->warning_threshold ?: (int) AppSettings::get()->budget_warning_threshold;

        $status = match (true) {
            $spent > $budget->amount_minor => 'exceeded',
            $percent >= $threshold => 'warning',
            default => 'normal',
        };

        $categoryBudgets = $budget->categoryBudgets()->with('category')->get()->map(function ($categoryBudget) use ($start, $end) {
            $spent = (int) Transaction::query()
                ->between($start, $end)
                ->expenses()
                ->where('category_id', $categoryBudget->category_id)
                ->selectRaw('COALESCE(SUM(CASE WHEN is_refund = 1 THEN -amount_minor ELSE amount_minor END), 0) as total')
                ->value('total');
            $percent = Money::percent($spent, $categoryBudget->amount_minor);
            $threshold = $categoryBudget->warning_threshold ?: $categoryBudget->budget->warning_threshold;
            $status = match (true) {
                $spent > $categoryBudget->amount_minor => 'exceeded',
                $percent >= $threshold => 'warning',
                default => 'normal',
            };

            return [
                'model' => $categoryBudget,
                'category' => $categoryBudget->category,
                'spent' => $spent,
                'remaining' => $categoryBudget->amount_minor - $spent,
                'percent' => $percent,
                'status' => $status,
            ];
        })->sortByDesc('percent')->values()->all();

        return [
            'budget' => $budget,
            'start' => $start,
            'end' => $end,
            'spent' => $spent,
            'remaining' => $remaining,
            'percent' => $percent,
            'threshold' => $threshold,
            'status' => $status,
            'categories' => $categoryBudgets,
        ];
    }

    public function budgetStatuses(string $month): array
    {
        return Budget::query()
            ->forMonth($month)
            ->with(['categoryBudgets.category'])
            ->get()
            ->map(fn (Budget $budget) => $this->budgetStatus($budget))
            ->all();
    }

    public function primaryBudgetForMonth(string $month): ?array
    {
        $budget = Budget::query()->forMonth($month)->first();

        return $budget ? $this->budgetStatus($budget) : null;
    }

    public function savingsOverview(): array
    {
        $goals = SavingsGoal::query()->with('contributions')->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")->get();

        $items = $goals->map(function (SavingsGoal $goal) {
            $saved = (int) $goal->contributions->sum('amount_minor');
            $percent = Money::percent(max(0, $saved), max(1, $goal->target_minor));
            $remaining = max(0, $goal->target_minor - $saved);

            return [
                'goal' => $goal,
                'saved' => $saved,
                'remaining' => $remaining,
                'percent' => $percent,
                'status' => $saved >= $goal->target_minor ? 'reached' : $goal->status,
            ];
        })->all();

        return [
            'items' => $items,
            'total_target' => (int) $goals->sum('target_minor'),
            'total_saved' => (int) array_sum(array_column($items, 'saved')),
        ];
    }

    public function activeRecurring(int $limit = 4): array
    {
        return RecurringTransaction::query()
            ->with(['category', 'account', 'toAccount'])
            ->active()
            ->orderBy('next_due_date')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function recurringMonthlyCommitment(): int
    {
        return (int) RecurringTransaction::query()
            ->active()
            ->get()
            ->sum(fn (RecurringTransaction $item) => match ($item->frequency) {
                'daily' => intdiv($item->amount_minor * 365, 12),
                'weekly' => intdiv($item->amount_minor * 52, 12),
                'monthly' => $item->amount_minor,
                'yearly' => intdiv($item->amount_minor, 12),
                default => 0,
            });
    }

    public function dueRecurringCount(): int
    {
        return RecurringTransaction::query()->due()->count();
    }

    public function dashboard(string $month): array
    {
        [$start, $end] = $this->periods->monthRange($month);
        $totals = $this->rangeTotals($start, $end);
        $comparison = $this->comparisonTotals($start, $end);
        $categories = $this->categoryTotals($start, $end);
        $daily = $this->dailyNetExpenses($start, $end);
        $budget = $this->primaryBudgetForMonth($month);
        $recent = Transaction::query()
            ->with(['category', 'account', 'toAccount'])
            ->between($start, $end)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
        $savings = $this->savingsOverview();
        $activeGoals = array_values(array_filter($savings['items'], fn ($item) => $item['goal']->status === SavingsGoal::STATUS_ACTIVE));
        $top = $categories[0] ?? null;
        $highestDay = collect($daily)->sortByDesc('total')->first();
        $avgDaily = count($daily) > 0 ? intdiv($totals['expenses'], count($daily)) : 0;

        $observations = [];
        if ($totals['expenses'] > 0 && $top) {
            $share = Money::percent($top['total'], $totals['expenses']);
            $observations[] = [
                'icon' => 'chart-pie',
                'tone' => 'primary',
                'text' => $top['name'].' accounts for '.$share.'% of your spending this period.',
            ];
        }
        if ($budget) {
            $observations[] = match ($budget['status']) {
                'exceeded' => ['icon' => 'triangle-alert', 'tone' => 'expense', 'text' => 'You are '.Money::format(abs($budget['remaining'])).' over your monthly budget.'],
                'warning' => ['icon' => 'triangle-alert', 'tone' => 'warning', 'text' => 'You have used '.$budget['percent'].'% of your monthly budget.'],
                default => ['icon' => 'circle-check', 'tone' => 'income', 'text' => Money::format($budget['remaining']).' remains in your monthly budget.'],
            };
        }
        if ($comparison['totals']['expenses'] > 0) {
            $difference = $totals['expenses'] - $comparison['totals']['expenses'];
            $observations[] = [
                'icon' => $difference > 0 ? 'trending-up' : 'trending-down',
                'tone' => $difference > 0 ? 'expense' : 'income',
                'text' => $difference === 0
                    ? 'Spending matches the previous period.'
                    : 'Spending is '.Money::format(abs($difference)).' '.($difference > 0 ? 'higher' : 'lower').' than the previous period.',
            ];
        }
        if ($totals['income'] > 0 && $totals['savings_rate'] !== null) {
            $observations[] = [
                'icon' => 'piggy-bank',
                'tone' => $totals['savings_rate'] >= 20 ? 'income' : 'warning',
                'text' => 'Your savings rate is '.$totals['savings_rate'].'% for this period.',
            ];
        }

        return [
            'month' => $month,
            'range' => [$start, $end],
            'totals' => $totals,
            'comparison' => $comparison,
            'categories' => $categories,
            'daily' => $daily,
            'highest_day' => $highestDay,
            'average_daily' => $avgDaily,
            'budget' => $budget,
            'recent' => $recent,
            'savings' => $savings,
            'active_goals' => array_slice($activeGoals, 0, 3),
            'upcoming_recurring' => $this->activeRecurring(3),
            'top_category' => $top,
            'total_balance' => $this->totalAccountBalance(),
            'recurring_commitment' => $this->recurringMonthlyCommitment(),
            'due_recurring' => $this->dueRecurringCount(),
            'observations' => array_slice($observations, 0, 4),
            'previous_range' => $comparison['range'],
        ];
    }

    public function insights(CarbonInterface $start, CarbonInterface $endExclusive, string $granularity = 'day'): array
    {
        $totals = $this->rangeTotals($start, $endExclusive);
        $categories = $this->categoryTotals($start, $endExclusive);
        $daily = $this->dailyNetExpenses($start, $endExclusive);
        $days = max(1, (int) $start->diffInDays($endExclusive));
        $activeDays = collect($daily)->filter(fn ($point) => $point['total'] > 0)->count();
        $highest = collect($daily)->sortByDesc('total')->first();
        $essential = collect($categories)->where('is_essential', true)->sum('total');
        $discretionary = $totals['expenses'] - $essential;
        $comparison = $this->comparisonTotals($start, $endExclusive);
        $budget = $this->primaryBudgetForMonth($this->periods->monthKeyFor($start));

        return [
            'range' => [$start, $endExclusive],
            'granularity' => $granularity,
            'totals' => $totals,
            'categories' => $categories,
            'series' => $this->series($start, $endExclusive, $granularity),
            'daily' => $daily,
            'average_daily' => $activeDays > 0 ? intdiv($totals['expenses'], $activeDays) : 0,
            'active_days' => $activeDays,
            'days' => $days,
            'highest_day' => $highest,
            'top_categories' => array_slice($categories, 0, 5),
            'essential' => $essential,
            'discretionary' => $discretionary,
            'comparison' => $comparison,
            'budget' => $budget,
            'recurring_commitment' => $this->recurringMonthlyCommitment(),
            'savings' => $this->savingsOverview(),
        ];
    }

    public function categorySpend(string $categoryName, CarbonInterface $start, CarbonInterface $endExclusive): int
    {
        $category = Category::query()->where('name', $categoryName)->first();

        if (! $category) {
            return 0;
        }

        return (int) Transaction::query()
            ->between($start, $endExclusive)
            ->expenses()
            ->where('category_id', $category->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN is_refund = 1 THEN -amount_minor ELSE amount_minor END), 0) as total')
            ->value('total');
    }
}
