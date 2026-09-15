import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { subscriptionApi, contractApi, serviceApi } from '@/api';
import { employeeApi } from '@/api/employees';
import { companyApi } from '@/api/companies';
import { useSubscriptionMutations } from '@/hooks/queries';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { Select } from '@/components/ui/Select';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Textarea } from '@/components/ui/Textarea';
import { Spinner } from '@/components/ui/Spinner';
import { formatCurrency, formatDate, cn } from '@/utils';
import { useTranslation } from 'react-i18next';
import { useUiStore } from '@/store/uiStore';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
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
  Plus,
  Edit2,
  Trash2,
  RefreshCw,
  MoreVertical,
}from 'lucide-react';

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

// ─── ACTION DROPDOWN ────────────────────────────────────────────

const SubscriptionActionDropdown: React.FC<{
  onView: () => void;
  onEdit: () => void;
  onRenew: () => void;
  onDelete: () => void;
  isAr: boolean;
}> = ({ onView, onEdit, onRenew, onDelete, isAr }) => {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = React.useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  return (
    <div className="relative inline-block text-start" ref={menuRef} onClick={(e) => e.stopPropagation()}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="p-1.5 rounded-lg text-text-muted hover:text-text hover:bg-surface-lighter transition-all border border-transparent hover:border-border active:scale-95 shadow-sm"
        title={isAr ? 'خيارات الإجراءات' : 'Actions Menu'}
      >
        <MoreVertical size={18} />
      </button>

      {isOpen && (
        <div className="absolute left-0 mt-1 w-48 bg-surface border border-border rounded-xl shadow-2xl z-50 overflow-hidden animate-fade-in py-1.5">
          <button
            onClick={() => { setIsOpen(false); onView(); }}
            className="w-full px-3.5 py-2.5 text-xs font-bold text-text hover:bg-surface-lighter flex items-center gap-2.5 transition-colors"
          >
            <Eye size={16} className="text-blue-500" />
            <span>{isAr ? 'عرض التفاصيل' : 'View Details'}</span>
          </button>

          <button
            onClick={() => { setIsOpen(false); onEdit(); }}
            className="w-full px-3.5 py-2.5 text-xs font-bold text-text hover:bg-surface-lighter flex items-center gap-2.5 transition-colors"
          >
            <Edit2 size={16} className="text-primary-400" />
            <span>{isAr ? 'تعديل الاشتراك' : 'Edit Subscription'}</span>
          </button>

          <button
            onClick={() => { setIsOpen(false); onRenew(); }}
            className="w-full px-3.5 py-2.5 text-xs font-bold text-text hover:bg-surface-lighter flex items-center gap-2.5 transition-colors"
          >
            <RefreshCw size={16} className="text-emerald-400" />
            <span>{isAr ? 'تجديد الاشتراك' : 'Renew Subscription'}</span>
          </button>

          <div className="border-t border-border/60 my-1" />

          <button
            onClick={() => { setIsOpen(false); onDelete(); }}
            className="w-full px-3.5 py-2.5 text-xs font-bold text-danger-500 hover:bg-danger-500/10 flex items-center gap-2.5 transition-colors"
          >
            <Trash2 size={16} className="text-danger-500" />
            <span>{isAr ? 'حذف الاشتراك' : 'Delete Subscription'}</span>
          </button>
        </div>
      )}
    </div>
  );
};

// ─── FORM SCHEMAS ───────────────────────────────────────────────

const subscriptionSchema = z.object({
  company_id:     z.string().min(1, { message: 'يرجى اختيار العميل.' }),
  employee_id:    z.string().nullable().or(z.literal('')),
  contract_value: z.string().min(1, { message: 'القيمة مطلوبة.' }),
  currency:       z.string(),
  exchange_rate:  z.string().nullable().or(z.literal('')),
  start_date:     z.string().min(1, { message: 'تاريخ البدء مطلوب.' }),
  end_date:       z.string().min(1, { message: 'تاريخ الانتهاء مطلوب.' }),
  status:         z.string(),
  category:       z.string().min(1, { message: 'نوع الاشتراك مطلوب.' }),
  product:        z.string(),
  notes:          z.string().nullable().or(z.literal('')),
});

const renewSchema = z.object({
  contract_value: z.string().min(1, { message: 'القيمة مطلوبة.' }),
  exchange_rate:  z.string().nullable().or(z.literal('')),
  start_date:     z.string().min(1, { message: 'تاريخ البدء مطلوب.' }),
  end_date:       z.string().min(1, { message: 'تاريخ الانتهاء مطلوب.' }),
  category:       z.string().nullable().or(z.literal('')),
  notes:          z.string().nullable().or(z.literal('')),
});

