import React from 'react';
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Cell,
} from 'recharts';
import { Card } from '../ui/Card';
import { useUiStore } from '@/store/uiStore';

const MONTH_NAMES_AR: Record<number, string> = {
  1: 'يناير', 2: 'فبراير', 3: 'مارس', 4: 'أبريل',
  5: 'مايو',  6: 'يونيو',  7: 'يوليو', 8: 'أغسطس',
  9: 'سبتمبر', 10: 'أكتوبر', 11: 'نوفمبر', 12: 'ديسمبر',
};

function formatMillions(value: number): string {
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
  if (value >= 1_000)     return `${(value / 1_000).toFixed(0)}K`;
  return String(value);
}

interface MonthlySalesProps {
  data: Record<number, number>;
  title: string;
}

export const MonthlySalesChart: React.FC<MonthlySalesProps> = ({ data, title }) => {
  const { theme } = useUiStore();
  const isDark = theme === 'dark';
  const currentMonth = new Date().getMonth() + 1;

  // Normalize data keys to numbers (JSON returns string keys)
  const normalizedData: Record<number, number> = {};
  if (data) {
    Object.entries(data).forEach(([k, v]) => {
      normalizedData[Number(k)] = Number(v) || 0;
    });
  }

  const chartData = Array.from({ length: 12 }, (_, i) => {
    const month = i + 1;
    return {
      name:  MONTH_NAMES_AR[month],
      month,
      total: normalizedData[month] ?? 0,
    };
  });

  const gridStroke    = isDark ? '#1d2b2c' : '#e2e8f0';
  const axisStroke    = isDark ? '#64868a' : '#94a3b8';
  const tooltipBg     = isDark ? '#111819' : '#ffffff';
  const tooltipBorder = isDark ? '#1d2b2c' : '#e2e8f0';
  const tooltipText   = isDark ? '#f0fdfa' : '#0f172a';
  const barColor      = '#0d9488';
  const barCurrent    = '#0f766e';

  const CustomTooltip = ({ active, payload, label }: any) => {
    if (active && payload && payload.length) {
      return (
        <div style={{
          background:   tooltipBg,
          border:       `1px solid ${tooltipBorder}`,
          borderRadius: 10,
          padding:      '10px 16px',
          boxShadow:    '0 4px 24px rgba(0,0,0,0.1)',
          minWidth:     120,
        }}>
          <p style={{ color: axisStroke, fontSize: 11, marginBottom: 6, fontWeight: 600 }}>{label}</p>
          <p style={{ color: tooltipText, fontSize: 18, fontWeight: 800, margin: 0 }}>
            ${Math.round(payload[0].value).toLocaleString()}
          </p>
        </div>
      );
    }
    return null;
  };

  const totalYear = Object.values(normalizedData).reduce((a, b) => a + b, 0);

  return (
    <Card className="flex flex-col gap-4 !p-6" style={{ minHeight: 340 }}>
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h3 className="text-sm font-bold text-text">{title}</h3>
          <p className="text-xs text-text-muted mt-0.5">المجموع السنوي</p>
        </div>
        <div className="text-end">
          <p className="text-xl font-extrabold text-primary-500">${Math.round(totalYear).toLocaleString()}</p>
        </div>
      </div>

      {/* Chart */}
      <div style={{ flex: 1, minHeight: 260 }}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={chartData} margin={{ top: 4, right: 4, left: 0, bottom: 0 }} barCategoryGap="30%">
            <CartesianGrid strokeDasharray="3 3" stroke={gridStroke} vertical={false} />
            <XAxis
              dataKey="name"
              stroke={axisStroke}
              fontSize={10}
              tickLine={false}
              axisLine={false}
            />
            <YAxis
              stroke={axisStroke}
              fontSize={10}
              tickLine={false}
              axisLine={false}
              tickFormatter={formatMillions}
              width={48}
            />
            <Tooltip content={<CustomTooltip />} cursor={{ fill: isDark ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.04)' }} />
            <Bar dataKey="total" radius={[6, 6, 0, 0]}>
              {chartData.map((entry) => (
                <Cell
                  key={entry.month}
                  fill={entry.month === currentMonth ? barCurrent : barColor}
                  fillOpacity={entry.month === currentMonth ? 1 : 0.55}
                />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </Card>
  );
};

// Keep the other exports as no-ops so no import errors occur
export const MonthlyCollectionsChart = () => null;
export const ContractsByStatusChart  = () => null;
export const TopEmployeesChart       = () => null;
export const YearComparisonChart     = () => null;
