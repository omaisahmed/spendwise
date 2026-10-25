<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Category;
use App\Support\AppSettings;
use Database\Seeders\SpendWiseDefaultsSeeder;

trait InteractsWithSpendWise
{
    protected function setUpSpendWiseWorkspace(): void
    {
        $this->seed(SpendWiseDefaultsSeeder::class);

        AppSetting::current()->update([
            'display_name' => 'Test User',
            'onboarding_completed' => true,
        ]);

        AppSettings::forget();
        $this->withSession(['spendwise_unlocked' => true]);
    }

    protected function defaultAccount(): Account
    {
        return Account::query()->where('is_default', true)->firstOrFail();
    }

    protected function category(string $type, string $name): Category
    {
        return Category::query()->where('type', $type)->where('name', $name)->firstOrFail();
    }
}
