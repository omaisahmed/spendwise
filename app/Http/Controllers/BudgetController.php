<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\FinanceService;
use App\Services\PeriodService;
use App\Support\Money;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(
        private FinanceService $finance,
        private PeriodService $periods,
    ) {}

    public function index(Request $request)
    {
        $month = $this->periods->normalizeMonth($request->query('month'));
        $budgetStatus = $this->finance->primaryBudgetForMonth($month);
        $categorySpend = collect($this->finance->categoryTotals(...$this->periods->monthRange($month)))->keyBy('category_id');

        return view('budgets.index', [
            'month' => $month,
            'monthLabel' => $this->periods->monthLabel($month),
            'rangeLabel' => $this->periods->rangeLabel($month),
            'previousMonth' => $this->periods->shiftMonth($month, -1),
            'nextMonth' => $this->periods->shiftMonth($month, 1),
            'canGoNext' => $month < $this->periods->currentMonthKey(),
            'budgetStatus' => $budgetStatus,
            'categorySpend' => $categorySpend,
            'expenseCategories' => Category::query()->active()->ofType(Category::TYPE_EXPENSE)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $month = $this->periods->normalizeMonth($request->query('month'));

        if ($this->finance->primaryBudgetForMonth($month)) {
            return redirect()->route('budgets.edit', Budget::query()->forMonth($month)->first())->with('info', 'A budget already exists for this month.');
        }

        return view('budgets.form', [
            'budget' => null,
            'month' => $month,
            'monthLabel' => $this->periods->monthLabel($month),
        ]);
    }

    public function store(BudgetRequest $request)
    {
        if (Budget::query()->forMonth($request->input('month'))->exists()) {
            return back()->withErrors(['month' => 'A budget already exists for this month.'])->withInput();
        }

        $budget = Budget::query()->create([
            'name' => $request->input('name'),
            'month' => $request->input('month'),
            'amount_minor' => Money::parse($request->input('amount')),
            'warning_threshold' => $request->integer('warning_threshold'),
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('budgets.index', ['month' => $budget->month])->with('success', 'Monthly budget created.');
    }

    public function edit(Budget $budget)
    {
        return view('budgets.form', [
            'budget' => $budget,
            'month' => $budget->month,
            'monthLabel' => $this->periods->monthLabel($budget->month),
        ]);
    }

    public function update(BudgetRequest $request, Budget $budget)
    {
        $amount = Money::parse($request->input('amount'));
        $allocated = (int) $budget->categoryBudgets()->sum('amount_minor');

        if ($amount < $allocated) {
            return back()->withErrors([
                'amount' => 'The overall budget cannot be lower than the '.Money::format($allocated).' already allocated to categories.',
            ])->withInput();
        }

        $budget->update([
            'name' => $request->input('name'),
            'amount_minor' => $amount,
            'warning_threshold' => $request->integer('warning_threshold'),
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('budgets.index', ['month' => $budget->month])->with('success', 'Budget updated.');
    }

    public function destroy(Budget $budget)
    {
        $month = $budget->month;
        $budget->delete();

        return redirect()->route('budgets.index', ['month' => $month])->with('success', 'Budget removed. Transactions are untouched.');
    }
}
