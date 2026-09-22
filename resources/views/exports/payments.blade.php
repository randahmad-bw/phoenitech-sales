<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>تصدير الدفعات</title>
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
            background-color: #065f46;
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
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-paid { background-color: #d1fae5; color: #065f46; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-text">PhoeniTech Sales</div>
        <div class="subtitle">تقرير سجل الدفعات</div>
        <div class="meta">
            تاريخ التصدير: {{ now()->format('Y-m-d H:i') }}<br>
            عدد الدفعات: {{ $payments->count() }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>المعرف</th>
                <th>رقم العقد</th>
                <th>الشركة</th>
                <th>المبلغ</th>
                <th>تاريخ الدفع</th>
                <th>طريقة الدفع</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->id }}</td>
                    <td><strong>{{ $payment->contract?->contract_number ?? '—' }}</strong></td>
                    <td>{{ $payment->contract?->company?->name ?? '—' }}</td>
                    <td>{{ number_format($payment->amount, 2) }} {{ $payment->contract?->currency ?? 'USD' }}</td>
                    <td>{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ str_replace('_', ' ', $payment->method) }}</td>
                    <td>
                        <span class="badge badge-{{ $payment->status }}">
                            {{ $payment->status === 'paid' ? 'مدفوع' : 'معلّق' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
