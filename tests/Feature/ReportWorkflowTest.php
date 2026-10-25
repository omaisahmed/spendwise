<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
    }

    public function test_report_totals_respect_the_selected_transaction_type_and_category(): void
    {
        $account = $this->defaultAccount();
        $food = $this->category('expense', 'Food & Dining');
        $salary = $this->category('income', 'Salary');

        Transaction::query()->create([
            'type' => 'income',
            'amount_minor' => 100000,
            'category_id' => $salary->id,
            'account_id' => $account->id,
            'occurred_at' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        Transaction::query()->create([
            'type' => 'expense',
            'amount_minor' => 30000,
            'category_id' => $food->id,
            'account_id' => $account->id,
            'occurred_at' => now(),
            'payment_method' => 'cash',
        ]);

        $filters = [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ];

        $reports = app(ReportService::class);
        $expenseReport = $reports->data('expense', [...$filters, 'transaction_type' => 'expense']);

        $this->assertSame(0, $expenseReport['totals']['income']);
        $this->assertSame(30000, $expenseReport['totals']['expenses']);
        $this->assertSame(-30000, $expenseReport['totals']['balance']);
        $this->assertSame(1, $expenseReport['transaction_count']);
        $this->assertSame($food->id, $expenseReport['categories'][0]['category_id']);

        $categoryReport = $reports->data('custom', [...$filters, 'category_id' => $food->id]);

        $this->assertSame(0, $categoryReport['totals']['income']);
        $this->assertSame(30000, $categoryReport['totals']['expenses']);
        $this->assertSame(1, $categoryReport['transaction_count']);

        $summary = $reports->data('monthly_summary', $filters);

        $this->assertSame(100000, $summary['totals']['income']);
        $this->assertSame(30000, $summary['totals']['expenses']);
        $this->assertSame(70000, $summary['totals']['balance']);
        $this->assertSame(2, $summary['transaction_count']);
    }

    public function test_report_ranges_reject_reversed_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The start date must be before the end date.');

        app(ReportService::class)->data('custom', [
            'from' => '2026-10-20',
            'to' => '2026-10-10',
        ]);
    }

    public function test_report_pages_accept_type_and_date_filters(): void
    {
        $this->get(route('reports.index', [
            'type' => 'expense',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();

        $this->get(route('reports.csv', [
            'type' => 'custom',
            'from' => '2026-10-10',
            'to' => '2026-10-20',
        ]))->assertOk()->assertDownload();
    }
}
