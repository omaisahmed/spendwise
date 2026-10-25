<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
    }

    public function test_preferences_are_validated_and_saved(): void
    {
        $this->put(route('settings.update'), [
            'display_name' => 'Bilal',
            'currency' => 'USD',
            'month_start_day' => 25,
            'monthly_income' => '12345.00',
            'budget_warning_threshold' => 90,
            'theme' => 'dark',
            'notifications_enabled' => '1',
            'privacy_mode' => '1',
            'date_format' => 'Y-m-d',
        ])->assertRedirect(route('settings.index'));

        $settings = AppSetting::current();

        $this->assertSame('Bilal', $settings->display_name);
        $this->assertSame('USD', $settings->currency);
        $this->assertSame(25, $settings->month_start_day);
        $this->assertSame(1234500, $settings->monthly_income_minor);
        $this->assertSame(90, $settings->budget_warning_threshold);
        $this->assertSame('dark', $settings->theme);
        $this->assertTrue($settings->notifications_enabled);
        $this->assertTrue($settings->privacy_mode);
        $this->assertSame('Y-m-d', $settings->date_format);

        $this->from(route('settings.index'))->put(route('settings.update'), [
            'display_name' => 'Bilal',
            'currency' => 'INVALID',
            'month_start_day' => 32,
            'budget_warning_threshold' => 120,
            'theme' => 'neon',
            'date_format' => 'd/m/Y',
        ])->assertSessionHasErrors(['currency', 'month_start_day', 'budget_warning_threshold', 'theme']);
    }

    public function test_pin_changes_require_the_current_pin_and_can_be_disabled(): void
    {
        $this->put(route('settings.security'), [
            'action' => 'enable',
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ])->assertRedirect(route('settings.index'));

        $settings = AppSetting::current();
        $this->assertTrue($settings->app_lock_enabled);
        $this->assertTrue(Hash::check('1234', $settings->pin_hash));
        $this->assertArrayNotHasKey('pin_hash', $settings->toArray());

        $this->from(route('settings.index'))->put(route('settings.security'), [
            'action' => 'change',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])->assertSessionHasErrors('current_pin');

        $this->from(route('settings.index'))->put(route('settings.security'), [
            'action' => 'change',
            'current_pin' => '9999',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])->assertSessionHasErrors('current_pin');

        $this->put(route('settings.security'), [
            'action' => 'change',
            'current_pin' => '1234',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])->assertRedirect(route('settings.index'));

        $this->assertTrue(Hash::check('5678', $settings->fresh()->pin_hash));

        $this->put(route('settings.security'), [
            'action' => 'disable',
            'current_pin' => '5678',
        ])->assertRedirect(route('settings.index'));

        $settings->refresh();

        $this->assertFalse($settings->app_lock_enabled);
        $this->assertNull($settings->pin_hash);
    }
}
