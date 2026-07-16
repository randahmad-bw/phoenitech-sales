<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Yearly Report</title>
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
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 10px;
        }
        .logo-text {
            font-size: 20px;
            font-weight: bold;
            color: #1e3b8b;
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
        .stats-grid {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            display: inline-block;
            width: 45%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            border-radius: 6px;
            margin-right: 15px;
            margin-bottom: 15px;
        }
        .stat-val {
            font-size: 14px;
            font-weight: bold;
            color: #1e3b8b;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #1e3b8b;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-text">PhoeniTech Sales</div>
        <div class="subtitle">Yearly Performance Report — {{ $year }}</div>
        <div class="meta">
            Generated: {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    <h3>Yearly Highlights</h3>
    <div class="stats-grid">
        <div class="stat-card">
            <div>Best Sales Month</div>
            <div class="stat-val">Month {{ $report['best_month']['month'] }} ({{ number_format($report['best_month']['value'], 2) }} USD)</div>
        </div>
        <div class="stat-card">
            <div>Top Performing Employee</div>
            <div class="stat-val">{{ $report['best_employee']['name'] ?? 'N/A' }} ({{ number_format($report['best_employee']['total'] ?? 0, 2) }} USD)</div>
        </div>
        <div class="stat-card">
            <div>Top Service Type</div>
            <div class="stat-val">{{ $report['top_service']['name_en'] ?? 'N/A' }} ({{ $report['top_service']['count'] ?? 0 }} contracts)</div>
        </div>
    </div>

    <h3>Monthly Breakdown</h3>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>New Companies</th>
                <th>New Contracts</th>
                <th>Total Value</th>
                <th>Collected Payments</th>
                <th>Remaining Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['monthly_breakdown'] as $m => $stats)
                <tr>
                    <td><strong>Month {{ $m }}</strong></td>
                    <td>{{ $stats['new_companies'] }}</td>
                    <td>{{ $stats['new_contracts'] }}</td>
                    <td>{{ number_format($stats['total_value'], 2) }} USD</td>
                    <td>{{ number_format($stats['collected'], 2) }} USD</td>
                    <td>{{ number_format($stats['remaining'], 2) }} USD</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
