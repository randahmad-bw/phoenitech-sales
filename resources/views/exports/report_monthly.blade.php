<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>التقرير الشهري</title>
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
            width: 40%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            border-radius: 6px;
            margin-left: 15px;
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
            text-align: right;
            padding: 8px;
            border: 1px solid #e5e7eb;
        }
        td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: right;
        }
        .diff-pos { color: #059669; font-weight: bold; }
        .diff-neg { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-text">PhoeniTech Sales</div>
        <div class="subtitle">التقرير الشهري — {{ $month }}/{{ $year }}</div>
        <div class="meta">
            تاريخ التصدير: {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    <h3>ملخص الأداء</h3>
    <div class="stats-grid">
        <div class="stat-card">
            <div>شركات جديدة</div>
            <div class="stat-val">{{ $report['current']['new_companies'] }}</div>
        </div>
        <div class="stat-card">
            <div>عقود جديدة</div>
            <div class="stat-val">{{ $report['current']['new_contracts'] }}</div>
        </div>
        <div class="stat-card">
            <div>إجمالي القيمة</div>
            <div class="stat-val">{{ number_format($report['current']['total_value'], 2) }} USD</div>
        </div>
        <div class="stat-card">
            <div>إجمالي المحصّل</div>
            <div class="stat-val">{{ number_format($report['current']['collected'], 2) }} USD</div>
        </div>
        <div class="stat-card">
            <div>إجمالي المتبقي</div>
            <div class="stat-val">{{ number_format($report['current']['remaining'], 2) }} USD</div>
        </div>
    </div>

    <h3>مقارنة بالشهر السابق</h3>
    <table>
        <thead>
            <tr>
                <th>مؤشر الأداء</th>
                <th>الشهر السابق</th>
                <th>الشهر الحالي</th>
                <th>الفرق</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>شركات جديدة مضافة</td>
                <td>{{ $report['previous']['new_companies'] }}</td>
                <td>{{ $report['current']['new_companies'] }}</td>
                <td class="{{ $report['comparison']['new_companies_diff'] >= 0 ? 'diff-pos' : 'diff-neg' }}">
                    {{ $report['comparison']['new_companies_diff'] >= 0 ? '+' : '' }}{{ $report['comparison']['new_companies_diff'] }}
                </td>
            </tr>
            <tr>
                <td>عقود جديدة مبرمة</td>
                <td>{{ $report['previous']['new_contracts'] }}</td>
                <td>{{ $report['current']['new_contracts'] }}</td>
                <td class="{{ $report['comparison']['new_contracts_diff'] >= 0 ? 'diff-pos' : 'diff-neg' }}">
                    {{ $report['comparison']['new_contracts_diff'] >= 0 ? '+' : '' }}{{ $report['comparison']['new_contracts_diff'] }}
                </td>
            </tr>
            <tr>
                <td>إجمالي قيمة العقود</td>
                <td>{{ number_format($report['previous']['total_value'], 2) }} USD</td>
                <td>{{ number_format($report['current']['total_value'], 2) }} USD</td>
                <td class="{{ $report['comparison']['total_value_diff'] >= 0 ? 'diff-pos' : 'diff-neg' }}">
                    {{ $report['comparison']['total_value_diff'] >= 0 ? '+' : '' }}{{ number_format($report['comparison']['total_value_diff'], 2) }} USD
                </td>
            </tr>
            <tr>
                <td>المبالغ المحصلة</td>
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
