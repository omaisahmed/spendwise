<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\AppSettings;
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

    public function test_date_format_setting_formats_full_dates(): void
    {
        $date = now()->setDate(2026, 10, 26);

        AppSetting::current()->update(['date_format' => 'Y-m-d']);
        AppSettings::forget();

        $this->assertSame('2026-10-26', AppSettings::formatDate($date));
        $this->assertSame('2026-10-26, '.$date->format('H:i'), AppSettings::formatDateTime($date));

        $this->put(route('settings.update'), [
            'currency' => 'PKR',
            'month_start_day' => 1,
            'budget_warning_threshold' => 80,
            'theme' => 'system',
            'date_format' => 'M d, Y',
        ])->assertRedirect(route('settings.index'));

        $this->assertSame('Oct 26, 2026', AppSettings::formatDate($date));
    }

    public function test_security_forms_submit_put_requests(): void
    {
        $this->put(route('settings.security'), [
            'action' => 'enable',
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ])->assertRedirect(route('settings.index'));

        $response = $this->get(route('settings.index'))->assertOk();

        $response->assertSee('action="'.route('settings.security').'"', false);
        $this->assertSame(3, substr_count($response->getContent(), 'name="_method" value="PUT"'));
    }

    public function test_theme_can_be_applied_immediately_and_is_persisted(): void
    {
        $this->patchJson(route('settings.theme'), ['theme' => 'dark'])
            ->assertOk()
            ->assertJson(['theme' => 'dark']);

        $this->assertSame('dark', AppSetting::current()->theme);

        $this->patchJson(route('settings.theme'), ['theme' => 'system'])
            ->assertOk()
            ->assertJson(['theme' => 'system']);

        $this->assertSame('system', AppSetting::current()->theme);

        $this->patchJson(route('settings.theme'), ['theme' => 'neon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme');
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
