<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>التقرير السنوي</title>
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
            color: #1e3b8b;
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
            margin-left: 15px;
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
        <div class="subtitle">تقرير الأداء السنوي — {{ $year }}</div>
        <div class="meta">
            تاريخ التصدير: {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    <h3>أبرز إنجازات السنة</h3>
    <div class="stats-grid">
        <div class="stat-card">
            <div>أفضل شهر مبيعات</div>
            <div class="stat-val">شهر {{ $report['best_month']['month'] }} ({{ number_format($report['best_month']['value'], 2) }} USD)</div>
        </div>
        <div class="stat-card">
            <div>أعلى موظف أداءً</div>
            <div class="stat-val">{{ $report['best_employee']['name'] ?? '—' }} ({{ number_format($report['best_employee']['total'] ?? 0, 2) }} USD)</div>
        </div>
        <div class="stat-card">
            <div>أعلى نوع خدمة طلبًا</div>
            <div class="stat-val">{{ $report['top_service']['name_ar'] ?? $report['top_service']['name_en'] ?? '—' }} ({{ $report['top_service']['count'] ?? 0 }} عقد)</div>
        </div>
    </div>

    <h3>التفاصيل الشهرية</h3>
    <table>
        <thead>
            <tr>
                <th>الشهر</th>
                <th>شركات جديدة</th>
                <th>عقود جديدة</th>
                <th>إجمالي القيمة</th>
                <th>المبالغ المحصلة</th>
                <th>المبلغ المتبقي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['monthly_breakdown'] as $m => $stats)
                <tr>
                    <td><strong>شهر {{ $m }}</strong></td>
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
