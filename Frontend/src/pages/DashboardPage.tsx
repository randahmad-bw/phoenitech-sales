import React, { useState } from 'react';
import { useNavigate } from 'react-router';
import { useDashboard } from '@/hooks/queries';
import { Spinner } from '@/components/ui/Spinner';
import { Card } from '@/components/ui/Card';
import { formatCurrency, formatPercentage } from '@/utils';
import { useTranslation } from 'react-i18next';
import {
  FileText,
  TrendingUp,
  CheckCircle,
  Calendar,
  User,
  DollarSign,
  Percent,
  Wallet,
} from 'lucide-react';

const YEARS = [2026, 2025, 2024, 2023];

const ARABIC_MONTHS = [
  'كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران',
  'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول',
];

const _now = new Date();
const THIS_MONTH_LABEL = ARABIC_MONTHS[_now.getMonth()];
const _prevDate = new Date(_now.getFullYear(), _now.getMonth() - 1, 1);
const PREV_MONTH_LABEL = ARABIC_MONTHS[_prevDate.getMonth()];


const EMPLOYEE_COLORS = [
  { ring: 'ring-primary-500/60',  avatar: 'bg-primary-500/15 text-primary-500',  glow: 'shadow-primary-500/10' },
  { ring: 'ring-info-text/60',    avatar: 'bg-info-bg text-info-text',            glow: 'shadow-info-text/10' },
  { ring: 'ring-success-text/60', avatar: 'bg-success-bg text-success-text',      glow: 'shadow-success-text/10' },
  { ring: 'ring-warning-text/60', avatar: 'bg-warning-bg text-warning-text',      glow: 'shadow-warning-text/10' },
];

