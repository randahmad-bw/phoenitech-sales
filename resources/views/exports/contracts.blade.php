<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contracts Export</title>
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
            color: #1e3a8a;
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
            background-color: #1e3a8a;
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
        .badge-active { background-color: #d1fae5; color: #065f46; }
        .badge-signed { background-color: #dbeafe; color: #1e40af; }
        .badge-completed { background-color: #f3e8ff; color: #6b21a8; }
        .badge-draft { background-color: #f3f4f6; color: #374151; }
        .badge-suspended { background-color: #fef3c7; color: #92400e; }
        .badge-cancelled { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-text">PhoeniTech Sales</div>
        <div class="subtitle">Contracts Report</div>
        <div class="meta">
            Generated: {{ now()->format('Y-m-d H:i') }}<br>
            Total Records: {{ $contracts->count() }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Contract #</th>
                <th>Company</th>
                <th>Employee</th>
                <th>Service (EN)</th>
                <th>Value</th>
                <th>Start Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contracts as $contract)
                <tr>
                    <td><strong>{{ $contract->contract_number }}</strong></td>
                    <td>{{ $contract->company?->name ?? 'N/A' }}</td>
                    <td>{{ $contract->employee?->name ?? 'N/A' }}</td>
                    <td>{{ $contract->service?->name_en ?? 'N/A' }}</td>
                    <td>{{ number_format($contract->contract_value, 2) }} {{ $contract->currency }}</td>
                    <td>{{ $contract->start_date?->format('Y-m-d') ?? 'N/A' }}</td>
                    <td>
                        <span class="badge badge-{{ $contract->status }}">
                            {{ $contract->status }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
