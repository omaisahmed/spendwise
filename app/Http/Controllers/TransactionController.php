<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\FinanceService;
use App\Services\PeriodService;
use App\Support\AppSettings;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function __construct(
        private FinanceService $finance,
        private PeriodService $periods,
    ) {}

    public function index(Request $request)
    {
        $period = $request->query('period', 'month');
        $month = $this->periods->normalizeMonth($request->query('month'));
        $settings = AppSettings::get();
        [$start, $end] = $this->periods->monthRange($month);

        $query = Transaction::query()
            ->with(['category', 'account', 'toAccount', 'tags'])
            ->when($period === 'month', fn ($builder) => $builder->between($start, $end))
            ->when($request->filled('type'), fn ($builder, $type) => $builder->where('type', $type))
            ->when($request->filled('category_id'), fn ($builder, $category) => $builder->where('category_id', $category))
            ->when($request->filled('account_id'), fn ($builder, $account) => $builder->where(fn ($q) => $q->where('account_id', $account)->orWhere('to_account_id', $account)))
            ->when($request->filled('payment_method'), fn ($builder, $method) => $builder->where('payment_method', $method))
            ->when($request->filled('search'), function ($builder, $search) {
                $builder->where(function ($q) use ($search) {
                    $q->where('merchant', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%')
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('tags', fn ($tag) => $tag->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $transactions = $query->paginate(25)->withQueryString();
        $totals = $this->finance->rangeTotals(
            $period === 'month' ? $start : CarbonImmutable::create(1970, 1, 1, 0, 0, 0, config('app.timezone')),
            $period === 'month' ? $end : CarbonImmutable::create(2100, 1, 1, 0, 0, 0, config('app.timezone'))
        );

        return view('transactions.index', [
            'transactions' => $transactions,
            'totals' => $totals,
            'month' => $month,
            'monthLabel' => $period === 'all' ? 'All transactions' : $this->periods->monthLabel($month),
            'previousMonth' => $this->periods->shiftMonth($month, -1),
            'nextMonth' => $this->periods->shiftMonth($month, 1),
            'canGoNext' => $month < $this->periods->currentMonthKey(),
            'period' => $period,
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'accounts' => Account::query()->active()->orderBy('name')->get(),
            'paymentMethods' => Transaction::PAYMENT_METHODS,
            'settings' => $settings,
        ]);
    }

    public function create(Request $request)
    {
        return $this->form(null, $request->query('type', 'expense'));
    }

    public function createTransfer()
    {
        return $this->form(null, 'transfer');
    }

    public function store(TransactionRequest $request)
    {
        $transaction = DB::transaction(function () use ($request) {
            $transaction = Transaction::query()->create($this->payload($request));
            $this->syncTags($transaction, $request->input('tags'));

            return $transaction;
        });

        $month = $this->periods->monthKeyFor($transaction->occurred_at);

        return redirect()
            ->route('transactions.index', ['month' => $month])
            ->with('success', ucfirst($transaction->type).' saved successfully.');
    }

    public function edit(Transaction $transaction)
    {
        return $this->form($transaction, $transaction->type);
    }

    public function update(TransactionRequest $request, Transaction $transaction)
    {
        DB::transaction(function () use ($request, $transaction) {
            $payload = $this->payload($request, $transaction);

            if ($payload['type'] === Transaction::TYPE_TRANSFER && ! $transaction->transfer_group) {
                $payload['transfer_group'] = (string) Str::uuid();
            }

            if ($payload['type'] !== Transaction::TYPE_TRANSFER) {
                $payload['transfer_group'] = null;
            }

            $transaction->update($payload);
            $this->syncTags($transaction, $request->input('tags'));
        });

        return redirect()
            ->route('transactions.index', ['month' => $this->periods->monthKeyFor($transaction->occurred_at)])
            ->with('success', 'Transaction updated.');
    }

    public function destroy(Transaction $transaction)
    {
        $month = $this->periods->monthKeyFor($transaction->occurred_at);
        $type = $transaction->type;
        $transaction->delete();

        return redirect()
            ->route('transactions.index', ['month' => $month])
            ->with('success', ucfirst($type).' deleted.');
    }

    private function form(?Transaction $transaction, string $type)
    {
        $accounts = Account::query()->active()->orderByDesc('is_default')->orderBy('name')->get();
        $settings = AppSettings::get();

        return view('transactions.form', [
            'transaction' => $transaction,
            'type' => in_array($type, [Transaction::TYPE_EXPENSE, Transaction::TYPE_INCOME, Transaction::TYPE_TRANSFER], true) ? $type : Transaction::TYPE_EXPENSE,
            'categories' => Category::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'accounts' => $accounts,
            'paymentMethods' => Transaction::PAYMENT_METHODS,
            'defaultAccountId' => $transaction?->account_id ?? $settings->default_account_id ?? $accounts->first()?->id,
            'defaultDate' => $transaction?->occurred_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'defaultTime' => $transaction?->occurred_at?->format('H:i') ?? now()->format('H:i'),
        ]);
    }

    private function payload(TransactionRequest $request, ?Transaction $transaction = null): array
    {
        $type = $request->input('type');
        $isTransfer = $type === Transaction::TYPE_TRANSFER;
        $date = $request->input('occurred_at');
        $time = $request->input('occurred_time') ?: now()->format('H:i');
        $occurredAt = CarbonImmutable::parse($date.' '.$time, config('app.timezone'));

        return [
            'type' => $type,
            'amount_minor' => Money::parse($request->input('amount')),
            'category_id' => $isTransfer ? null : $request->integer('category_id'),
            'account_id' => $request->integer('account_id'),
            'to_account_id' => $isTransfer ? $request->integer('to_account_id') : null,
            'occurred_at' => $occurredAt,
            'payment_method' => $request->input('payment_method'),
            'merchant' => $request->filled('merchant') ? trim($request->input('merchant')) : null,
            'notes' => $request->filled('notes') ? trim($request->input('notes')) : null,
            'is_refund' => $type === Transaction::TYPE_EXPENSE && $request->boolean('is_refund'),
            'transfer_group' => $isTransfer ? ($transaction?->transfer_group ?: (string) Str::uuid()) : null,
        ];
    }

    private function syncTags(Transaction $transaction, ?string $tags): void
    {
        $names = collect(explode(',', (string) $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique(fn ($tag) => strtolower($tag))
            ->take(10)
            ->values();

        $ids = $names->map(function (string $name) {
            return Tag::query()->firstOrCreate(['name' => Str::limit($name, 40, '')])->id;
        });

        $transaction->tags()->sync($ids);
    }
}
