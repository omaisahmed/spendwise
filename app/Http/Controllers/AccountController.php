<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\RecurringTransaction;
use App\Models\SavingsContribution;
use App\Models\Transaction;
use App\Services\FinanceService;
use App\Support\Money;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    public function index()
    {
        $balances = collect($this->finance->accountBalances())->keyBy(fn ($entry) => $entry['account']->id);
        $accounts = Account::query()
            ->withCount(['transactions'])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('accounts.index', [
            'accounts' => $accounts->where('is_archived', false),
            'archived' => $accounts->where('is_archived', true),
            'balances' => $balances,
            'totalBalance' => (int) $balances->sum('balance'),
        ]);
    }

    public function create()
    {
        return view('accounts.form', [
            'account' => null,
            'types' => Account::TYPES,
        ]);
    }

    public function store(AccountRequest $request)
    {
        $account = Account::query()->create($this->payload($request));

        if ($request->boolean('is_default')) {
            $this->makeDefault($account);
        }

        return redirect()->route('accounts.index')->with('success', 'Account created.');
    }

    public function edit(Account $account)
    {
        return view('accounts.form', [
            'account' => $account,
            'types' => Account::TYPES,
        ]);
    }

    public function update(AccountRequest $request, Account $account)
    {
        $wasDefault = $account->is_default;

        $account->update($this->payload($request));

        if ($request->boolean('is_default')) {
            $this->makeDefault($account);
        } elseif ($wasDefault) {
            $replacement = Account::query()
                ->active()
                ->whereKeyNot($account->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->first();

            if ($replacement) {
                $this->makeDefault($replacement);
            } else {
                $account->forceFill(['is_default' => true])->save();
            }
        }

        return redirect()->route('accounts.index')->with('success', 'Account updated.');
    }

    public function destroy(Account $account)
    {
        if ($account->is_default) {
            return back()->with('error', 'Choose another default account before archiving this one.');
        }

        $activeCount = Account::query()->active()->count();
        if ($activeCount <= 1) {
            return back()->with('error', 'Keep at least one active account.');
        }

        $account->forceFill(['is_archived' => true, 'is_default' => false])->save();

        return redirect()->route('accounts.index')->with('success', 'Account archived. Its history is preserved.');
    }

    public function restore(Account $account)
    {
        $account->forceFill(['is_archived' => false])->save();

        return redirect()->route('accounts.index')->with('success', 'Account restored.');
    }

    private function payload(AccountRequest $request): array
    {
        return [
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'opening_balance_minor' => Money::parse($request->input('opening_balance')),
            'color' => $request->input('color'),
            'icon' => $request->input('icon'),
            'is_default' => $request->boolean('is_default'),
        ];
    }

    private function makeDefault(Account $account): void
    {
        Account::query()->whereKeyNot($account->id)->update(['is_default' => false]);
        $account->forceFill(['is_default' => true, 'is_archived' => false])->save();
        AppSetting::current()->forceFill(['default_account_id' => $account->id])->save();
    }
}
