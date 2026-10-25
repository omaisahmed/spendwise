<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecurringRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\RecurringService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use RuntimeException;

class RecurringTransactionController extends Controller
{
    public function __construct(private RecurringService $recurring) {}

    public function index()
    {
        $schedules = RecurringTransaction::query()
            ->with(['category', 'account', 'toAccount'])
            ->withCount('occurrences')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderBy('next_due_date')
            ->get();

        return view('recurring.index', [
            'due' => $schedules->where('status', RecurringTransaction::STATUS_ACTIVE)->where('next_due_date', '<=', now()->startOfDay()),
            'upcoming' => $schedules->where('status', RecurringTransaction::STATUS_ACTIVE)->where('next_due_date', '>', now()->startOfDay()),
            'inactive' => $schedules->whereNotIn('status', [RecurringTransaction::STATUS_ACTIVE]),
            'frequencies' => RecurringTransaction::FREQUENCIES,
        ]);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function store(RecurringRequest $request)
    {
        $schedule = RecurringTransaction::query()->create($this->payload($request));

        return redirect()->route('recurring.index')->with('success', 'Recurring schedule created.');
    }

    public function edit(RecurringTransaction $recurring)
    {
        return $this->form($recurring);
    }

    public function update(RecurringRequest $request, RecurringTransaction $recurring)
    {
        $payload = $this->payload($request);
        $occurrences = $recurring->occurrences()->count();
        $startChanged = $recurring->start_date->toDateString() !== $payload['start_date']->toDateString();

        if ($occurrences === 0) {
            $payload['next_due_date'] = $payload['start_date'];
        } else {
            $lastDue = $recurring->occurrences()->max('due_date');
            $preview = $recurring->replicate()->forceFill($payload);
            $nextDue = $lastDue
                ? $this->recurring->nextDueDate($preview, CarbonImmutable::parse($lastDue, config('app.timezone')))
                : ($startChanged ? $payload['start_date'] : $recurring->next_due_date);

            $payload['next_due_date'] = $nextDue->lessThan($payload['start_date'])
                ? $payload['start_date']
                : $nextDue;
        }

        if ($payload['end_date'] && CarbonImmutable::parse($payload['end_date'])->lessThan($payload['next_due_date'])) {
            $payload['status'] = RecurringTransaction::STATUS_COMPLETED;
        }

        $recurring->update($payload);

        return redirect()->route('recurring.index')->with('success', 'Recurring schedule updated.');
    }

    public function destroy(RecurringTransaction $recurring)
    {
        $recurring->forceFill(['status' => RecurringTransaction::STATUS_CANCELLED])->save();

        return redirect()->route('recurring.index')->with('success', 'Recurring schedule cancelled. Existing entries are preserved.');
    }

    public function markPaid(RecurringTransaction $recurring)
    {
        try {
            $transaction = $this->recurring->markPaid($recurring);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('recurring.index')->with('success', 'Payment recorded as '.Money::format($transaction->amount_minor).'.');
    }

    public function markSkipped(RecurringTransaction $recurring)
    {
        try {
            $this->recurring->markSkipped($recurring);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('recurring.index')->with('success', 'Occurrence skipped.');
    }

    public function pause(RecurringTransaction $recurring)
    {
        if ($recurring->status !== RecurringTransaction::STATUS_ACTIVE) {
            return back()->with('error', 'Only active schedules can be paused.');
        }

        $recurring->forceFill(['status' => RecurringTransaction::STATUS_PAUSED])->save();

        return redirect()->route('recurring.index')->with('success', 'Recurring schedule paused.');
    }

    public function resume(RecurringTransaction $recurring)
    {
        if ($recurring->status !== RecurringTransaction::STATUS_PAUSED) {
            return back()->with('error', 'Only paused schedules can be resumed.');
        }

        $recurring->forceFill(['status' => RecurringTransaction::STATUS_ACTIVE])->save();

        return redirect()->route('recurring.index')->with('success', 'Recurring schedule resumed.');
    }

    private function form(?RecurringTransaction $recurring)
    {
        $accounts = Account::query()->active()->orderByDesc('is_default')->orderBy('name')->get();

        return view('recurring.form', [
            'recurring' => $recurring,
            'categories' => Category::query()->active()->orderBy('sort_order')->get(),
            'accounts' => $accounts,
            'paymentMethods' => Transaction::PAYMENT_METHODS,
            'defaultAccountId' => $recurring?->account_id ?? $accounts->first()?->id,
            'startDate' => $recurring?->start_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ]);
    }

    private function payload(RecurringRequest $request): array
    {
        $type = $request->input('type');
        $isTransfer = $type === Transaction::TYPE_TRANSFER;
        $start = CarbonImmutable::parse($request->input('start_date'), config('app.timezone'));

        return [
            'type' => $type,
            'amount_minor' => Money::parse($request->input('amount')),
            'category_id' => $isTransfer ? null : $request->integer('category_id'),
            'account_id' => $request->integer('account_id'),
            'to_account_id' => $isTransfer ? $request->integer('to_account_id') : null,
            'frequency' => $request->input('frequency'),
            'anchor_day' => $request->integer('anchor_day') ?: $start->day,
            'start_date' => $start,
            'end_date' => $request->input('end_date') ?: null,
            'next_due_date' => $start,
            'merchant' => $request->input('merchant'),
            'notes' => $request->input('notes'),
            'payment_method' => $request->input('payment_method'),
            'reminder_enabled' => $request->boolean('reminder_enabled'),
        ];
    }
}
