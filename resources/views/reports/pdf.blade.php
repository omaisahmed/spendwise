<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 30px 30px 42px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            line-height: 1.45;
            color: #171B34;
            background: #ffffff;
        }
        .header { border-bottom: 2px solid #635BFF; padding-bottom: 14px; }
        .brand-row { width: 100%; }
        .brand { color: #635BFF; font-size: 18px; font-weight: bold; letter-spacing: -0.5px; }
        .report-title { color: #171B34; font-size: 17px; font-weight: bold; text-align: right; }
        .muted { color: #6B7185; }
        .meta { margin-top: 7px; font-size: 8px; color: #6B7185; }
        .meta-right { text-align: right; }
        .section { margin-top: 18px; }
        .section-title { margin: 0 0 7px; color: #171B34; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.7px; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-left: -5px; }
        .summary td { width: 33.33%; padding: 10px; background: #F6F7FB; border-radius: 6px; vertical-align: top; }
        .summary .label { color: #6B7185; font-size: 7px; text-transform: uppercase; letter-spacing: 0.6px; }
        .summary .value { margin-top: 3px; font-size: 13px; font-weight: bold; }
        .income { color: #16A878; }
        .expense { color: #F05B6B; }
        .brand-text { color: #635BFF; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th { background: #171B34; color: #ffffff; font-size: 7px; text-align: left; padding: 6px 7px; text-transform: uppercase; letter-spacing: 0.4px; }
        table.data td { border-bottom: 1px solid #E7E9F0; padding: 6px 7px; vertical-align: top; }
        table.data tr { page-break-inside: avoid; }
        table.data tr:nth-child(even) td { background: #FAFBFD; }
        .right { text-align: right; }
        .center { text-align: center; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 8px; background: #EEEDFF; color: #635BFF; font-size: 7px; font-weight: bold; }
        .empty { padding: 14px; background: #F6F7FB; border-radius: 6px; color: #6B7185; text-align: center; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; border-top: 1px solid #E7E9F0; padding-top: 6px; color: #8A8FA3; font-size: 7px; }
        .footer-right { text-align: right; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    @php
        $showsCategories = in_array($report['type'], ['category', 'monthly_summary', 'custom', 'expense'], true);
        $showsAccounts = in_array($report['type'], ['account', 'custom'], true);
        $showsBudgets = in_array($report['type'], ['budget', 'custom', 'monthly_summary'], true) && count($report['budgets']) > 0;
    @endphp

    <div class="header">
        <table class="brand-row">
            <tr>
                <td><span class="brand">SpendWise</span></td>
                <td class="report-title">{{ $report['title'] }}</td>
            </tr>
        </table>
        <table class="meta" style="width:100%;">
            <tr>
                <td>{{ \App\Support\AppSettings::formatDate($report['start']) }} — {{ \App\Support\AppSettings::formatDate($report['end']) }} · {{ $currency }}</td>
                <td class="meta-right">Generated {{ \App\Support\AppSettings::formatDateTime($report['generated_at']) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="summary">
            <tr>
                <td><div class="label">Income</div><div class="value income">{{ \App\Support\Money::format($report['totals']['income']) }}</div></td>
                <td><div class="label">Expenses</div><div class="value expense">{{ \App\Support\Money::format($report['totals']['expenses']) }}</div></td>
                <td><div class="label">Net balance</div><div class="value brand-text">{{ \App\Support\Money::format($report['totals']['balance']) }}</div></td>
            </tr>
        </table>
    </div>

    @if($showsCategories)
        <div class="section">
            <h2 class="section-title">Category breakdown</h2>
            @if(count($report['categories']))
                <table class="data">
                    <thead><tr><th>Category</th><th class="center">Entries</th><th class="right">Amount</th><th class="right">Share</th><th>Essential</th></tr></thead>
                    <tbody>
                        @foreach($report['categories'] as $category)
                            <tr>
                                <td>{{ $category['name'] }}</td>
                                <td class="center">{{ $category['entries'] }}</td>
                                <td class="right">{{ \App\Support\Money::format($category['total']) }}</td>
                                <td class="right">{{ \App\Support\Money::percent($category['total'], max(1, $report['totals']['expenses'])) }}%</td>
                                <td>{{ $category['is_essential'] ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty">No category activity in this period.</div>
            @endif
        </div>
    @endif

    @if($showsAccounts)
        <div class="section">
            <h2 class="section-title">Accounts</h2>
            <table class="data">
                <thead><tr><th>Account</th><th>Type</th><th class="right">Opening balance</th><th class="right">Current balance</th></tr></thead>
                <tbody>
                    @foreach($report['accounts'] as $entry)
                        <tr>
                            <td>{{ $entry['account']->name }}</td>
                            <td>{{ ucfirst($entry['account']->type) }}</td>
                            <td class="right">{{ \App\Support\Money::format($entry['account']->opening_balance_minor) }}</td>
                            <td class="right">{{ \App\Support\Money::format($entry['balance']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($showsBudgets)
        <div class="section">
            <h2 class="section-title">Budget performance</h2>
            <table class="data">
                <thead><tr><th>Budget</th><th>Month</th><th class="right">Budgeted</th><th class="right">Spent</th><th class="right">Remaining</th><th class="right">Used</th></tr></thead>
                <tbody>
                    @foreach($report['budgets'] as $budget)
                        <tr>
                            <td>{{ $budget['budget']->name }}</td>
                            <td>{{ $budget['budget']->month }}</td>
                            <td class="right">{{ \App\Support\Money::format($budget['budget']->amount_minor) }}</td>
                            <td class="right">{{ \App\Support\Money::format($budget['spent']) }}</td>
                            <td class="right">{{ \App\Support\Money::format($budget['remaining']) }}</td>
                            <td class="right">{{ $budget['percent'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="section">
        <h2 class="section-title">Transactions ({{ $report['transaction_count'] }})</h2>
        @if($report['transactions']->isNotEmpty())
            <table class="data">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category / payee</th>
                        <th>Account</th>
                        <th>Method</th>
                        <th>Notes</th>
                        <th class="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['transactions'] as $transaction)
                        <tr>
                            <td>{{ \App\Support\AppSettings::formatDate($transaction->occurred_at) }}<br><span class="muted">{{ $transaction->occurred_at->format('H:i') }}</span></td>
                            <td>{{ ucfirst($transaction->type) }}</td>
                            <td>
                                {{ $transaction->category?->name ?? '—' }}
                                @if($transaction->merchant)<br><span class="muted">{{ $transaction->merchant }}</span>@endif
                            </td>
                            <td>
                                {{ $transaction->account?->name ?? '—' }}
                                @if($transaction->type === 'transfer' && $transaction->toAccount)<br><span class="muted">to {{ $transaction->toAccount->name }}</span>@endif
                            </td>
                            <td>{{ $transaction->paymentMethodLabel() }}</td>
                            <td>{{ \Illuminate\Support\Str::limit((string) $transaction->notes, 60) ?: '—' }}</td>
                            <td class="right {{ $transaction->type === 'income' ? 'income' : ($transaction->type === 'expense' ? 'expense' : '') }}">
                                {{ \App\Support\Money::format($transaction->is_refund ? -$transaction->amount_minor : $transaction->amount_minor) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">No matching transactions.</div>
        @endif
    </div>

    <div class="footer">
        <table style="width:100%;">
            <tr>
                <td>SpendWise · {{ $report['title'] }}</td>
                <td class="footer-right">Page <span class="page-number"></span></td>
            </tr>
        </table>
    </div>
</body>
</html>
