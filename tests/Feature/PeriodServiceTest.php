<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\PeriodService;
use App\Support\AppSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class PeriodServiceTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();

        AppSetting::current()->update(['month_start_day' => 25]);
        AppSettings::forget();
    }

    public function test_custom_month_start_assigns_dates_to_the_correct_period(): void
    {
        $periods = app(PeriodService::class);

        $this->assertSame('2026-02', $periods->monthKeyFor(CarbonImmutable::parse('2026-03-24 12:00:00')));
        $this->assertSame('2026-03', $periods->monthKeyFor(CarbonImmutable::parse('2026-03-25 00:00:00')));
        $this->assertSame('2026-03', $periods->monthKeyFor(CarbonImmutable::parse('2026-04-24 23:59:59')));
        $this->assertSame('2026-04', $periods->monthKeyFor(CarbonImmutable::parse('2026-04-25 00:00:00')));
    }

    public function test_custom_month_ranges_and_labels_use_the_configured_start_day(): void
    {
        $periods = app(PeriodService::class);

        [$start, $end] = $periods->monthRange('2026-02');

        $this->assertSame('2026-02-25 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-25 00:00:00', $end->format('Y-m-d H:i:s'));
        $this->assertSame('25 Feb - 24 Mar 2026', $periods->rangeLabel('2026-02'));
    }

    public function test_start_day_is_clamped_for_short_months(): void
    {
        AppSetting::current()->update(['month_start_day' => 31]);
        AppSettings::forget();

        $periods = app(PeriodService::class);
        [$start, $end] = $periods->monthRange('2026-02');

        $this->assertSame('2026-02-28 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-31 00:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_invalid_month_keys_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PeriodService::class)->assertMonth('2026-13');
    }
}