type SubscriptionFormFields = z.infer<typeof subscriptionSchema>;
type RenewFormFields = z.infer<typeof renewSchema>;

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

  // ── CRUD States ──
  const { create, update, remove, renew } = useSubscriptionMutations();
  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [renewId, setRenewId] = useState<number | null>(null);

  const { register, handleSubmit, reset, setValue, watch, formState: { errors } } = useForm<SubscriptionFormFields>({
    resolver: zodResolver(subscriptionSchema),
    defaultValues: {
      company_id: '', employee_id: '', contract_value: '0', currency: 'USD',
      exchange_rate: '1.0', start_date: '', end_date: '', status: 'active',
      category: '', product: 'onocode', notes: '',
    },
  });

  const selectedCurrency = watch('currency');

  const { register: regRenew, handleSubmit: handleRenewSubmit, reset: resetRenew, formState: { errors: renewErrors } } = useForm<RenewFormFields>({
    resolver: zodResolver(renewSchema),
    defaultValues: {
      contract_value: '0', exchange_rate: '1.0', start_date: '', end_date: '',
      category: '', notes: '',
    },
  });

  const companyOptions = [
    { value: '', label: isAr ? '— اختر العميل / الشركة —' : '— Select Client / Company —' },
    ...(companiesData || []).map((c: Company) => ({
      value: c.id.toString(),
      label: c.name,
    })),
  ];

  const categoryOptions = [
    { value: '', label: isAr ? '— اختر نوع الاشتراك —' : '— Select Subscription Type —' },
    { value: 'hosting', label: isAr ? 'استضافة' : 'Hosting' },
    { value: 'domain', label: isAr ? 'دومين' : 'Domain' },
    { value: 'vps', label: 'VPS' },
    { value: 'email', label: isAr ? 'بريد إلكتروني' : 'Email' },
    { value: 'ssl', label: 'SSL' },
    { value: 'other', label: isAr ? 'أخرى' : 'Other' },
  ];

  const getCategoryLabel = (cat?: string | null) => {
    if (!cat) return '—';
    const found = categoryOptions.find(o => o.value === cat);
    return found?.label || cat;
  };

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

  const onSubmit = async (data: SubscriptionFormFields) => {
    const payload: Record<string, unknown> = {
      company_id:     parseInt(data.company_id),
      employee_id:    data.employee_id ? parseInt(data.employee_id) : null,
      contract_value: parseFloat(data.contract_value),
      currency:       data.currency,
      exchange_rate:  data.exchange_rate ? parseFloat(data.exchange_rate) : 1.0,
      start_date:     data.start_date || null,
      end_date:       data.end_date || null,
      status:         data.status,
      category:       data.category || null,
      product:        data.product || 'onocode',
      notes:          data.notes || null,
    };
    try {
      if (editingId) {
        await update.mutateAsync({ id: editingId, payload });
      } else {
        await create.mutateAsync(payload);
      }
      setFormOpen(false);
      setEditingId(null);
      reset();
    } catch (_) {}
  };

  const handleEdit = (contract: Contract) => {
    setEditingId(contract.id);
    setValue('company_id', (contract.company_id ?? contract.company?.id)?.toString() || '');
    setValue('employee_id', (contract.employee_id ?? contract.employee?.id)?.toString() || '');
    setValue('contract_value', contract.contract_value ? contract.contract_value.toString() : '0');
    setValue('currency', contract.currency || 'USD');
    setValue('exchange_rate', contract.exchange_rate ? contract.exchange_rate.toString() : '1.0');
    setValue('start_date', contract.start_date ? contract.start_date.substring(0, 10) : '');
    setValue('end_date', contract.end_date ? contract.end_date.substring(0, 10) : '');
    setValue('status', contract.status || 'active');
    setValue('category', contract.category || '');
    setValue('product', contract.product || 'onocode');
    setValue('notes', contract.notes || '');
    setFormOpen(true);
  };

  const handleDeleteConfirm = async () => {
    if (!deleteId) return;
    try {
      await remove.mutateAsync(deleteId);
      setDeleteId(null);
    } catch (_) {}
  };

  const onRenewSubmit = async (data: RenewFormFields) => {
    if (!renewId) return;
    try {
      await renew.mutateAsync({
        id: renewId,
        payload: {
          contract_value: parseFloat(data.contract_value),
          exchange_rate: data.exchange_rate ? parseFloat(data.exchange_rate) : 1.0,
          start_date: data.start_date,
          end_date: data.end_date,
          category: data.category || null,
          notes: data.notes || null,
        },
      });
      setRenewId(null);
      resetRenew();
    } catch (_) {}
  };

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
    {
      key: 'actions',
      header: isAr ? 'إجراءات' : 'Actions',
      render: (row: Contract) => (
        <SubscriptionActionDropdown
          isAr={isAr}
          onView={() => window.open(`/contracts?show=${row.id}`, '_self')}
          onEdit={() => handleEdit(row)}
          onRenew={() => setRenewId(row.id)}
          onDelete={() => setDeleteId(row.id)}
        />
      ),
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

          {/* Add Button */}
          <button
            onClick={() => { setEditingId(null); reset(); setFormOpen(true); }}
            className="flex items-center gap-2 bg-white text-primary-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transition-all hover:scale-105 active:scale-95 cursor-pointer"
          >
            <Plus size={18} />
            {isAr ? 'إضافة اشتراك' : 'Add Subscription'}
          </button>
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

      {/* ─── CREATE / EDIT MODAL ─────────────────────────── */}
      <Modal
        isOpen={formOpen}
        onClose={() => { setFormOpen(false); setEditingId(null); reset(); }}
        title={editingId ? (isAr ? 'تعديل الاشتراك' : 'Edit Subscription') : (isAr ? 'إضافة اشتراك جديد' : 'Add Subscription')}
        size="lg"
      >
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Company */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'الشركة' : 'Company'} *</label>
              <select {...register('company_id')} className="input-field w-full text-sm">
                {companyOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
              {errors.company_id && <p className="text-xs text-danger-500 mt-1">{errors.company_id.message}</p>}
            </div>

            {/* Employee */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'المسؤول' : 'Employee'}</label>
              <select {...register('employee_id')} className="input-field w-full text-sm">
                <option value="">{isAr ? '— اختر —' : '— Select —'}</option>
                {(employeesData || []).map((e: Employee) => <option key={e.id} value={e.id}>{e.name}</option>)}
              </select>
            </div>

            {/* Category (Subscription Type) */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'نوع الاشتراك' : 'Subscription Type'} *</label>
              <select {...register('category')} className="input-field w-full text-sm">
                {categoryOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
              {errors.category && <p className="text-xs text-danger-500 mt-1">{errors.category.message}</p>}
            </div>

            {/* Product */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'المنتج' : 'Product'}</label>
              <select {...register('product')} className="input-field w-full text-sm">
                <option value="phoenitech">PhoeniTech</option>
                <option value="onocode">OnoCode</option>
                <option value="other">{isAr ? 'أخرى' : 'Other'}</option>
              </select>
            </div>

            {/* Value */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'القيمة' : 'Value'} *</label>
              <Input {...register('contract_value')} type="number" step="0.01" />
              {errors.contract_value && <p className="text-xs text-danger-500 mt-1">{errors.contract_value.message}</p>}
            </div>

            {/* Currency */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'العملة' : 'Currency'}</label>
              <select {...register('currency')} className="input-field w-full text-sm">
                <option value="USD">USD</option>
                <option value="SYP">SYP</option>
                <option value="TRY">TRY</option>
                <option value="EUR">EUR</option>
              </select>
            </div>

            {selectedCurrency !== 'USD' && (
              <div>
                <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'سعر الصرف' : 'Exchange Rate'}</label>
                <Input {...register('exchange_rate')} type="number" step="0.0001" />
              </div>
            )}

            {/* Start Date */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'تاريخ البدء' : 'Start Date'} *</label>
              <Input {...register('start_date')} type="date" />
              {errors.start_date && <p className="text-xs text-danger-500 mt-1">{errors.start_date.message}</p>}
            </div>

            {/* End Date */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'تاريخ الانتهاء' : 'End Date'} *</label>
              <Input {...register('end_date')} type="date" />
              {errors.end_date && <p className="text-xs text-danger-500 mt-1">{errors.end_date.message}</p>}
            </div>

            {/* Status */}
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'الحالة' : 'Status'}</label>
              <select {...register('status')} className="input-field w-full text-sm">
                <option value="active">{isAr ? 'نشط' : 'Active'}</option>
                <option value="draft">{isAr ? 'مسودة' : 'Draft'}</option>
                <option value="signed">{isAr ? 'موقع' : 'Signed'}</option>
                <option value="completed">{isAr ? 'مكتمل' : 'Completed'}</option>
                <option value="cancelled">{isAr ? 'ملغي' : 'Cancelled'}</option>
                <option value="suspended">{isAr ? 'معلق' : 'Suspended'}</option>
              </select>
            </div>
          </div>

          {/* Notes */}
          <div>
            <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'ملاحظات' : 'Notes'}</label>
            <Textarea {...register('notes')} rows={3} placeholder={isAr ? 'اسم الخدمة | الدومين / الإيميل...' : 'Service name | Domain / Email...'} />
          </div>

          <div className="flex gap-3 pt-2">
            <button
              type="submit"
              disabled={create.isPending || update.isPending}
              className="btn-primary flex-1 py-2.5 text-sm font-bold rounded-lg disabled:opacity-50"
            >
              {(create.isPending || update.isPending)
                ? (isAr ? 'جاري الحفظ...' : 'Saving...')
                : editingId
                  ? (isAr ? 'تحديث الاشتراك' : 'Update Subscription')
                  : (isAr ? 'إضافة الاشتراك' : 'Add Subscription')
              }
            </button>
            <button
              type="button"
              onClick={() => { setFormOpen(false); setEditingId(null); reset(); }}
              className="px-6 py-2.5 text-sm font-bold rounded-lg border border-border text-text-muted hover:bg-surface-lighter transition-colors"
            >
              {isAr ? 'إلغاء' : 'Cancel'}
            </button>
          </div>
        </form>
      </Modal>

      {/* ─── DELETE CONFIRMATION MODAL ────────────────────── */}
      <Modal
        isOpen={deleteId !== null}
        onClose={() => setDeleteId(null)}
        title={isAr ? 'تأكيد الحذف' : 'Confirm Deletion'}
        size="sm"
      >
        <div className="text-center space-y-4">
          <div className="w-16 h-16 mx-auto rounded-full bg-danger-bg flex items-center justify-center">
            <Trash2 size={28} className="text-danger-500" />
          </div>
          <p className="text-sm text-text-muted">
            {isAr ? 'هل أنت متأكد من حذف هذا الاشتراك؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to delete this subscription? This action cannot be undone.'}
          </p>
          <div className="flex gap-3">
            <button
              onClick={handleDeleteConfirm}
              disabled={remove.isPending}
              className="flex-1 py-2.5 text-sm font-bold rounded-lg bg-danger-500 text-white hover:bg-danger-600 transition-colors disabled:opacity-50"
            >
              {remove.isPending ? (isAr ? 'جاري الحذف...' : 'Deleting...') : (isAr ? 'حذف' : 'Delete')}
            </button>
            <button
              onClick={() => setDeleteId(null)}
              className="flex-1 py-2.5 text-sm font-bold rounded-lg border border-border text-text-muted hover:bg-surface-lighter transition-colors"
            >
              {isAr ? 'إلغاء' : 'Cancel'}
            </button>
          </div>
        </div>
      </Modal>

      {/* ─── RENEW MODAL ─────────────────────────────────── */}
      <Modal
        isOpen={renewId !== null}
        onClose={() => { setRenewId(null); resetRenew(); }}
        title={isAr ? 'تجديد الاشتراك' : 'Renew Subscription'}
        size="md"
      >
        <form onSubmit={handleRenewSubmit(onRenewSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'القيمة الجديدة' : 'New Value'} *</label>
              <Input {...regRenew('contract_value')} type="number" step="0.01" />
              {renewErrors.contract_value && <p className="text-xs text-danger-500 mt-1">{renewErrors.contract_value.message}</p>}
            </div>
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'سعر الصرف' : 'Exchange Rate'}</label>
              <Input {...regRenew('exchange_rate')} type="number" step="0.0001" />
            </div>
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'تاريخ البدء' : 'Start Date'} *</label>
              <Input {...regRenew('start_date')} type="date" />
              {renewErrors.start_date && <p className="text-xs text-danger-500 mt-1">{renewErrors.start_date.message}</p>}
            </div>
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'تاريخ الانتهاء' : 'End Date'} *</label>
              <Input {...regRenew('end_date')} type="date" />
              {renewErrors.end_date && <p className="text-xs text-danger-500 mt-1">{renewErrors.end_date.message}</p>}
            </div>
            <div>
              <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'نوع الاشتراك' : 'Type'}</label>
              <select {...regRenew('category')} className="input-field w-full text-sm">
                {categoryOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
            </div>
          </div>
          <div>
            <label className="block text-xs font-semibold text-text-muted mb-1">{isAr ? 'ملاحظات' : 'Notes'}</label>
            <Textarea {...regRenew('notes')} rows={2} />
          </div>
          <div className="flex gap-3 pt-2">
            <button
              type="submit"
              disabled={renew.isPending}
              className="btn-primary flex-1 py-2.5 text-sm font-bold rounded-lg disabled:opacity-50"
            >
              {renew.isPending ? (isAr ? 'جاري التجديد...' : 'Renewing...') : (isAr ? 'تجديد' : 'Renew')}
            </button>
            <button
              type="button"
              onClick={() => { setRenewId(null); resetRenew(); }}
              className="px-6 py-2.5 text-sm font-bold rounded-lg border border-border text-text-muted hover:bg-surface-lighter transition-colors"
            >
              {isAr ? 'إلغاء' : 'Cancel'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
};
