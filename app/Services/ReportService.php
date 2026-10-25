<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReportService
{
    public function __construct(
        private FinanceService $finance,
        private PeriodService $periods,
    ) {}

    public function range(array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        try {
            $start = CarbonImmutable::parse($from, config('app.timezone'))->startOfDay();
            $end = CarbonImmutable::parse($to, config('app.timezone'))->startOfDay()->addDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Choose a valid report date range.');
        }

        if ($start->greaterThanOrEqualTo($end)) {
            throw new InvalidArgumentException('The start date must be before the end date.');
        }

        return [$start, $end];
    }

    public function query(array $filters): Builder
    {
        [$start, $end] = $this->range($filters);

        return Transaction::query()
            ->with(['category', 'account', 'toAccount', 'tags'])
            ->between($start, $end)
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, $type) => $query->where('transactions.type', $type))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $category) => $query->where('category_id', $category))
            ->when($filters['account_id'] ?? null, fn (Builder $query, $account) => $query->where(fn (Builder $q) => $q->where('account_id', $account)->orWhere('to_account_id', $account)))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, $method) => $query->where('payment_method', $method))
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function data(string $type, array $filters, ?int $transactionLimit = null): array
    {
        [$start, $end] = $this->range($filters);
        $baseQuery = $this->query($filters);
        $transactions = (clone $baseQuery)->when($transactionLimit, fn ($query) => $query->limit($transactionLimit))->get();
        $transactionCount = (clone $baseQuery)->count();
        $totals = $this->finance->totalsForQuery($baseQuery);
        $categories = $this->finance->categoryTotalsForQuery($baseQuery);

        $title = match ($type) {
            'income' => 'Income report',
            'expense' => 'Expense report',
            'category' => 'Category report',
            'account' => 'Account report',
            'budget' => 'Budget performance report',
            'custom' => 'Custom report',
            default => 'Monthly summary',
        };

        return [
            'type' => $type,
            'title' => $title,
            'start' => $start,
            'end' => $end->subMicrosecond(),
            'filters' => $filters,
            'totals' => $totals,
            'categories' => $categories,
            'accounts' => $this->finance->accountBalances(),
            'budgets' => $this->finance->budgetStatuses($this->periods->monthKeyFor($start)),
            'transactions' => $transactions,
            'transaction_count' => $transactionCount,
            'generated_at' => now(),
            'recurring_commitment' => $this->finance->recurringMonthlyCommitment(),
            'recurring_count' => RecurringTransaction::query()->active()->count(),
        ];
    }

    public function csv(string $type, array $filters): array
    {
        $data = $this->data($type, $filters);
        $path = $this->exportPath('csv');

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to create the export file.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($this->csvSections($data) as $section) {
            foreach ($section['rows'] as $row) {
                fputcsv($handle, array_map(fn ($value) => $this->csvCell($value), $row), ',', '"', '');
            }
            fputcsv($handle, [], ',', '"', '');
        }

        fclose($handle);

        return [
            'path' => $path,
            'filename' => $this->filename($type, 'csv'),
            'mime' => 'text/csv',
        ];
    }

    public function pdf(string $type, array $filters): array
    {
        $data = $this->data($type, $filters);
        $path = $this->exportPath('pdf');

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $data,
            'currency' => \App\Support\AppSettings::currency(),
        ])->setPaper('a4');

        File::put($path, $pdf->output());

        return [
            'path' => $path,
            'filename' => $this->filename($type, 'pdf'),
            'mime' => 'application/pdf',
        ];
    }

    private function csvSections(array $data): array
    {
        $currency = \App\Support\AppSettings::currency();
        $sections = [];

        $sections[] = ['rows' => [
            ['SpendWise '.$data['title']],
            ['Generated', $data['generated_at']->format('Y-m-d H:i')],
            ['Period', $data['start']->format('Y-m-d').' to '.$data['end']->format('Y-m-d')],
            ['Currency', $currency],
            [],
            ['Income', Money::toDecimal($data['totals']['income'])],
            ['Expenses', Money::toDecimal($data['totals']['expenses'])],
            ['Balance', Money::toDecimal($data['totals']['balance'])],
        ]];

        if (in_array($data['type'], ['category', 'monthly_summary', 'custom', 'expense'], true)) {
            $rows = [['Category', 'Entries', 'Total ('.$currency.')', 'Essential']];
            foreach ($data['categories'] as $category) {
                $rows[] = [$category['name'], $category['entries'], Money::toDecimal($category['total']), $category['is_essential'] ? 'Yes' : 'No'];
            }
            $sections[] = ['rows' => $rows];
        }

        if (in_array($data['type'], ['account', 'custom'], true)) {
            $rows = [['Account', 'Type', 'Opening balance', 'Current balance']];
            foreach ($data['accounts'] as $entry) {
                $rows[] = [
                    $entry['account']->name,
                    Account::TYPES[$entry['account']->type] ?? $entry['account']->type,
                    Money::toDecimal($entry['account']->opening_balance_minor),
                    Money::toDecimal($entry['balance']),
                ];
            }
            $sections[] = ['rows' => $rows];
        }

        if (in_array($data['type'], ['budget', 'custom', 'monthly_summary'], true) && $data['budgets']) {
            $rows = [['Budget', 'Month', 'Budgeted', 'Spent', 'Remaining', 'Used %', 'Status']];
            foreach ($data['budgets'] as $budget) {
                $rows[] = [
                    $budget['budget']->name,
                    $budget['budget']->month,
                    Money::toDecimal($budget['budget']->amount_minor),
                    Money::toDecimal($budget['spent']),
                    Money::toDecimal($budget['remaining']),
                    $budget['percent'].'%',
                    $budget['status'],
                ];
            }
            $sections[] = ['rows' => $rows];
        }

        if ($data['transactions']->isNotEmpty()) {
            $rows = [[
                'Date', 'Time', 'Type', 'Category', 'From account', 'To account', 'Merchant / payee',
                'Payment method', 'Notes', 'Tags', 'Amount ('.$currency.')', 'Refund',
            ]];
            foreach ($data['transactions'] as $transaction) {
                $rows[] = [
                    $transaction->occurred_at->format('Y-m-d'),
                    $transaction->occurred_at->format('H:i'),
                    ucfirst($transaction->type),
                    $transaction->category?->name,
                    $transaction->account?->name,
                    $transaction->toAccount?->name,
                    $transaction->merchant,
                    $transaction->paymentMethodLabel(),
                    $transaction->notes,
                    $transaction->tags->pluck('name')->implode(', '),
                    Money::toDecimal($transaction->is_refund ? -$transaction->amount_minor : $transaction->amount_minor),
                    $transaction->is_refund ? 'Yes' : 'No',
                ];
            }
            $sections[] = ['rows' => $rows];
        }

        return $sections;
    }

    private function csvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    private function exportPath(string $extension): string
    {
        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);

        return $directory.DIRECTORY_SEPARATOR.Str::uuid().'.'.$extension;
    }

    private function filename(string $type, string $extension): string
    {
        return 'spendwise-'.Str::slug($type).'-'.now()->format('Ymd-His').'.'.$extension;
    }
}
