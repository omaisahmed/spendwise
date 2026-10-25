<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Support\AppSettings;
use App\Support\Money;

class NotificationService
{
    public function __construct(
        private FinanceService $finance,
        private PeriodService $periods,
        private RecurringService $recurring,
    ) {}

    public function refreshAll(): int
    {
        if (! AppSettings::get()->notifications_enabled) {
            return 0;
        }

        $created = $this->recurring->refreshDueNotifications();
        $created += $this->refreshBudgets($this->periods->currentMonthKey());
        $created += $this->refreshSavings();

        return $created;
    }

    public function refreshBudgets(?string $month = null): int
    {
        $month ??= $this->periods->currentMonthKey();
        $monthLabel = $this->periods->monthLabel($month);
        $created = 0;

        foreach ($this->finance->budgetStatuses($month) as $status) {
            $budget = $status['budget'];

            if (in_array($status['status'], ['warning', 'exceeded'], true)) {
                $notification = AppNotification::record(
                    $status['status'] === 'exceeded' ? 'Monthly budget exceeded' : 'Monthly budget warning',
                    $this->budgetBody($budget->name, $status, $monthLabel),
                    'budget',
                    [
                        'url' => route('budgets.index', ['month' => $month]),
                        'budget_id' => $budget->id,
                    ],
                    'budget-'.$budget->id.'-'.$status['status'].'-'.$month,
                );

                if ($notification->wasRecentlyCreated) {
                    $created++;
                }
            }

            foreach ($status['categories'] as $categoryStatus) {
                if (! in_array($categoryStatus['status'], ['warning', 'exceeded'], true)) {
                    continue;
                }

                $categoryBudget = $categoryStatus['model'];
                $categoryName = $categoryStatus['category']?->name ?? 'Category';
                $notification = AppNotification::record(
                    $categoryStatus['status'] === 'exceeded' ? 'Category budget exceeded' : 'Category budget warning',
                    $this->categoryBudgetBody($categoryName, $budget->name, $categoryStatus, $monthLabel),
                    'budget',
                    [
                        'url' => route('budgets.index', ['month' => $month]),
                        'budget_id' => $budget->id,
                        'category_budget_id' => $categoryBudget->id,
                    ],
                    'category-budget-'.$categoryBudget->id.'-'.$categoryStatus['status'].'-'.$month,
                );

                if ($notification->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    public function refreshSavings(): int
    {
        $created = 0;

        foreach ($this->finance->savingsOverview()['items'] as $item) {
            if ($item['status'] !== 'reached') {
                continue;
            }

            $goal = $item['goal'];
            $notification = AppNotification::record(
                'Savings goal reached',
                'You reached your '.$goal->name.' goal of '.Money::format($goal->target_minor).'.',
                'savings',
                [
                    'url' => route('savings.index'),
                    'savings_goal_id' => $goal->id,
                ],
                'savings-'.$goal->id.'-'.$goal->target_minor,
            );

            if ($notification->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function budgetBody(string $name, array $status, string $monthLabel): string
    {
        if ($status['status'] === 'exceeded') {
            return $name.' is '.Money::format(abs($status['remaining'])).' over its '.$monthLabel.' budget.';
        }

        return $name.' has used '.$status['percent'].'% of its '.$monthLabel.' budget.';
    }

    private function categoryBudgetBody(string $category, string $budget, array $status, string $monthLabel): string
    {
        if ($status['status'] === 'exceeded') {
            return $category.' is '.Money::format(abs($status['remaining'])).' over its limit in '.$budget.' for '.$monthLabel.'.';
        }

        return $category.' has used '.$status['percent'].'% of its limit in '.$budget.' for '.$monthLabel.'.';
    }
}
