<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AppSetting;
use App\Models\RecurringOccurrence;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RecurringService
{
    public function nextDueDate(RecurringTransaction $schedule, CarbonInterface $from): CarbonImmutable
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $anchorDay = $schedule->anchor_day ?: $schedule->start_date->day;

        $next = match ($schedule->frequency) {
            'daily' => $from->addDay(),
            'weekly' => $from->addWeek(),
            'monthly' => $this->nextMonthly($from, $anchorDay),
            'yearly' => $this->nextYearly($from, $schedule),
            default => throw new RuntimeException('Unsupported recurrence frequency.'),
        };

        return $next;
    }

    private function nextMonthly(CarbonImmutable $from, int $anchorDay): CarbonImmutable
    {
        $base = $from->startOfMonth()->addMonthNoOverflow();
        $day = min($anchorDay, $base->daysInMonth);

        return $base->setDay($day);
    }

    private function nextYearly(CarbonImmutable $from, RecurringTransaction $schedule): CarbonImmutable
    {
        $year = $from->year + 1;
        $month = $schedule->start_date->month;
        $day = min($schedule->start_date->day, CarbonImmutable::create($year, $month, 1)->daysInMonth);

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, config('app.timezone'));
    }

    public function markPaid(RecurringTransaction $schedule, ?CarbonInterface $dueDate = null): Transaction
    {
        return DB::transaction(function () use ($schedule, $dueDate) {
            $locked = RecurringTransaction::query()->lockForUpdate()->findOrFail($schedule->id);

            $due = CarbonImmutable::instance($dueDate ?? $locked->next_due_date)
                ->startOfDay()
                ->setTimezone(config('app.timezone'));

            $existing = RecurringOccurrence::query()
                ->where('recurring_transaction_id', $locked->id)
                ->whereDate('due_date', $due->toDateString())
                ->first();

            if ($existing?->transaction_id) {
                return Transaction::query()->findOrFail($existing->transaction_id);
            }

            if ($locked->status !== RecurringTransaction::STATUS_ACTIVE) {
                throw new RuntimeException('Only active schedules can be processed.');
            }

            $occurrence = RecurringOccurrence::query()->firstOrCreate(
                ['recurring_transaction_id' => $locked->id, 'due_date' => $due->toDateString()],
                ['status' => 'paid', 'processed_at' => now()]
            );

            if (! $occurrence->wasRecentlyCreated && $occurrence->transaction_id) {
                return Transaction::query()->findOrFail($occurrence->transaction_id);
            }

            $transaction = Transaction::query()->create([
                'type' => $locked->type,
                'amount_minor' => $locked->amount_minor,
                'category_id' => $locked->category_id,
                'account_id' => $locked->account_id,
                'to_account_id' => $locked->to_account_id,
                'occurred_at' => $due->setTime((int) now()->format('H'), (int) now()->format('i')),
                'payment_method' => $locked->payment_method,
                'merchant' => $locked->merchant,
                'notes' => $locked->notes,
                'recurring_transaction_id' => $locked->id,
                'transfer_group' => $locked->type === Transaction::TYPE_TRANSFER ? (string) Str::uuid() : null,
            ]);

            $occurrence->forceFill([
                'transaction_id' => $transaction->id,
                'status' => 'paid',
                'processed_at' => now(),
            ])->save();

            $next = $this->nextDueDate($locked, $due);
            $completed = $locked->end_date && $next->greaterThan($locked->end_date);

            $locked->forceFill([
                'next_due_date' => $next,
                'last_processed_at' => now(),
                'status' => $completed ? RecurringTransaction::STATUS_COMPLETED : $locked->status,
            ])->save();

            return $transaction;
        });
    }

    public function markSkipped(RecurringTransaction $schedule, ?CarbonInterface $dueDate = null): void
    {
        DB::transaction(function () use ($schedule, $dueDate) {
            $locked = RecurringTransaction::query()->lockForUpdate()->findOrFail($schedule->id);
            $due = CarbonImmutable::instance($dueDate ?? $locked->next_due_date)->startOfDay();

            $existing = RecurringOccurrence::query()
                ->where('recurring_transaction_id', $locked->id)
                ->whereDate('due_date', $due->toDateString())
                ->exists();

            if ($existing) {
                return;
            }

            if ($locked->status !== RecurringTransaction::STATUS_ACTIVE) {
                throw new RuntimeException('Only active schedules can be processed.');
            }

            $occurrence = RecurringOccurrence::query()->firstOrCreate(
                ['recurring_transaction_id' => $locked->id, 'due_date' => $due->toDateString()],
                ['status' => 'skipped', 'processed_at' => now()]
            );

            if (! $occurrence->wasRecentlyCreated) {
                return;
            }

            $next = $this->nextDueDate($locked, $due);
            $completed = $locked->end_date && $next->greaterThan($locked->end_date);

            $locked->forceFill([
                'next_due_date' => $next,
                'last_processed_at' => now(),
                'status' => $completed ? RecurringTransaction::STATUS_COMPLETED : $locked->status,
            ])->save();
        });
    }

    public function refreshDueNotifications(): int
    {
        $settings = AppSetting::current();
        $created = 0;

        if (! $settings->notifications_enabled) {
            return 0;
        }

        $dueSchedules = RecurringTransaction::query()
            ->due()
            ->where('reminder_enabled', true)
            ->with(['category'])
            ->get();

        foreach ($dueSchedules as $schedule) {
            $key = 'recurring-'.$schedule->id.'-'.$schedule->next_due_date->toDateString();
            $notification = AppNotification::query()->firstOrCreate(
                ['dedupe_key' => $key],
                [
                    'title' => $schedule->type === Transaction::TYPE_INCOME ? 'Expected income due' : 'Upcoming payment due',
                    'body' => ($schedule->merchant ?: $schedule->category?->name ?: 'Recurring transaction')
                        .' is due '.$schedule->next_due_date->format('d M Y').'.',
                    'type' => 'recurring',
                    'data' => [
                        'url' => route('recurring.index'),
                        'recurring_id' => $schedule->id,
                    ],
                ]
            );

            if ($notification->wasRecentlyCreated) {
                $created++;
            }
        }

        $settings->forceFill(['last_due_check_at' => now()])->save();

        return $created;
    }
}