export const DashboardPage: React.FC = () => {
  const navigate = useNavigate();
  const [selectedYear, setSelectedYear] = useState<number>(new Date().getFullYear());
  const { data: dashboard, isLoading, error } = useDashboard(selectedYear);
  const { t } = useTranslation();

  if (isLoading) return <Spinner className="mt-20" size="lg" />;
  if (error || !dashboard) return (
    <div className="flex items-center justify-center mt-20 text-danger-text font-medium">
      {t('dashboard.error', 'تعذّر تحميل إحصائيات لوحة التحكم.')}
    </div>
  );

  const { stats, charts } = dashboard;

  const kpis = [
    {
      label: 'إجمالي العقود',
      display: String(stats.total_contracts),
      sub: `${stats.active_contracts} نشط`,
      icon: FileText,
      accent: 'text-primary-500',
      bg: 'bg-primary-500/10',
      onClick: () => navigate(`/contracts?status=active&year=${selectedYear}`),
    },
    {
      label: 'عقود هذا الشهر',
      display: String(stats.new_contracts_this_month),
      sub: stats.renewed_contracts_this_month > 0 
        ? `${stats.new_contracts_this_month} جديد (مجدد: ${stats.renewed_contracts_this_month})` 
        : 'عقد جديد',
      icon: Calendar,
      accent: 'text-warning-text',
      bg: 'bg-warning-bg',
    },
    {
      label: 'مبيعات هذا الشهر',
      display: formatCurrency(stats.sales_this_month),
      sub: 'قيمة العقود',
      icon: TrendingUp,
      accent: 'text-info-text',
      bg: 'bg-info-bg',
    },
    {
      label: 'إجمالي المبيعات',
      display: formatCurrency(stats.total_sales ?? 0),
      sub: 'منذ البداية',
      icon: CheckCircle,
      accent: 'text-success-text',
      bg: 'bg-success-bg',
    },
  ];

  const collectionKpis = [
    {
      label: 'إجمالي المحصّل',
      display: formatCurrency(stats.total_paid),
      sub: 'مبالغ مدفوعة',
      icon: DollarSign,
      accent: 'text-success-text',
      bg: 'bg-success-bg',
    },
    {
      label: 'المتبقي للتحصيل',
      display: formatCurrency(stats.total_remaining),
      sub: 'مبالغ غير مدفوعة',
      icon: Wallet,
      accent: 'text-danger-text',
      bg: 'bg-danger-bg',
      onClick: () => navigate(`/contracts?uncollected=true&year=${selectedYear}`),
    },
    {
      label: 'نسبة التحصيل الكلية',
      display: formatPercentage(stats.collection_percentage),
      sub: stats.collection_percentage >= 80 ? 'ممتاز' : stats.collection_percentage >= 50 ? 'جيد' : 'يحتاج متابعة',
      icon: Percent,
      accent: stats.collection_percentage >= 80 ? 'text-success-text' : stats.collection_percentage >= 50 ? 'text-warning-text' : 'text-danger-text',
      bg: stats.collection_percentage >= 80 ? 'bg-success-bg' : stats.collection_percentage >= 50 ? 'bg-warning-bg' : 'bg-danger-bg',
    },
    {
      label: 'محصّل هذا الشهر',
      display: formatCurrency(stats.collected_this_month),
      sub: 'دفعات مؤكدة',
      icon: Calendar,
      accent: 'text-info-text',
      bg: 'bg-info-bg',
    },
  ];

  const employees: any[] = (charts.employee_monthly_contracts || [])
    .filter((emp: any) => !emp.department || emp.department === 'sales' || emp.department === 'management')
    .sort((a: any, b: any) => b.sales_this_month - a.sales_this_month);

  return (
    <div className="flex flex-col gap-7">

      {/* ── Header ───────────────────────────────────────── */}
      <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black text-text tracking-tight">لوحة التحكم</h1>
          <p className="text-xs text-text-muted mt-1">نظرة شاملة على أداء مبيعات فوني تيك</p>
        </div>

        {/* Year filter */}
        <div className="flex items-center gap-2">
          <span className="text-xs text-text-muted font-medium">السنة:</span>
          <div className="flex rounded-lg border border-border overflow-hidden text-xs font-semibold">
            {YEARS.map((y) => (
              <button
                key={y}
                onClick={() => setSelectedYear(y)}
                className={`px-3 py-1.5 transition-colors ${
                  selectedYear === y
                    ? 'bg-primary-500 text-white'
                    : 'bg-surface-light text-text-muted hover:bg-surface-lighter'
                }`}
              >
                {y}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* ── KPI Cards ────────────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {kpis.map((kpi, idx) => {
          const Icon = kpi.icon;
          return (
            <Card
              key={idx}
              className={`flex flex-col gap-3 !p-5 transition-all duration-200 ${
                kpi.onClick ? 'cursor-pointer hover:shadow-md hover:border-primary-500/30 hover:-translate-y-0.5' : ''
              }`}
              onClick={kpi.onClick}
            >
              <div className="flex items-start justify-between">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${kpi.bg}`}>
                  <Icon size={18} className={kpi.accent} />
                </div>
                <span className="text-xs md:text-sm font-bold text-text-muted text-end leading-tight max-w-[75%]">
                  {kpi.label}
                </span>
              </div>
              <div>
                <p className="text-2xl font-extrabold text-text leading-none tracking-tight">
                  {kpi.display}
                </p>
                <p className={`text-xs mt-1 font-medium ${kpi.accent}`}>{kpi.sub}</p>
              </div>
            </Card>
          );
        })}
      </div>

      {/* ── Collection KPI Cards ───────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {collectionKpis.map((kpi, idx) => {
          const Icon = kpi.icon;
          return (
            <Card
              key={idx}
              className={`flex flex-col gap-3 !p-5 transition-all duration-200 ${
                kpi.onClick ? 'cursor-pointer hover:shadow-md hover:border-primary-500/30 hover:-translate-y-0.5' : ''
              }`}
              onClick={kpi.onClick}
            >
              <div className="flex items-start justify-between">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${kpi.bg}`}>
                  <Icon size={18} className={kpi.accent} />
                </div>
                <span className="text-xs md:text-sm font-bold text-text-muted text-end leading-tight max-w-[75%]">
                  {kpi.label}
                </span>
              </div>
              <div>
                <p className="text-2xl font-extrabold text-text leading-none tracking-tight">
                  {kpi.display}
                </p>
                <p className={`text-xs mt-1 font-medium ${kpi.accent}`}>{kpi.sub}</p>
              </div>
            </Card>
          );
        })}
      </div>

      {/* ── Employee Stats Section ────────────────────────────── */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <div>
            <h2 className="text-base font-bold text-text">أداء فريق المبيعات</h2>
            <p className="text-xs text-text-muted mt-0.5">
              مقارنة بين {THIS_MONTH_LABEL} و{PREV_MONTH_LABEL}
            </p>
          </div>
          <span className="text-[10px] text-text-muted bg-surface-lighter border border-border rounded-full px-3 py-1 font-semibold uppercase tracking-wider">
            {employees.length} موظف
          </span>
        </div>

        {employees.length === 0 ? (
          <Card className="py-12 flex items-center justify-center text-text-muted text-sm">
            لا يوجد موظفون مسجّلون.
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            {employees.map((emp: any, idx: number) => {
              const color = EMPLOYEE_COLORS[idx % EMPLOYEE_COLORS.length];
              const nameParts = emp.name.trim().split(/\s+/);
              const initials = nameParts.slice(0, 2).map((w: string) => w[0]).join('');
              return (
                <Card
                  key={idx}
                  className={`!p-0 overflow-hidden ring-1 ${color.ring} shadow-lg ${color.glow}`}
                >
                  {/* ── Card Header ── */}
                  <div className="flex items-center gap-3 px-5 py-4 border-b border-border">
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${color.avatar}`}>
                      <User size={20} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="font-bold text-text text-sm truncate">{emp.name}</p>
                      <p className="text-[11px] text-text-muted font-medium">
                        {emp.total_contracts} عقد إجمالي
                      </p>
                    </div>
                    <div className="text-end shrink-0">
                      <p className="text-[10px] text-text-muted font-medium">إجمالي المبيعات</p>
                      <p className="text-sm font-black text-text">{formatCurrency(emp.total_sales)}</p>
                    </div>
                  </div>

                  {/* ── Stats Grid ── */}
                  <div className="grid grid-cols-2 divide-x divide-x-reverse divide-border">

                    {/* This Month */}
                    <div className="px-4 py-4 flex flex-col gap-3">
                      <p className="text-[10px] font-bold text-text-muted uppercase tracking-widest text-center border-b border-border/50 pb-2">
                        {THIS_MONTH_LABEL}
                      </p>

                      {/* Contracts */}
                      <div className="flex items-center justify-between gap-2">
                        <span className="text-[11px] text-text-muted font-medium">العقود</span>
                        <span className="text-lg font-black text-primary-500 leading-none">
                          {emp.contracts_this_month}
                        </span>
                      </div>

                      {/* Sales */}
                      <div className="flex items-center justify-between gap-2">
                        <span className="text-[11px] text-text-muted font-medium">المبيعات</span>
                        <span
                          className={`text-sm font-extrabold leading-none ${
                            emp.sales_this_month > 0 ? 'text-success-text' : 'text-text-muted'
                          }`}
                        >
                          {formatCurrency(emp.sales_this_month)}
                        </span>
                      </div>

                    </div>

                    {/* Previous Month */}
                    <div className="px-4 py-4 flex flex-col gap-3">
                      <p className="text-[10px] font-bold text-text-muted uppercase tracking-widest text-center border-b border-border/50 pb-2">
                        {PREV_MONTH_LABEL}
                      </p>

                      {/* Contracts prev */}
                      <div className="flex items-center justify-between gap-2">
                        <span className="text-[11px] text-text-muted font-medium">العقود</span>
                        <span className="text-lg font-black text-text-muted leading-none">
                          {emp.contracts_prev_month}
                        </span>
                      </div>

                      {/* Sales prev */}
                      <div className="flex items-center justify-between gap-2">
                        <span className="text-[11px] text-text-muted font-medium">المبيعات</span>
                        <span
                          className={`text-sm font-extrabold leading-none ${
                            emp.sales_prev_month > 0 ? 'text-text' : 'text-text-muted'
                          }`}
                        >
                          {formatCurrency(emp.sales_prev_month)}
                        </span>
                      </div>
                    </div>

                  </div>
                </Card>
              );
            })}
          </div>
        )}
      </div>

    </div>
  );
};
