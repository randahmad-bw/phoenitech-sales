import React, { useState } from 'react';
import { useMonthlyReport, useYearlyReport } from '@/hooks/queries';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { Card } from '@/components/ui/Card';
import { Table } from '@/components/ui/Table';
import { formatCurrency, downloadBlob } from '@/utils';
import api from '@/lib/axios';
import { useTranslation } from 'react-i18next';
import { FileText, FileSpreadsheet, Award, TrendingUp } from 'lucide-react';

export const ReportsPage: React.FC = () => {
  const { t, i18n } = useTranslation();
  const [activeTab, setActiveTab] = useState<'monthly' | 'yearly'>('monthly');

  // Filters
  const [year, setYear] = useState<number>(new Date().getFullYear());
  const [month, setMonth] = useState<number>(new Date().getMonth() + 1);

  // Queries
  const { data: monthlyData, isLoading: monthlyLoading } = useMonthlyReport(year, month) as any;
  const { data: yearlyData, isLoading: yearlyLoading } = useYearlyReport(year) as any;

  const exportReport = async (format: 'pdf' | 'excel') => {
    try {
      const response = await api.get('/export/report', {
        params: {
          type: activeTab,
          format,
          year,
          month: activeTab === 'monthly' ? month : undefined,
        },
        responseType: 'blob',
      });
      const ext = format === 'pdf' ? 'pdf' : 'xlsx';
      downloadBlob(response.data, `${activeTab}_report_${year}_${month}.${ext}`);
    } catch (err) {
      console.error('Report export failed', err);
    }
  };

  const years = [
    { value: '2026', label: '2026' },
    { value: '2025', label: '2025' },
    { value: '2024', label: '2024' },
  ];

  const months = Array.from({ length: 12 }, (_, i) => ({
    value: (i + 1).toString(),
    label: t(`months.${i + 1}`, new Date(0, i).toLocaleString(i18n.language, { month: 'long' })),
  }));

  return (
    <div className="flex flex-col gap-6">
      {/* Title Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-black text-text uppercase tracking-wider">{t('report.title', 'Reports & Statements')}</h1>
          <p className="text-xs text-text-muted mt-1">{t('report.subtitle', 'Generate audited financial reports, monthly conversions, and yearly targets.')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => exportReport('excel')} className="flex items-center gap-1.5 text-success-text hover:text-success-text/90">
            <FileSpreadsheet size={14} />
            Excel
          </Button>
          <Button variant="outline" size="sm" onClick={() => exportReport('pdf')} className="flex items-center gap-1.5 text-danger-text hover:text-danger-text/90">
            <FileText size={14} />
            PDF Report
          </Button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-border gap-1.5">
        <button
          onClick={() => setActiveTab('monthly')}
          className={`px-4 py-2.5 text-sm font-bold border-b-2 transition-all cursor-pointer ${activeTab === 'monthly' ? 'border-blue-500 text-blue-400' : 'border-transparent text-text-muted hover:text-text'}`}
        >
          {t('report.monthly', 'Monthly Performance')}
        </button>
        <button
          onClick={() => setActiveTab('yearly')}
          className={`px-4 py-2.5 text-sm font-bold border-b-2 transition-all cursor-pointer ${activeTab === 'yearly' ? 'border-blue-500 text-blue-400' : 'border-transparent text-text-muted hover:text-text'}`}
        >
          {t('report.yearly', 'Yearly Consolidation')}
        </button>
      </div>

      {/* Monthly Report View */}
      {activeTab === 'monthly' && (
        <div className="flex flex-col gap-6">
          {/* Filters */}
          <Card className="p-5 border-border bg-surface-light">
            <div className="flex flex-wrap items-center gap-4">
              <div className="w-36">
                <Select label={t('year', 'Year')} options={years} value={year.toString()} onChange={(e) => setYear(parseInt(e.target.value))} />
              </div>
              <div className="w-36">
                <Select label={t('month', 'Month')} options={months} value={month.toString()} onChange={(e) => setMonth(parseInt(e.target.value))} />
              </div>
            </div>
          </Card>

          {monthlyLoading ? (
            <div className="text-center py-20 text-sm text-text-muted">{t('common.loading', 'Loading...')}</div>
          ) : monthlyData ? (
            <>
              {/* Summary Cards */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-5">
                <Card className="p-4 border-border bg-surface-lighter">
                  <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('report.new_companies', 'New Companies')}</div>
                  <div className="text-xl font-extrabold text-text mt-1">{monthlyData.current.new_companies}</div>
                </Card>
                <Card className="p-4 border-border bg-surface-lighter">
                  <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('report.new_contracts', 'New Contracts')}</div>
                  <div className="text-xl font-extrabold text-text mt-1">{monthlyData.current.new_contracts}</div>
                </Card>
                <Card className="p-4 border-border bg-surface-lighter">
                  <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.value', 'Sales Value')}</div>
                  <div className="text-xl font-extrabold text-text mt-1">{formatCurrency(monthlyData.current.total_value)}</div>
                </Card>
                <Card className="p-4 border-border bg-surface-lighter">
                  <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('report.collected', 'Collected')}</div>
                  <div className="text-xl font-extrabold text-success-text mt-1">{formatCurrency(monthlyData.current.collected)}</div>
                </Card>
                <Card className="p-4 border-border bg-surface-lighter">
                  <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.remaining', 'Remaining')}</div>
                  <div className="text-xl font-extrabold text-danger-text mt-1">{formatCurrency(monthlyData.current.remaining)}</div>
                </Card>
              </div>

              {/* Comparison table */}
              <div className="flex flex-col gap-3">
                <h3 className="text-sm font-bold text-text-muted uppercase tracking-wider">{t('report.vs_previous', 'Month-over-Month Variance')}</h3>
                <Table
                  columns={[
                    { key: 'metric', header: t('report.metric', 'Performance KPI') },
                    { key: 'prev', header: t('report.previous_month', 'Previous Month'), render: (row: any) => row.prev },
                    { key: 'curr', header: t('report.current_month', 'Current Month'), render: (row: any) => row.curr },
                    { key: 'diff', header: t('report.variance', 'Variance'), render: (row: any) => {
                      const isPositive = row.diffVal >= 0;
                      return (
                        <span className={isPositive ? 'text-success-text font-bold' : 'text-danger-text font-bold'}>
                          {isPositive ? '+' : ''}{row.diff}
                        </span>
                      );
                    }},
                  ]}
                  data={[
                    { id: 1, metric: t('report.new_companies', 'New Companies Signed'), prev: monthlyData.previous.new_companies, curr: monthlyData.current.new_companies, diff: monthlyData.comparison.new_companies_diff, diffVal: monthlyData.comparison.new_companies_diff },
                    { id: 2, metric: t('report.new_contracts', 'New Contracts Signed'), prev: monthlyData.previous.new_contracts, curr: monthlyData.current.new_contracts, diff: monthlyData.comparison.new_contracts_diff, diffVal: monthlyData.comparison.new_contracts_diff },
                    { id: 3, metric: t('contract.value', 'Sales Value (USD)'), prev: formatCurrency(monthlyData.previous.total_value), curr: formatCurrency(monthlyData.current.total_value), diff: formatCurrency(monthlyData.comparison.total_value_diff), diffVal: monthlyData.comparison.total_value_diff },
                    { id: 4, metric: t('report.collected', 'Collected Payments (USD)'), prev: formatCurrency(monthlyData.previous.collected), curr: formatCurrency(monthlyData.current.collected), diff: formatCurrency(monthlyData.comparison.collected_diff), diffVal: monthlyData.comparison.collected_diff },
                  ]}
                />
              </div>
            </>
          ) : null}
        </div>
      )}

      {/* Yearly Report View */}
      {activeTab === 'yearly' && (
        <div className="flex flex-col gap-6">
          {/* Filters */}
          <Card className="p-5 border-border bg-surface-light">
            <div className="w-36">
              <Select label={t('year', 'Year')} options={years} value={year.toString()} onChange={(e) => setYear(parseInt(e.target.value))} />
            </div>
          </Card>

          {yearlyLoading ? (
            <div className="text-center py-20 text-sm text-text-muted">{t('common.loading', 'Loading...')}</div>
          ) : yearlyData ? (
            <>
              {/* Highlight Cards */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Card className="flex items-center justify-between border-border bg-surface-lighter p-5">
                  <div className="min-w-0">
                    <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('report.best_month', 'Highest Performing Month')}</div>
                    <div className="text-base font-extrabold text-text mt-1">{t('month', 'Month')} {yearlyData.best_month.month}</div>
                    <div className="text-xs text-text-muted mt-0.5">{formatCurrency(yearlyData.best_month.value)}</div>
                  </div>
                  <div className="h-10 w-10 bg-blue-500/10 text-info-text flex items-center justify-center rounded-lg">
                    <TrendingUp size={20} />
                  </div>
                </Card>
                <Card className="flex items-center justify-between border-border bg-surface-lighter p-5">
                  <div className="min-w-0">
                    <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('report.best_employee', 'Top Sales Rep')}</div>
                    <div className="text-base font-extrabold text-text mt-1">{yearlyData.best_employee?.name || '—'}</div>
                    <div className="text-xs text-text-muted mt-0.5">{formatCurrency(yearlyData.best_employee?.total || 0)}</div>
                  </div>
                  <div className="h-10 w-10 bg-purple-500/10 text-primary-text flex items-center justify-center rounded-lg">
                    <Award size={20} />
                  </div>
                </Card>
              </div>

              {/* Monthly breakdown table */}
              <div className="flex flex-col gap-3">
                <h3 className="text-sm font-bold text-text-muted uppercase tracking-wider">{t('report.yearly_breakdown', 'Monthly Sales Breakdown')}</h3>
                <Table
                  columns={[
                    { key: 'month', header: t('month', 'Month'), render: (row: any) => `${t('month', 'Month')} ${row.month}` },
                    { key: 'new_companies', header: t('report.new_companies', 'New Companies') },
                    { key: 'new_contracts', header: t('report.new_contracts', 'New Contracts') },
                    { key: 'total_value', header: t('contract.value', 'Sales Value (USD)'), render: (row: any) => formatCurrency(row.total_value) },
                    { key: 'collected', header: t('report.collected', 'Collected (USD)'), render: (row: any) => formatCurrency(row.collected) },
                    { key: 'remaining', header: t('contract.remaining', 'Remaining (USD)'), render: (row: any) => formatCurrency(row.remaining) },
                  ]}
                  data={Object.values(yearlyData.monthly_breakdown)}
                />
              </div>
            </>
          ) : null}
        </div>
      )}
    </div>
  );
};
