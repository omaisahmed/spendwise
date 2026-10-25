<?php

namespace App\Http\Controllers;

use App\Http\Requests\OnboardingRequest;
use App\Models\Account;
use App\Models\AppSetting;
use App\Support\AppSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function show()
    {
        if (AppSettings::get()->onboarding_completed) {
            return redirect()->route('dashboard');
        }

        return view('onboarding', [
            'currencies' => Money::SYMBOLS,
            'accountTypes' => ['cash' => 'Cash', 'bank' => 'Bank account', 'wallet' => 'Mobile wallet', 'savings' => 'Savings account'],
        ]);
    }

    public function store(OnboardingRequest $request)
    {
        DB::transaction(function () use ($request) {
            $settings = AppSetting::current();
            $account = Account::query()->where('is_default', true)->first()
                ?? Account::query()->orderBy('id')->first()
                ?? new Account();

            $account->fill([
                'name' => $request->input('account_name'),
                'type' => $request->input('account_type'),
                'icon' => Account::typeIcon($request->input('account_type')),
                'color' => '#635BFF',
                'opening_balance_minor' => Money::parse($request->input('starting_balance')),
                'is_default' => true,
                'is_archived' => false,
                'sort_order' => 0,
            ])->save();

            Account::query()->whereKeyNot($account->id)->update(['is_default' => false]);

            $settings->update([
                'display_name' => $request->input('display_name'),
                'currency' => $request->input('currency'),
                'month_start_day' => $request->integer('month_start_day'),
                'monthly_income_minor' => $request->filled('monthly_income') ? Money::parse($request->input('monthly_income')) : null,
                'theme' => $request->input('theme'),
                'default_account_id' => $account->id,
                'onboarding_completed' => true,
                'onboarding_skipped' => false,
            ]);
        });

        AppSettings::forget();
        $request->session()->put('spendwise_unlocked', true);

        return redirect()->route('dashboard')->with('success', 'Your SpendWise workspace is ready.');
    }

    public function skip()
    {
        AppSettings::get()->forceFill(['onboarding_skipped' => true])->save();
        AppSettings::forget();

        return redirect()->route('dashboard')->with('success', 'You can personalise SpendWise anytime in Settings.');
    }
}
