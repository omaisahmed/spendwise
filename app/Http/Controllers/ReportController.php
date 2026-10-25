<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\PeriodService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ReportController extends Controller
{
    public const TYPES = [
        'monthly_summary' => 'Monthly summary',
        'expense' => 'Expense report',
        'income' => 'Income report',
        'category' => 'Category report',
        'account' => 'Account report',
        'budget' => 'Budget performance',
        'custom' => 'Custom report',
    ];

    public function __construct(
        private ReportService $reports,
        private PeriodService $periods,
    ) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        try {
            $report = $this->reports->data($filters['type'], $filters, 50);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('reports.index')->with('error', $exception->getMessage());
        }

        return view('reports.index', [
            'report' => $report,
            'types' => self::TYPES,
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(),
            'accounts' => Account::query()->orderBy('name')->get(),
            'paymentMethods' => Transaction::PAYMENT_METHODS,
        ]);
    }

    public function csv(Request $request)
    {
        $filters = $this->filters($request);

        try {
            $file = $this->reports->csv($filters['type'], $filters);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('reports.index', $filters)->with('error', $exception->getMessage());
        }

        return response()
            ->download($file['path'], $file['filename'], ['Content-Type' => $file['mime']])
            ->deleteFileAfterSend(true);
    }

    public function pdf(Request $request)
    {
        $filters = $this->filters($request);

        try {
            $file = $this->reports->pdf($filters['type'], $filters);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('reports.index', $filters)->with('error', $exception->getMessage());
        }

        return response()
            ->download($file['path'], $file['filename'], ['Content-Type' => $file['mime']])
            ->deleteFileAfterSend(true);
    }

    private function filters(Request $request): array
    {
        [$start, $end] = $this->periods->monthRange($this->periods->currentMonthKey());
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')],
            'payment_method' => ['nullable', Rule::in(array_keys(Transaction::PAYMENT_METHODS))],
        ]);

        $type = $validated['type'] ?? 'monthly_summary';
        $filters = [
            'type' => $type,
            'from' => $validated['from'] ?? $start->toDateString(),
            'to' => $validated['to'] ?? min($end->subDay(), CarbonImmutable::now())->toDateString(),
            'category_id' => $validated['category_id'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
        ];

        if (in_array($type, ['expense', 'income'], true)) {
            $filters['transaction_type'] = $type;
        }

        return $filters;
    }
}
