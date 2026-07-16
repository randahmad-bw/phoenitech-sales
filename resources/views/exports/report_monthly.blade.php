<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Report</title>
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
            border-bottom: 2px solid #8b5cf6;
            padding-bottom: 10px;
        }
        .logo-text {
            font-size: 20px;
            font-weight: bold;
            color: #4c1d95;
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
            width: 30%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            border-radius: 6px;
            margin-right: 15px;
            margin-bottom: 15px;
        }
        .stat-val {
            font-size: 16px;
            font-weight: bold;
            color: #4c1d95;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #4c1d95;
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
        .diff-pos { color: #059669; font-weight: bold; }
        .diff-neg { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-text">PhoeniTech Sales</div>
        <div class="subtitle">Monthly Report — {{ $month }}/{{ $year }}</div>
        <div class="meta">
            Generated: {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    <h3>Performance Summary</h3>
    <div class="stats-grid">
        <div class="stat-card">
            <div>New Companies</div>
            <div class="stat-val">{{ $report['current']['new_companies'] }}</div>
        </div>
        <div class="stat-card">
            <div>New Contracts</div>
            <div class="stat-val">{{ $report['current']['new_contracts'] }}</div>
        </div>
        <div class="stat-card">
            <div>Total Value</div>
            <div class="stat-val">{{ number_format($report['current']['total_value'], 2) }} USD</div>
        </div>
        <div class="stat-card">
            <div>Total Collected</div>
            <div class="stat-val">{{ number_format($report['current']['collected'], 2) }} USD</div>
        </div>
        <div class="stat-card">
            <div>Total Remaining</div>
            <div class="stat-val">{{ number_format($report['current']['remaining'], 2) }} USD</div>
        </div>
    </div>

    <h3>Comparison with Previous Month</h3>
    <table>
        <thead>
            <tr>
                <th>KPI Metric</th>
                <th>Previous Month</th>
                <th>Current Month</th>
                <th>Difference</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>New Companies Signed</td>
                <td>{{ $report['previous']['new_companies'] }}</td>
                <td>{{ $report['current']['new_companies'] }}</td>
                <td class="{{ $report['comparison']['new_companies_diff'] >= 0 ? 'diff-pos' : 'diff-neg' }}">
                    {{ $report['comparison']['new_companies_diff'] >= 0 ? '+' : '' }}{{ $report['comparison']['new_companies_diff'] }}
                </td>
            </tr>
            <tr>
                <td>New Contracts Generated</td>
                <td>{{ $report['previous']['new_contracts'] }}</td>
                <td>{{ $report['current']['new_contracts'] }}</td>
                <td class="{{ $report['comparison']['new_contracts_diff'] >= 0 ? 'diff-pos' : 'diff-neg' }}">
                    {{ $report['comparison']['new_contracts_diff'] >= 0 ? '+' : '' }}{{ $report['comparison']['new_contracts_diff'] }}
                </td>
            </tr>
            <tr>
                <td>Total Contracts Value</td>
                <td>{{ number_format($report['previous']['total_value'], 2) }} USD</td>
                <td>{{ number_format($report['current']['total_value'], 2) }} USD</td>
                <td class="{{ $report['comparison']['total_value_diff'] >= 0 ? 'diff-pos' : 'diff-neg' }}">
                    {{ $report['comparison']['total_value_diff'] >= 0 ? '+' : '' }}{{ number_format($report['comparison']['total_value_diff'], 2) }} USD
                </td>
            </tr>
            <tr>
                <td>Collected Payments</td>
                <td>{{ number_format($report['previous']['collected'], 2) }} USD</td>
                <td>{{ number_format($report['current']['collected'], 2) }} USD</td>
                <td class="{{ $report['comparison']['collected_diff'] >= 0 ? 'diff-pos' : 'diff-neg' }}">
                    {{ $report['comparison']['collected_diff'] >= 0 ? '+' : '' }}{{ number_format($report['comparison']['collected_diff'], 2) }} USD
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
