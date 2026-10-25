<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AppSetting;
use App\Support\AppSettings;
use Database\Seeders\SpendWiseDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnboardingLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpendWiseDefaultsSeeder::class);
        AppSettings::forget();
    }

    public function test_onboarding_creates_the_workspace_then_app_lock_protects_it(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('onboarding.show'));

        $this->post(route('onboarding.store'), [
            'display_name' => 'Ayesha',
            'starting_balance' => '1500.00',
            'monthly_income' => '85000.00',
            'currency' => 'PKR',
            'month_start_day' => 25,
            'theme' => 'dark',
            'account_name' => 'Main wallet',
            'account_type' => 'wallet',
        ])->assertRedirect(route('dashboard'));

        $settings = AppSetting::current();
        $account = Account::query()->where('name', 'Main wallet')->firstOrFail();

        $this->assertTrue($settings->onboarding_completed);
        $this->assertSame('Ayesha', $settings->display_name);
        $this->assertSame('PKR', $settings->currency);
        $this->assertSame(25, $settings->month_start_day);
        $this->assertSame(8500000, $settings->monthly_income_minor);
        $this->assertSame(150000, $account->opening_balance_minor);
        $this->assertSame('wallet', $account->type);
        $this->assertTrue($account->is_default);
        $this->assertSame($account->id, $settings->default_account_id);

        $this->get(route('onboarding.show'))->assertRedirect(route('dashboard'));

        $this->put(route('settings.security'), [
            'action' => 'enable',
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ])->assertRedirect(route('settings.index'));

        $settings->refresh();

        $this->assertTrue($settings->app_lock_enabled);
        $this->assertTrue(Hash::check('1234', $settings->pin_hash));

        $this->post(route('lock.lock'))->assertRedirect(route('lock.show'));
        $this->get(route('dashboard'))->assertRedirect(route('lock.show'));

        $this->from(route('lock.show'))->post(route('lock.unlock'), [
            'pin' => '0000',
        ])->assertSessionHas('error');

        $this->assertFalse(session('spendwise_unlocked'));

        $this->post(route('lock.unlock'), [
            'pin' => '1234',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(session('spendwise_unlocked'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_onboarding_can_be_skipped_without_creating_default_data(): void
    {
        $this->post(route('onboarding.skip'))->assertRedirect(route('dashboard'));

        $settings = AppSetting::current();

        $this->assertTrue($settings->onboarding_skipped);
        $this->assertFalse($settings->onboarding_completed);
        $this->assertDatabaseCount('accounts', 1);
        $this->get(route('dashboard'))->assertOk();
    }
}
