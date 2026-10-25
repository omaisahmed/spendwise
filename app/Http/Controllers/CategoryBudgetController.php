<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryBudgetRequest;
use App\Models\Budget;
use App\Models\CategoryBudget;
use App\Support\Money;

class CategoryBudgetController extends Controller
{
    public function store(CategoryBudgetRequest $request, Budget $budget)
    {
        $amount = Money::parse($request->input('amount'));
        $allocated = (int) $budget->categoryBudgets()->sum('amount_minor');

        if ($allocated + $amount > $budget->amount_minor) {
            return back()->withErrors([
                'amount' => 'This allocation exceeds the overall budget by '.Money::format($allocated + $amount - $budget->amount_minor).'.',
            ])->withInput();
        }

        $budget->categoryBudgets()->create([
            'category_id' => $request->integer('category_id'),
            'amount_minor' => $amount,
            'warning_threshold' => $request->input('warning_threshold'),
        ]);

        return redirect()->route('budgets.index', ['month' => $budget->month])->with('success', 'Category limit added.');
    }

    public function update(CategoryBudgetRequest $request, Budget $budget, CategoryBudget $categoryBudget)
    {
        abort_unless($categoryBudget->budget_id === $budget->id, 404);

        $amount = Money::parse($request->input('amount'));
        $allocated = (int) $budget->categoryBudgets()->whereKeyNot($categoryBudget->id)->sum('amount_minor');

        if ($allocated + $amount > $budget->amount_minor) {
            return back()->withErrors([
                'amount' => 'This allocation exceeds the overall budget by '.Money::format($allocated + $amount - $budget->amount_minor).'.',
            ])->withInput();
        }

        $categoryBudget->update([
            'category_id' => $request->integer('category_id'),
            'amount_minor' => $amount,
            'warning_threshold' => $request->exists('warning_threshold')
                ? $request->input('warning_threshold')
                : $categoryBudget->warning_threshold,
        ]);

        return redirect()->route('budgets.index', ['month' => $budget->month])->with('success', 'Category limit updated.');
    }

    public function destroy(Budget $budget, CategoryBudget $categoryBudget)
    {
        abort_unless($categoryBudget->budget_id === $budget->id, 404);
        $categoryBudget->delete();

        return redirect()->route('budgets.index', ['month' => $budget->month])->with('success', 'Category limit removed.');
    }
}
