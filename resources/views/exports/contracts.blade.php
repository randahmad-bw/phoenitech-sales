<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>تصدير العقود</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
            direction: rtl;
            text-align: right;
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
            margin-top: 4px;
        }
        .meta {
            float: left;
            text-align: left;
            font-size: 10px;
            color: #6b7280;
            margin-top: -35px;
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
            text-align: right;
            padding: 8px;
            border: 1px solid #e5e7eb;
        }
        td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: right;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-text">PhoeniTech Sales</div>
        <div class="subtitle">تقرير العقود المسجلة</div>
        <div class="meta">
            تاريخ التصدير: {{ now()->format('Y-m-d H:i') }}<br>
            عدد العقود: {{ $contracts->count() }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>رقم العقد</th>
                <th>اسم الشركة</th>
                <th>تاريخ البداية</th>
                <th>تاريخ الانتهاء</th>
                <th>المبلغ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contracts as $contract)
                <tr>
                    <td><strong>{{ $contract->contract_number }}</strong></td>
                    <td>{{ $contract->company?->name ?? '—' }}</td>
                    <td>{{ $contract->start_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $contract->end_date?->format('Y-m-d') ?? '—' }}</td>
                    <td><strong>{{ number_format($contract->contract_value, 2) }} {{ $contract->currency }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
