<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $type = in_array($request->query('type'), [Category::TYPE_EXPENSE, Category::TYPE_INCOME], true)
            ? $request->query('type')
            : Category::TYPE_EXPENSE;

        $categories = Category::query()
            ->where('type', $type)
            ->withCount(['transactions', 'categoryBudgets'])
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('categories.index', [
            'categories' => $categories,
            'type' => $type,
        ]);
    }

    public function create(Request $request)
    {
        return view('categories.form', [
            'category' => null,
            'type' => $request->query('type') === Category::TYPE_INCOME ? Category::TYPE_INCOME : Category::TYPE_EXPENSE,
            'icons' => Category::iconChoices(),
            'colors' => Category::colorChoices(),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        Category::query()->create([
            ...$request->validated(),
            'is_essential' => $request->input('type') === Category::TYPE_EXPENSE && $request->boolean('is_essential'),
        ]);

        return redirect()->route('categories.index', ['type' => $request->input('type')])->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('categories.form', [
            'category' => $category,
            'type' => $category->type,
            'icons' => Category::iconChoices(),
            'colors' => Category::colorChoices(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $typeChanged = $request->input('type') !== $category->type;
        $isReferenced = Transaction::query()->where('category_id', $category->id)->exists()
            || RecurringTransaction::query()->where('category_id', $category->id)->exists()
            || $category->categoryBudgets()->exists();

        if ($typeChanged && $isReferenced) {
            return back()->withErrors([
                'type' => 'This category is used by transactions, schedules, or budgets, so its type cannot change.',
            ])->withInput();
        }

        $type = $request->input('type');

        $category->update([
            ...$request->validated(),
            'type' => $type,
            'is_essential' => $type === Category::TYPE_EXPENSE && $request->boolean('is_essential'),
        ]);

        return redirect()->route('categories.index', ['type' => $type])->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $inUse = Transaction::query()->where('category_id', $category->id)->exists()
            || RecurringTransaction::query()->where('category_id', $category->id)->exists()
            || $category->categoryBudgets()->exists();

        if ($inUse) {
            $category->forceFill(['archived_at' => now()])->save();
            $message = 'Category archived. Existing records keep their category.';
        } else {
            $category->delete();
            $message = 'Category deleted.';
        }

        return redirect()->route('categories.index', ['type' => $category->type])->with('success', $message);
    }

    public function restore(Category $category)
    {
        $category->forceFill(['archived_at' => null])->save();

        return redirect()->route('categories.index', ['type' => $category->type])->with('success', 'Category restored.');
    }
}
