import React, { useState, useMemo, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import { subscriptionApi, contractApi, serviceApi } from '@/api';
import { employeeApi } from '@/api/employees';
import { companyApi } from '@/api/companies';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { Select } from '@/components/ui/Select';
import { Input } from '@/components/ui/Input';
import { Spinner } from '@/components/ui/Spinner';
import { formatCurrency, formatDate, cn } from '@/utils';
import { useTranslation } from 'react-i18next';
import { useUiStore } from '@/store/uiStore';
import type { Contract, SubscriptionDashboard, Employee, Company, Service } from '@/types';
import {
  RefreshCcw,
  Activity,
  AlertTriangle,
  XCircle,
  TrendingUp,
  DollarSign,
  Search,
  ChevronLeft,
  ChevronRight,
  Eye,
  Clock,
  Building2,
  Filter,
  RotateCw,
} from 'lucide-react';

// ─── HELPERS ────────────────────────────────────────────────────

function getDaysRemaining(endDate: string | null): number | null {
  if (!endDate) return null;
  const end = new Date(endDate);
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  end.setHours(0, 0, 0, 0);
  return Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
}

function getDaysRemainingColor(days: number | null): string {
  if (days === null) return 'text-text-muted';
  if (days < 0) return 'text-danger-text';
  if (days <= 7) return 'text-danger-text';
  if (days <= 30) return 'text-warning-text';
  return 'text-success-text';
}

function getDaysRemainingBg(days: number | null): string {
  if (days === null) return 'bg-surface-lighter';
  if (days < 0) return 'bg-danger-bg';
  if (days <= 7) return 'bg-danger-bg';
  if (days <= 30) return 'bg-warning-bg';
  return 'bg-success-bg';
}

// ─── PRODUCT BADGE ──────────────────────────────────────────────

const ProductBadge: React.FC<{ product?: string; isAr: boolean }> = ({ product, isAr }) => {
  const configs: Record<string, { label: string; labelEn: string; bg: string; text: string; border: string }> = {
    phoenitech: {
      label: 'فونيتيك',
      labelEn: 'PhoeniTech',
      bg: 'bg-primary-bg',
      text: 'text-primary-text',
      border: 'border-primary-text/15',
    },
    onocode: {
      label: 'أونو كود',
      labelEn: 'OnoCode',
      bg: 'bg-[rgba(139,92,246,0.12)]',
      text: 'text-[#a78bfa]',
      border: 'border-[#a78bfa]/15',
    },
    other: {
      label: 'أخرى',
      labelEn: 'Other',
      bg: 'bg-surface-lighter',
      text: 'text-text-muted',
      border: 'border-border',
    },
  };

  const config = configs[product || 'phoenitech'] || configs.phoenitech;

  return (
    <span className={cn('badge', config.bg, config.text, config.border)}>
      {isAr ? config.label : config.labelEn}
    </span>
  );
};

// ─── SUBSCRIPTION STATUS BADGE ─────────────────────────────────

const SubscriptionStatusBadge: React.FC<{ contract: Contract; isAr: boolean }> = ({ contract, isAr }) => {
  const days = getDaysRemaining(contract.end_date);

  if (contract.status === 'cancelled') {
    return (
      <span className="badge bg-danger-bg text-danger-text border-danger-text/15">
        {isAr ? 'ملغي' : 'Cancelled'}
      </span>
    );
  }

  if (days !== null && days < 0) {
    return (
      <span className="badge bg-danger-bg text-danger-text border-danger-text/15">
        {isAr ? 'منتهي' : 'Expired'}
      </span>
    );
  }

  if (days !== null && days <= 30 && contract.status === 'active') {
    return (
      <span className="badge bg-warning-bg text-warning-text border-warning-text/15">
        {isAr ? 'قريب الانتهاء' : 'Expiring Soon'}
      </span>
    );
  }

  return <Badge status={contract.status} />;
};

// ─── KPI STAT CARD ─────────────────────────────────────────────

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: string | number;
  subtitle?: string;
  accentColor?: string;
}

const StatCard: React.FC<StatCardProps> = ({ icon, label, value, subtitle, accentColor = 'border-s-primary-500' }) => (
  <div className={cn('stat-card border-s-4', accentColor)}>
    <div className="flex items-start justify-between">
      <div className="flex-1 min-w-0">
        <p className="text-xs font-semibold text-text-muted uppercase tracking-wider mb-1">{label}</p>
        <p className="text-2xl font-bold text-text">{value}</p>
        {subtitle && <p className="text-xs text-text-muted mt-1">{subtitle}</p>}
      </div>
      <div className="p-2.5 rounded-lg bg-surface-lighter text-text-muted shrink-0">
        {icon}
      </div>
    </div>
  </div>
);

