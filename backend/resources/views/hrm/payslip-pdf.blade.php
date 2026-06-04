<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip {{ $payslip->id }}</title>
    <style>
        @page { margin: 30px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        h2 { font-size: 13px; margin: 18px 0 6px 0; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; padding: 6px 8px; border-bottom: 1px solid #d1d5db; }
        td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .totals td { border-bottom: 2px solid #1f2937; font-weight: bold; padding-top: 10px; }
        .header { display: table; width: 100%; }
        .header > div { display: table-cell; vertical-align: middle; }
        .brand-name { font-size: 14px; font-weight: bold; }
        .grid { display: table; width: 100%; margin-top: 6px; }
        .grid > div { display: table-cell; width: 50%; vertical-align: top; padding-right: 12px; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
        .value { font-size: 11px; margin-top: 2px; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 9px; }
        .badge-paid { background: #ecfdf5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand-name">{{ $tenantName }}</div>
            <div class="muted">Payslip</div>
        </div>
        <div class="right">
            <div class="muted">Reference</div>
            <div class="mono">{{ $payslip->id }}</div>
        </div>
    </div>

    <div class="grid" style="margin-top: 16px;">
        <div>
            <div class="label">Employee</div>
            <div class="value">{{ $employee->first_name }} {{ $employee->last_name }}</div>
            <div class="value muted mono">{{ $employee->employee_id ?? '-' }}</div>
            <div class="value muted">{{ $employee->email ?? '' }}</div>
        </div>
        <div>
            <div class="label">Payroll period</div>
            <div class="value">{{ $period?->name ?? '-' }}</div>
            <div class="value muted mono">
                {{ optional($period?->start_date)->toDateString() ?? '-' }}
                &rarr;
                {{ optional($period?->end_date)->toDateString() ?? '-' }}
            </div>
            <div class="value" style="margin-top: 4px;">
                <span class="badge {{ $period?->status === 'closed' ? 'badge-paid' : 'badge-pending' }}">
                    {{ ucfirst($period?->status ?? 'draft') }}
                </span>
            </div>
        </div>
    </div>

    <h2>Earnings</h2>
    <table>
        <thead>
            <tr><th>Component</th><th class="right">Amount ({{ $currency }})</th></tr>
        </thead>
        <tbody>
            @foreach($earnings as $key => $value)
                <tr>
                    <td>{{ $labels[$key] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $key)) }}</td>
                    <td class="right mono">{{ number_format((float) $value, 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>Gross salary</td>
                <td class="right mono">{{ number_format((float) $payslip->gross_salary, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Deductions</h2>
    <table>
        <thead>
            <tr><th>Component</th><th class="right">Amount ({{ $currency }})</th></tr>
        </thead>
        <tbody>
            @foreach($deductions as $key => $value)
                <tr>
                    <td>{{ $labels[$key] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $key)) }}</td>
                    <td class="right mono">{{ number_format((float) $value, 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>Total deductions</td>
                <td class="right mono">{{ number_format(array_sum(array_map('floatval', $deductions)), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Net pay</h2>
    <table>
        <tbody>
            <tr class="totals">
                <td style="font-size: 13px;">Net salary</td>
                <td class="right mono" style="font-size: 13px;">
                    {{ $currency }} {{ number_format((float) $payslip->net_salary, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top: 24px; font-size: 9px;">
        Generated {{ $generatedAt }} . This document is system-generated and does not require a signature.
    </p>
</body>
</html>
