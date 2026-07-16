<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments Export</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #10b981;
            padding-bottom: 10px;
        }
        .logo-text {
            font-size: 20px;
            font-weight: bold;
            color: #065f46;
        }
        .subtitle {
            font-size: 12px;
            color: #6b7280;
        }
        .meta {
            text-align: right;
            font-size: 10px;
            color: #6b7280;
            margin-top: -30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #065f46;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 8px;
            border: 1px solid #e5e7eb;
        }
        td {
            padding: 8px;
            border: 1px solid #e5e7eb;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-paid { background-color: #d1fae5; color: #065f46; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-text">PhoeniTech Sales</div>
        <div class="subtitle">Payments List Report</div>
        <div class="meta">
            Generated: {{ now()->format('Y-m-d H:i') }}<br>
            Total Records: {{ $payments->count() }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Contract #</th>
                <th>Company</th>
                <th>Amount</th>
                <th>Payment Date</th>
                <th>Method</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->id }}</td>
                    <td><strong>{{ $payment->contract?->contract_number ?? 'N/A' }}</strong></td>
                    <td>{{ $payment->contract?->company?->name ?? 'N/A' }}</td>
                    <td>{{ number_format($payment->amount, 2) }} {{ $payment->contract?->currency ?? 'USD' }}</td>
                    <td>{{ $payment->payment_date?->format('Y-m-d') ?? 'N/A' }}</td>
                    <td>{{ str_replace('_', ' ', $payment->method) }}</td>
                    <td>
                        <span class="badge badge-{{ $payment->status }}">
                            {{ $payment->status }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