// ─── MAIN PAGE COMPONENT ───────────────────────────────────────

export const SubscriptionsPage: React.FC = () => {
  const { t } = useTranslation();
  const { language } = useUiStore();
  const isAr = language === 'ar';

  // ── Filters State ──
  const [product, setProduct] = useState<string>('all');
  const [status, setStatus] = useState<string>('');
  const [employeeId, setEmployeeId] = useState<string>('');
  const [companyId, setCompanyId] = useState<string>('');
  const [serviceId, setServiceId] = useState<string>('');
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [page, setPage] = useState(1);
  const [showFilters, setShowFilters] = useState(false);

  // ── Data queries ──
  const dashboardParams = useMemo(() => ({
    product: product !== 'all' ? product : undefined,
  }), [product]);

  const listParams = useMemo(() => ({
    product: product !== 'all' ? product : undefined,
    status: status || undefined,
    employee_id: employeeId || undefined,
    company_id: companyId || undefined,
    service_id: serviceId || undefined,
    search: search || undefined,
    page,
    per_page: 20,
  }), [product, status, employeeId, companyId, serviceId, search, page]);

  const { data: dashboardData, isLoading: dashboardLoading } = useQuery({
    queryKey: ['subscriptions-dashboard', dashboardParams],
    queryFn: async () => {
      const { data } = await subscriptionApi.dashboard(dashboardParams);
      return data.data as SubscriptionDashboard;
    },
  });

  const { data: listData, isLoading: listLoading } = useQuery({
    queryKey: ['subscriptions-list', listParams],
    queryFn: async () => {
      const { data } = await subscriptionApi.list(listParams);
      return data;
    },
  });

  // Supporting data for filter dropdowns
  const { data: employeesData } = useQuery({
    queryKey: ['employees-dropdown'],
    queryFn: async () => {
      const { data } = await employeeApi.list({ per_page: 100 });
      return data.data as Employee[];
    },
  });

  const { data: companiesData } = useQuery({
    queryKey: ['companies-dropdown'],
    queryFn: async () => {
      const { data } = await companyApi.list({ per_page: 200 });
      return data.data as Company[];
    },
  });

  const { data: servicesData } = useQuery({
    queryKey: ['services-dropdown'],
    queryFn: async () => {
      const { data } = await serviceApi.list();
      return data.data as Service[];
    },
  });

  const subscriptions = listData?.data ?? [];
  const meta = listData?.meta;

  // ── Handlers ──
  const handleSearch = useCallback(() => {
    setSearch(searchInput);
    setPage(1);
  }, [searchInput]);

  const resetFilters = useCallback(() => {
    setProduct('all');
    setStatus('');
    setEmployeeId('');
    setCompanyId('');
    setServiceId('');
    setSearch('');
    setSearchInput('');
    setPage(1);
  }, []);

  // ── Table Columns ──
  const columns = useMemo(() => [
    {
      key: 'contract_number',
      header: isAr ? 'رقم العقد' : 'Contract #',
      render: (row: Contract) => (
        <span className="font-mono text-sm font-semibold text-primary-text">{row.contract_number}</span>
      ),
    },
    {
      key: 'company',
      header: isAr ? 'الشركة' : 'Company',
      render: (row: Contract) => (
        <div className="flex items-center gap-2">
          <div className="w-7 h-7 rounded-lg bg-surface-lighter flex items-center justify-center">
            <Building2 size={14} className="text-text-muted" />
          </div>
          <span className="font-medium text-sm truncate max-w-[160px]">{row.company?.name || '—'}</span>
        </div>
      ),
    },
    {
      key: 'product',
      header: isAr ? 'المنتج' : 'Product',
      render: (row: Contract) => <ProductBadge product={row.product} isAr={isAr} />,
    },
    {
      key: 'service',
      header: isAr ? 'الخدمة' : 'Service',
      render: (row: Contract) => (
        <span className="text-sm text-text-muted">
          {isAr ? row.service?.name_ar : row.service?.name_en || '—'}
        </span>
      ),
    },
    {
      key: 'employee',
      header: isAr ? 'المسؤول' : 'Employee',
      render: (row: Contract) => (
        <span className="text-sm">{row.employee?.name || '—'}</span>
      ),
    },
    {
      key: 'period',
      header: isAr ? 'الفترة' : 'Period',
      render: (row: Contract) => (
        <div className="text-xs space-y-0.5">
          <div className="text-text-muted">{formatDate(row.start_date, language)}</div>
          <div className="text-text font-medium">{formatDate(row.end_date, language)}</div>
        </div>
      ),
    },
    {
      key: 'days_remaining',
      header: isAr ? 'المتبقي' : 'Remaining',
      render: (row: Contract) => {
        const days = getDaysRemaining(row.end_date);
        if (days === null) return <span className="text-text-muted">—</span>;
        const color = getDaysRemainingColor(days);
        const bg = getDaysRemainingBg(days);
        return (
          <div className={cn('inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold', bg, color)}>
            <Clock size={12} />
            {days < 0
              ? (isAr ? `${Math.abs(days)} يوم منتهي` : `${Math.abs(days)}d overdue`)
              : (isAr ? `${days} يوم` : `${days}d`)
            }
          </div>
        );
      },
    },
    {
      key: 'value',
      header: isAr ? 'القيمة' : 'Value',
      render: (row: Contract) => (
        <span className="font-semibold text-sm">{formatCurrency(row.contract_value, row.currency)}</span>
      ),
    },
    {
      key: 'collection',
      header: isAr ? 'التحصيل' : 'Collection',
      render: (row: Contract) => {
        const pct = row.collection_percentage ?? 0;
        return (
          <div className="flex items-center gap-2 min-w-[100px]">
            <div className="flex-1 h-1.5 bg-surface-lighter rounded-full overflow-hidden">
              <div
                className={cn(
                  'h-full rounded-full transition-all duration-500',
                  pct >= 100 ? 'bg-success-500' : pct >= 50 ? 'bg-primary-500' : 'bg-warning-500'
                )}
                style={{ width: `${Math.min(pct, 100)}%` }}
              />
            </div>
            <span className="text-xs font-semibold text-text-muted w-10 text-end">{Math.round(pct)}%</span>
          </div>
        );
      },
    },
    {
      key: 'status',
      header: isAr ? 'الحالة' : 'Status',
      render: (row: Contract) => <SubscriptionStatusBadge contract={row} isAr={isAr} />,
    },
    {
      key: 'renewals',
      header: isAr ? 'التجديدات' : 'Renewals',
      render: (row: Contract) => {
        const count = row.renewals_count ?? 0;
        return count > 0 ? (
          <span className="inline-flex items-center gap-1 text-xs font-semibold text-info-text bg-info-bg px-2 py-0.5 rounded-full border border-info-text/15">
            <RotateCw size={11} /> {count}
          </span>
        ) : (
          <span className="text-text-muted text-xs">—</span>
        );
      },
    },
  ], [isAr, language]);

  // ── Product tab buttons ──
  const productTabs = [
    { value: 'all', label: isAr ? 'الكل' : 'All', icon: null },
    { value: 'phoenitech', label: 'PhoeniTech', icon: '🟢' },
    { value: 'onocode', label: 'OnoCode', icon: '🟣' },
  ];

  return (
    <div className="animate-fade-in space-y-6">
      {/* ─── HEADER ─────────────────────────────────────── */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-700 via-primary-600 to-primary-500 p-6 text-white">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(255,255,255,0.1),transparent_60%)]" />
        <div className="relative flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold flex items-center gap-3">
              <RefreshCcw size={28} />
              {isAr ? 'إدارة الاشتراكات والتجديدات' : 'Subscriptions & Renewals'}
            </h1>
            <p className="mt-1 text-white/80 text-sm">
              {isAr ? 'تتبع وإدارة تجديدات العقود لجميع المنتجات' : 'Track and manage contract renewals across all products'}
            </p>
          </div>

          {/* Product Tabs */}
          <div className="flex bg-white/10 rounded-xl p-1 backdrop-blur-sm border border-white/20">
            {productTabs.map((tab) => (
              <button
                key={tab.value}
                onClick={() => { setProduct(tab.value); setPage(1); }}
                className={cn(
                  'px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200 cursor-pointer',
                  product === tab.value
                    ? 'bg-white text-primary-700 shadow-md'
                    : 'text-white/80 hover:text-white hover:bg-white/10'
                )}
              >
                {tab.icon && <span className="me-1.5">{tab.icon}</span>}
                {tab.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* ─── KPI STATS ──────────────────────────────────── */}
      {dashboardLoading ? (
        <div className="flex justify-center py-8"><Spinner size="lg" /></div>
      ) : dashboardData && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
          <StatCard
            icon={<Activity size={20} />}
            label={isAr ? 'اشتراكات نشطة' : 'Active Subscriptions'}
            value={dashboardData.total_active}
            accentColor="border-s-success-500"
          />
          <StatCard
            icon={<AlertTriangle size={20} />}
            label={isAr ? 'قريبة الانتهاء' : 'Expiring Soon'}
            value={dashboardData.expiring_soon}
            subtitle={isAr ? 'خلال 30 يوم' : 'Within 30 days'}
            accentColor="border-s-warning-500"
          />
          <StatCard
            icon={<XCircle size={20} />}
            label={isAr ? 'منتهية الصلاحية' : 'Expired'}
            value={dashboardData.expired}
            accentColor="border-s-danger-500"
          />
          <StatCard
            icon={<TrendingUp size={20} />}
            label={isAr ? 'نسبة التجديد' : 'Renewal Rate'}
            value={`${dashboardData.renewal_rate}%`}
            subtitle={isAr ? `${dashboardData.renewed_this_month} هذا الشهر` : `${dashboardData.renewed_this_month} this month`}
            accentColor="border-s-primary-500"
          />
          <StatCard
            icon={<DollarSign size={20} />}
            label={isAr ? 'الإيرادات الشهرية' : 'Monthly Revenue'}
            value={formatCurrency(dashboardData.monthly_recurring_revenue)}
            subtitle={isAr ? `إجمالي: ${formatCurrency(dashboardData.total_value)}` : `Total: ${formatCurrency(dashboardData.total_value)}`}
            accentColor="border-s-info-text"
          />
        </div>
      )}

      {/* ─── Product Breakdown Cards (when showing all) ── */}
      {product === 'all' && dashboardData && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* PhoeniTech */}
          <Card className="!p-4 border-s-4 !border-s-primary-500">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-primary-bg flex items-center justify-center">
                  <span className="text-lg">🟢</span>
                </div>
                <div>
                  <p className="font-bold text-text">PhoeniTech</p>
                  <p className="text-xs text-text-muted">
                    {isAr ? `${dashboardData.by_product.phoenitech?.active ?? 0} نشط · ${dashboardData.by_product.phoenitech?.expired ?? 0} منتهي` : `${dashboardData.by_product.phoenitech?.active ?? 0} active · ${dashboardData.by_product.phoenitech?.expired ?? 0} expired`}
                  </p>
                </div>
              </div>
              <p className="text-lg font-bold text-primary-text">
                {formatCurrency(dashboardData.by_product.phoenitech?.value ?? 0)}
              </p>
            </div>
          </Card>
          {/* OnoCode */}
          <Card className="!p-4 border-s-4 !border-s-[#a78bfa]">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-[rgba(139,92,246,0.12)] flex items-center justify-center">
                  <span className="text-lg">🟣</span>
                </div>
                <div>
                  <p className="font-bold text-text">OnoCode</p>
                  <p className="text-xs text-text-muted">
                    {isAr ? `${dashboardData.by_product.onocode?.active ?? 0} نشط · ${dashboardData.by_product.onocode?.expired ?? 0} منتهي` : `${dashboardData.by_product.onocode?.active ?? 0} active · ${dashboardData.by_product.onocode?.expired ?? 0} expired`}
                  </p>
                </div>
              </div>
              <p className="text-lg font-bold text-[#a78bfa]">
                {formatCurrency(dashboardData.by_product.onocode?.value ?? 0)}
              </p>
            </div>
          </Card>
        </div>
      )}

      {/* ─── FILTERS ────────────────────────────────────── */}
      <Card className="!p-4">
        <div className="flex flex-wrap items-center gap-3 mb-3">
          {/* Search */}
          <div className="relative flex-1 min-w-[220px] max-w-md">
            <Search size={16} className="absolute start-3 top-1/2 -translate-y-1/2 text-text-muted" />
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
              placeholder={isAr ? 'بحث برقم العقد أو اسم الشركة...' : 'Search by contract # or company...'}
              className="input-field ps-9 h-10 text-sm"
            />
          </div>
          <button
            onClick={handleSearch}
            className="btn-primary h-10 px-5 text-sm flex items-center gap-2"
          >
            <Search size={15} />
            {isAr ? 'بحث' : 'Search'}
          </button>
          <button
            onClick={() => setShowFilters(!showFilters)}
            className={cn(
              'h-10 px-4 rounded-lg text-sm font-semibold flex items-center gap-2 border transition-all cursor-pointer',
              showFilters
                ? 'bg-primary-bg text-primary-text border-primary-text/20'
                : 'bg-surface-lighter text-text-muted border-border hover:text-text'
            )}
          >
            <Filter size={15} />
            {isAr ? 'فلاتر' : 'Filters'}
          </button>
          {(status || employeeId || companyId || serviceId || search) && (
            <button
              onClick={resetFilters}
              className="h-10 px-4 rounded-lg text-sm font-semibold text-danger-text bg-danger-bg border border-danger-text/15 flex items-center gap-2 hover:bg-danger-500/20 transition-all cursor-pointer"
            >
              <XCircle size={15} />
              {isAr ? 'إزالة الفلاتر' : 'Clear Filters'}
            </button>
          )}
        </div>

        {/* Expandable Filters */}
        {showFilters && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-border animate-fade-in">
            <Select
              label={isAr ? 'الحالة' : 'Status'}
              value={status}
              onChange={(e) => { setStatus(e.target.value); setPage(1); }}
              options={[
                { value: '', label: isAr ? 'جميع الحالات' : 'All Statuses' },
                { value: 'active', label: isAr ? 'نشط' : 'Active' },
                { value: 'expiring_soon', label: isAr ? 'قريب الانتهاء' : 'Expiring Soon' },
                { value: 'expired', label: isAr ? 'منتهي' : 'Expired' },
                { value: 'cancelled', label: isAr ? 'ملغي' : 'Cancelled' },
              ]}
            />
            <Select
              label={isAr ? 'الموظف' : 'Employee'}
              value={employeeId}
              onChange={(e) => { setEmployeeId(e.target.value); setPage(1); }}
              options={[
                { value: '', label: isAr ? 'جميع الموظفين' : 'All Employees' },
                ...(employeesData || []).map((e: Employee) => ({ value: e.id, label: e.name })),
              ]}
            />
            <Select
              label={isAr ? 'الشركة' : 'Company'}
              value={companyId}
              onChange={(e) => { setCompanyId(e.target.value); setPage(1); }}
              options={[
                { value: '', label: isAr ? 'جميع الشركات' : 'All Companies' },
                ...(companiesData || []).map((c: Company) => ({ value: c.id, label: c.name })),
              ]}
            />
            <Select
              label={isAr ? 'الخدمة' : 'Service'}
              value={serviceId}
              onChange={(e) => { setServiceId(e.target.value); setPage(1); }}
              options={[
                { value: '', label: isAr ? 'جميع الخدمات' : 'All Services' },
                ...(servicesData || []).map((s: Service) => ({
                  value: s.id,
                  label: isAr ? s.name_ar : s.name_en,
                })),
              ]}
            />
          </div>
        )}
      </Card>

      {/* ─── TABLE ──────────────────────────────────────── */}
      <Table<Contract>
        columns={columns}
        data={subscriptions}
        isLoading={listLoading}
        onRowClick={(row) => {
          window.open(`/contracts?show=${row.id}`, '_self');
        }}
      />

      {/* ─── PAGINATION ─────────────────────────────────── */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between px-2">
          <p className="text-sm text-text-muted">
            {isAr
              ? `عرض ${meta.from}–${meta.to} من ${meta.total}`
              : `Showing ${meta.from}–${meta.to} of ${meta.total}`
            }
          </p>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="p-2 rounded-lg border border-border hover:bg-surface-lighter disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            >
              {isAr ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
            </button>

            {/* Page numbers */}
            <div className="flex items-center gap-1">
              {Array.from({ length: Math.min(meta.last_page, 7) }, (_, i) => {
                let pageNum: number;
                if (meta.last_page <= 7) {
                  pageNum = i + 1;
                } else if (page <= 4) {
                  pageNum = i + 1;
                } else if (page >= meta.last_page - 3) {
                  pageNum = meta.last_page - 6 + i;
                } else {
                  pageNum = page - 3 + i;
                }
                return (
                  <button
                    key={pageNum}
                    onClick={() => setPage(pageNum)}
                    className={cn(
                      'w-9 h-9 rounded-lg text-sm font-semibold transition-all cursor-pointer',
                      page === pageNum
                        ? 'bg-primary-500 text-white shadow-md'
                        : 'hover:bg-surface-lighter text-text-muted'
                    )}
                  >
                    {pageNum}
                  </button>
                );
              })}
            </div>

            <button
              onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
              disabled={page >= meta.last_page}
              className="p-2 rounded-lg border border-border hover:bg-surface-lighter disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            >
              {isAr ? <ChevronLeft size={18} /> : <ChevronRight size={18} />}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
