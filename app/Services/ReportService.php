<?php

namespace App\Services;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\AppSettings;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportService
{
    public function __construct(
        private FinanceService $finance,
        private PeriodService $periods,
    ) {}

    public function range(array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        try {
            $start = CarbonImmutable::parse($from, config('app.timezone'))->startOfDay();
            $end = CarbonImmutable::parse($to, config('app.timezone'))->startOfDay()->addDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Choose a valid report date range.');
        }

        if ($start->greaterThanOrEqualTo($end)) {
            throw new InvalidArgumentException('The start date must be before the end date.');
        }

        return [$start, $end];
    }

    public function query(array $filters): Builder
    {
        [$start, $end] = $this->range($filters);

        return Transaction::query()
            ->with(['category', 'account', 'toAccount', 'tags'])
            ->between($start, $end)
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, $type) => $query->where('transactions.type', $type))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $category) => $query->where('category_id', $category))
            ->when($filters['account_id'] ?? null, fn (Builder $query, $account) => $query->where(fn (Builder $q) => $q->where('account_id', $account)->orWhere('to_account_id', $account)))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, $method) => $query->where('payment_method', $method))
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function data(string $type, array $filters, ?int $transactionLimit = null): array
    {
        [$start, $end] = $this->range($filters);
        $baseQuery = $this->query($filters);
        $transactions = (clone $baseQuery)->when($transactionLimit, fn ($query) => $query->limit($transactionLimit))->get();
        $transactionCount = (clone $baseQuery)->count();
        $totals = $this->finance->totalsForQuery($baseQuery);
        $categories = $this->finance->categoryTotalsForQuery($baseQuery);

        $title = match ($type) {
            'income' => 'Income report',
            'expense' => 'Expense report',
            'category' => 'Category report',
            'account' => 'Account report',
            'budget' => 'Budget performance report',
            'custom' => 'Custom report',
            default => 'Monthly summary',
        };

        return [
            'type' => $type,
            'title' => $title,
            'start' => $start,
            'end' => $end->subMicrosecond(),
            'filters' => $filters,
            'totals' => $totals,
            'categories' => $categories,
            'accounts' => $this->finance->accountBalances(),
            'budgets' => $this->finance->budgetStatuses($this->periods->monthKeyFor($start)),
            'transactions' => $transactions,
            'transaction_count' => $transactionCount,
            'generated_at' => now(),
            'recurring_commitment' => $this->finance->recurringMonthlyCommitment(),
            'recurring_count' => RecurringTransaction::query()->active()->count(),
        ];
    }

    public function csv(string $type, array $filters): array
    {
        $data = $this->data($type, $filters);
        $path = $this->exportPath('csv');

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to create the export file.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($this->csvSections($data) as $section) {
            foreach ($section['rows'] as $row) {
                fputcsv($handle, array_map(fn ($value) => $this->csvCell($value), $row), ',', '"', '');
            }
            fputcsv($handle, [], ',', '"', '');
        }

        fclose($handle);

        return [
            'path' => $path,
            'filename' => $this->filename($type, 'csv'),
            'mime' => 'text/csv',
        ];
    }

    public function pdf(string $type, array $filters): array
    {
        $data = $this->data($type, $filters);
        $path = $this->exportPath('pdf');

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $data,
            'currency' => AppSettings::currency(),
        ])->setPaper('a4');

        File::put($path, $pdf->output());

        return [
            'path' => $path,
            'filename' => $this->filename($type, 'pdf'),
            'mime' => 'application/pdf',
        ];
    }

    public function excel(string $type, array $filters): array
    {
        $data = $this->data($type, $filters);
        $currency = AppSettings::currency();
        $path = $this->exportPath('xlsx');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SpendWise')
            ->setTitle($data['title'])
            ->setDescription('SpendWise '.$data['title'].' from '.$data['start']->format('Y-m-d').' to '.$data['end']->format('Y-m-d'));

        $this->writeExcelSummary($spreadsheet->getActiveSheet()->setTitle('Summary'), $data, $currency);
        $this->writeExcelTransactions($spreadsheet->createSheet()->setTitle('Transactions'), $data, $currency);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return [
            'path' => $path,
            'filename' => $this->filename($type, 'xlsx'),
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    private function writeExcelSummary(Worksheet $sheet, array $data, string $currency): void
    {
        $sheet->mergeCells('A1:E1');
        $this->excelText($sheet, 'A1', 'SpendWise '.$data['title']);
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF171B34']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEEEDFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $meta = [
            ['Generated', $data['generated_at']->format('Y-m-d H:i')],
            ['Period', $data['start']->format('Y-m-d').' to '.$data['end']->format('Y-m-d')],
            ['Currency', $currency],
        ];

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $this->excelText($sheet, 'A'.$row, $label);
            $this->excelText($sheet, 'B'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->getColor()->setARGB('FF6B7185');
            $row++;
        }

        $row++;
        $this->excelSectionTitle($sheet, $row, 'Summary', 5);
        $this->excelHeaderRow($sheet, $row, ['Metric', 'Amount ('.$currency.')']);
        $totals = [
            ['Income', $data['totals']['income'], 'FF16A878'],
            ['Expenses', $data['totals']['expenses'], 'FFF05B6B'],
            ['Balance', $data['totals']['balance'], 'FF635BFF'],
        ];
        foreach ($totals as [$label, $amount, $color]) {
            $this->excelText($sheet, 'A'.$row, $label);
            $this->excelAmount($sheet, 'B'.$row, $amount);
            $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true)->getColor()->setARGB($color);
            $row++;
        }

        $showsCategories = in_array($data['type'], ['category', 'monthly_summary', 'custom', 'expense'], true);
        if ($showsCategories && count($data['categories'])) {
            $row++;
            $this->excelSectionTitle($sheet, $row, 'Category breakdown', 5);
            $this->excelHeaderRow($sheet, $row, ['Category', 'Entries', 'Total ('.$currency.')', 'Share', 'Essential']);
            foreach ($data['categories'] as $category) {
                $this->excelText($sheet, 'A'.$row, $category['name']);
                $this->excelInteger($sheet, 'B'.$row, $category['entries']);
                $this->excelAmount($sheet, 'C'.$row, $category['total']);
                $this->excelPercent($sheet, 'D'.$row, Money::percent($category['total'], max(1, $data['totals']['expenses'])));
                $this->excelText($sheet, 'E'.$row, $category['is_essential'] ? 'Yes' : 'No');
                $row++;
            }
        }

        $showsAccounts = in_array($data['type'], ['account', 'custom'], true);
        if ($showsAccounts) {
            $row++;
            $this->excelSectionTitle($sheet, $row, 'Accounts', 4);
            $this->excelHeaderRow($sheet, $row, ['Account', 'Type', 'Opening balance', 'Current balance']);
            foreach ($data['accounts'] as $entry) {
                $this->excelText($sheet, 'A'.$row, $entry['account']->name);
                $this->excelText($sheet, 'B'.$row, Account::TYPES[$entry['account']->type] ?? ucfirst($entry['account']->type));
                $this->excelAmount($sheet, 'C'.$row, $entry['account']->opening_balance_minor);
                $this->excelAmount($sheet, 'D'.$row, $entry['balance']);
                $row++;
            }
        }

        $showsBudgets = in_array($data['type'], ['budget', 'custom', 'monthly_summary'], true) && count($data['budgets']);
        if ($showsBudgets) {
            $row++;
            $this->excelSectionTitle($sheet, $row, 'Budget performance', 7);
            $this->excelHeaderRow($sheet, $row, ['Budget', 'Month', 'Budgeted', 'Spent', 'Remaining', 'Used', 'Status']);
            foreach ($data['budgets'] as $budget) {
                $this->excelText($sheet, 'A'.$row, $budget['budget']->name);
                $this->excelText($sheet, 'B'.$row, $budget['budget']->month);
                $this->excelAmount($sheet, 'C'.$row, $budget['budget']->amount_minor);
                $this->excelAmount($sheet, 'D'.$row, $budget['spent']);
                $this->excelAmount($sheet, 'E'.$row, $budget['remaining']);
                $this->excelPercent($sheet, 'F'.$row, $budget['percent']);
                $this->excelText($sheet, 'G'.$row, ucfirst($budget['status']));
                $row++;
            }
        }

        foreach ([
            'A' => 30,
            'B' => 20,
            'C' => 18,
            'D' => 16,
            'E' => 16,
            'F' => 14,
            'G' => 14,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A7');
        $sheet->setSelectedCell('A1');
    }

    private function writeExcelTransactions(Worksheet $sheet, array $data, string $currency): void
    {
        $headers = [
            'Date', 'Time', 'Type', 'Category', 'From account', 'To account', 'Merchant / payee',
            'Payment method', 'Notes', 'Tags', 'Amount ('.$currency.')', 'Refund',
        ];

        $row = 1;
        $this->excelHeaderRow($sheet, $row, $headers);

        foreach ($data['transactions'] as $transaction) {
            $this->excelText($sheet, 'A'.$row, $transaction->occurred_at->format('Y-m-d'));
            $this->excelText($sheet, 'B'.$row, $transaction->occurred_at->format('H:i'));
            $this->excelText($sheet, 'C'.$row, ucfirst($transaction->type));
            $this->excelText($sheet, 'D'.$row, $transaction->category?->name);
            $this->excelText($sheet, 'E'.$row, $transaction->account?->name);
            $this->excelText($sheet, 'F'.$row, $transaction->toAccount?->name);
            $this->excelText($sheet, 'G'.$row, $transaction->merchant);
            $this->excelText($sheet, 'H'.$row, $transaction->paymentMethodLabel());
            $this->excelText($sheet, 'I'.$row, $transaction->notes);
            $this->excelText($sheet, 'J'.$row, $transaction->tags->pluck('name')->implode(', '));
            $this->excelAmount($sheet, 'K'.$row, $transaction->is_refund ? -$transaction->amount_minor : $transaction->amount_minor);
            $this->excelText($sheet, 'L'.$row, $transaction->is_refund ? 'Yes' : 'No');

            if ($row % 2 === 0) {
                $sheet->getStyle('A'.$row.':L'.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFAFBFD');
            }

            $row++;
        }

        foreach ([
            'A' => 12,
            'B' => 9,
            'C' => 11,
            'D' => 20,
            'E' => 18,
            'F' => 18,
            'G' => 26,
            'H' => 17,
            'I' => 34,
            'J' => 22,
            'K' => 16,
            'L' => 9,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L'.max(1, $row - 1));
        $sheet->setSelectedCell('A2');
    }

    private function excelSectionTitle(Worksheet $sheet, int &$row, string $title, int $columns): void
    {
        $last = Coordinate::stringFromColumnIndex($columns);
        $sheet->mergeCells('A'.$row.':'.$last.$row);
        $this->excelText($sheet, 'A'.$row, $title);
        $sheet->getStyle('A'.$row.':'.$last.$row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF171B34']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;
    }

    private function excelHeaderRow(Worksheet $sheet, int &$row, array $headers): void
    {
        $last = Coordinate::stringFromColumnIndex(count($headers));
        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $this->excelText($sheet, $column.$row, $header);
        }

        $sheet->getStyle('A'.$row.':'.$last.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF635BFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF4E46E5']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;
    }

    private function excelText(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
    }

    private function excelAmount(Worksheet $sheet, string $coordinate, int $minor): void
    {
        $sheet->setCellValue($coordinate, (float) Money::toDecimal($minor));
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    private function excelInteger(Worksheet $sheet, string $coordinate, int $value): void
    {
        $sheet->setCellValue($coordinate, $value);
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
    }

    private function excelPercent(Worksheet $sheet, string $coordinate, int $value): void
    {
        $sheet->setCellValue($coordinate, $value);
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('0"%"');
    }

    private function csvSections(array $data): array
    {
        $currency = AppSettings::currency();
        $sections = [];

        $sections[] = ['rows' => [
            ['SpendWise '.$data['title']],
            ['Generated', $data['generated_at']->format('Y-m-d H:i')],
            ['Period', $data['start']->format('Y-m-d').' to '.$data['end']->format('Y-m-d')],
            ['Currency', $currency],
            [],
            ['Income', Money::toDecimal($data['totals']['income'])],
            ['Expenses', Money::toDecimal($data['totals']['expenses'])],
            ['Balance', Money::toDecimal($data['totals']['balance'])],
        ]];

        if (in_array($data['type'], ['category', 'monthly_summary', 'custom', 'expense'], true)) {
            $rows = [['Category', 'Entries', 'Total ('.$currency.')', 'Essential']];
            foreach ($data['categories'] as $category) {
                $rows[] = [$category['name'], $category['entries'], Money::toDecimal($category['total']), $category['is_essential'] ? 'Yes' : 'No'];
            }
            $sections[] = ['rows' => $rows];
        }

        if (in_array($data['type'], ['account', 'custom'], true)) {
            $rows = [['Account', 'Type', 'Opening balance', 'Current balance']];
            foreach ($data['accounts'] as $entry) {
                $rows[] = [
                    $entry['account']->name,
                    Account::TYPES[$entry['account']->type] ?? $entry['account']->type,
                    Money::toDecimal($entry['account']->opening_balance_minor),
                    Money::toDecimal($entry['balance']),
                ];
            }
            $sections[] = ['rows' => $rows];
        }

        if (in_array($data['type'], ['budget', 'custom', 'monthly_summary'], true) && $data['budgets']) {
            $rows = [['Budget', 'Month', 'Budgeted', 'Spent', 'Remaining', 'Used %', 'Status']];
            foreach ($data['budgets'] as $budget) {
                $rows[] = [
                    $budget['budget']->name,
                    $budget['budget']->month,
                    Money::toDecimal($budget['budget']->amount_minor),
                    Money::toDecimal($budget['spent']),
                    Money::toDecimal($budget['remaining']),
                    $budget['percent'].'%',
                    $budget['status'],
                ];
            }
            $sections[] = ['rows' => $rows];
        }

        if ($data['transactions']->isNotEmpty()) {
            $rows = [[
                'Date', 'Time', 'Type', 'Category', 'From account', 'To account', 'Merchant / payee',
                'Payment method', 'Notes', 'Tags', 'Amount ('.$currency.')', 'Refund',
            ]];
            foreach ($data['transactions'] as $transaction) {
                $rows[] = [
                    $transaction->occurred_at->format('Y-m-d'),
                    $transaction->occurred_at->format('H:i'),
                    ucfirst($transaction->type),
                    $transaction->category?->name,
                    $transaction->account?->name,
                    $transaction->toAccount?->name,
                    $transaction->merchant,
                    $transaction->paymentMethodLabel(),
                    $transaction->notes,
                    $transaction->tags->pluck('name')->implode(', '),
                    Money::toDecimal($transaction->is_refund ? -$transaction->amount_minor : $transaction->amount_minor),
                    $transaction->is_refund ? 'Yes' : 'No',
                ];
            }
            $sections[] = ['rows' => $rows];
        }

        return $sections;
    }

    private function csvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    private function exportPath(string $extension): string
    {
        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);

        return $directory.DIRECTORY_SEPARATOR.Str::uuid().'.'.$extension;
    }

    private function filename(string $type, string $extension): string
    {
        return 'spendwise-'.Str::slug($type).'-'.now()->format('Ymd-His').'.'.$extension;
    }
}
