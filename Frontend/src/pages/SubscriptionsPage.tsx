import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { subscriptionApi } from '@/api/subscriptions';
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
import type { ServerSubscription, ServerSubscriptionDashboard } from '@/types';
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
  Server,
  Globe,
  Mail,
  ShieldCheck,
  HardDrive,
  Copy,
  Check,
  ExternalLink,
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

// ─── TYPE BADGE ─────────────────────────────────────────────────

const SUBSCRIPTION_TYPES = [
  { value: 'all', labelAr: 'الكل', labelEn: 'All' },
  { value: 'vps', labelAr: 'VPS سيرفر', labelEn: 'VPS Server', icon: Server, color: 'text-purple-400 bg-purple-500/10 border-purple-500/20' },
  { value: 'hosting', labelAr: 'استضافة', labelEn: 'Hosting', icon: HardDrive, color: 'text-blue-400 bg-blue-500/10 border-blue-500/20' },
  { value: 'domain', labelAr: 'دومين', labelEn: 'Domain', icon: Globe, color: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20' },
  { value: 'email', labelAr: 'بريد إلكتروني', labelEn: 'Email', icon: Mail, color: 'text-amber-400 bg-amber-500/10 border-amber-500/20' },
  { value: 'ssl', labelAr: 'شهادة SSL', labelEn: 'SSL Certificate', icon: ShieldCheck, color: 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20' },
];

const TypeBadge: React.FC<{ type: string; isAr: boolean }> = ({ type, isAr }) => {
  const conf = SUBSCRIPTION_TYPES.find((t) => t.value === type) || {
    labelAr: type,
    labelEn: type,
    color: 'text-text-muted bg-surface-lighter border-border',
    icon: HardDrive,
  };
  const Icon = conf.icon || HardDrive;

  return (
    <span className={cn('inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border', conf.color)}>
      <Icon size={13} className="shrink-0" />
      <span>{isAr ? conf.labelAr : conf.labelEn}</span>
    </span>
  );
};

// ─── STATUS BADGE ───────────────────────────────────────────────

const SubscriptionStatusBadge: React.FC<{ sub: ServerSubscription; isAr: boolean }> = ({ sub, isAr }) => {
  const days = getDaysRemaining(sub.end_date);

  if (sub.status === 'cancelled') {
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

  if (days !== null && days <= 30) {
    return (
      <span className="badge bg-warning-bg text-warning-text border-warning-text/15">
        {isAr ? 'ينتهي قريباً' : 'Expiring Soon'}
      </span>
    );
  }

  return (
    <span className="badge bg-success-bg text-success-text border-success-text/15">
      {isAr ? 'نشط' : 'Active'}
    </span>
  );
};

// ─── STAT CARD ──────────────────────────────────────────────────

const StatCard: React.FC<{
  label: string;
  value: string | number;
  subtitle?: string;
  icon: React.ReactNode;
  accentColor?: string;
}> = ({ label, value, subtitle, icon, accentColor }) => (
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

const subscriptionFormSchema = z.object({
  name:         z.string().min(2, { message: 'اسم الخدمة مطلوب.' }),
  company_name: z.string().optional(),
  type:         z.enum(['vps', 'hosting', 'domain', 'email', 'ssl']),
  domain:       z.string().optional(),
  provider:     z.string().optional(),
  cost:         z.string().optional(),
  currency:     z.string().default('USD'),
  start_date:   z.string().optional(),
  end_date:     z.string().min(1, { message: 'تاريخ الانتهاء مطلوب.' }),
  status:       z.enum(['active', 'expiring_soon', 'expired', 'cancelled']).default('active'),
  notes:        z.string().optional(),
});

type SubscriptionFormValues = z.input<typeof subscriptionFormSchema>;

const renewSchema = z.object({
  end_date: z.string().min(1, { message: 'تاريخ الانتهاء مطلوب.' }),
  cost:     z.string().optional(),
  notes:    z.string().nullable().or(z.literal('')),
});

type RenewFormValues = z.infer<typeof renewSchema>;

// ─── MAIN PAGE COMPONENT ───────────────────────────────────────

export const SubscriptionsPage: React.FC = () => {
  const { t } = useTranslation();
  const { language } = useUiStore();
  const isAr = language === 'ar';

  // ── Filter States ──
  const [typeFilter, setTypeFilter] = useState<string>('all');
  const [companyFilter, setCompanyFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const perPage = 25;

  // ── CRUD States ──
  const { create, update, remove, renew } = useSubscriptionMutations();
  const [formOpen, setFormOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<ServerSubscription | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [viewItem, setViewItem] = useState<ServerSubscription | null>(null);
  const [renewItem, setRenewItem] = useState<ServerSubscription | null>(null);
  const [copiedDomain, setCopiedDomain] = useState<string | null>(null);

  // ── Forms ──
  const {
    register,
    handleSubmit,
    reset,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<SubscriptionFormValues>({
    resolver: zodResolver(subscriptionFormSchema),
    defaultValues: {
      name: '',
      company_name: '',
      type: 'hosting',
      domain: '',
      provider: 'GoDaddy',
      cost: '0',
      currency: 'USD',
      start_date: '',
      end_date: '',
      status: 'active',
      notes: '',
    },
  });

  const {
    register: registerRenew,
    handleSubmit: handleRenewSubmit,
    reset: resetRenew,
    setValue: setRenewValue,
    formState: { errors: renewErrors, isSubmitting: isRenewing },
  } = useForm<RenewFormValues>({
    resolver: zodResolver(renewSchema),
  });

  // ── Queries ──
  const { data: dashboardRes, isLoading: isDashboardLoading, refetch: refetchDashboard } = useQuery({
    queryKey: ['subscriptions-dashboard'],
    queryFn: () => subscriptionApi.dashboard(),
    staleTime: 30000,
  });

  const dashboardData = dashboardRes?.data?.data;

  const { data: listRes, isLoading: isListLoading, refetch: refetchList } = useQuery({
    queryKey: ['subscriptions-list', { type: typeFilter, company_name: companyFilter, status: statusFilter, search, page }],
    queryFn: () =>
      subscriptionApi.list({
        type: typeFilter !== 'all' ? typeFilter : undefined,
        company_name: companyFilter !== 'all' ? companyFilter : undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        search: search || undefined,
        page,
        per_page: perPage,
      }),
    staleTime: 10000,
  });

  const subscriptions = listRes?.data?.data ?? [];
  const meta = listRes?.data?.meta;

  // ── Handlers ──
  const handleSearch = useCallback(() => {
    setSearch(searchInput);
    setPage(1);
  }, [searchInput]);

  const resetFilters = useCallback(() => {
    setTypeFilter('all');
    setCompanyFilter('all');
    setStatusFilter('all');
    setSearchInput('');
    setSearch('');
    setPage(1);
  }, []);

  const openCreateForm = () => {
    setEditingItem(null);
    reset({
      name: '',
      company_name: '',
      type: 'hosting',
      domain: '',
      provider: 'GoDaddy',
      cost: '0',
      currency: 'USD',
      start_date: new Date().toISOString().split('T')[0],
      end_date: '',
      status: 'active',
      notes: '',
    });
    setFormOpen(true);
  };

  const openEditForm = (item: ServerSubscription) => {
    setEditingItem(item);
    setValue('name', item.name);
    setValue('company_name', item.company_name || '');
    setValue('type', item.type);
    setValue('domain', item.domain || '');
    setValue('provider', item.provider || 'GoDaddy');
    setValue('cost', String(item.cost || '0'));
    setValue('currency', item.currency || 'USD');
    setValue('start_date', item.start_date || '');
    setValue('end_date', item.end_date || '');
    setValue('status', item.status || 'active');
    setValue('notes', item.notes || '');
    setFormOpen(true);
  };

  const onSubmitForm = async (data: SubscriptionFormValues) => {
    const payload = {
      name: data.name,
      company_name: data.company_name || null,
      type: data.type,
      domain: data.domain || null,
      provider: data.provider || 'GoDaddy',
      cost: parseFloat(data.cost || '0') || 0,
      currency: data.currency,
      start_date: data.start_date || null,
      end_date: data.end_date,
      status: data.status,
      notes: data.notes || null,
    };

    if (editingItem) {
      await update.mutateAsync({ id: editingItem.id, payload });
    } else {
      await create.mutateAsync(payload);
    }
    setFormOpen(false);
    setEditingItem(null);
    reset();
    refetchList();
    refetchDashboard();
  };

  const openRenewModal = (item: ServerSubscription) => {
    setRenewItem(item);
    // Suggest 1 year ahead
    const curEnd = item.end_date ? new Date(item.end_date) : new Date();
    const nextYear = new Date(curEnd);
    nextYear.setFullYear(nextYear.getFullYear() + 1);
    const nextYearStr = nextYear.toISOString().split('T')[0];

    resetRenew({
      end_date: nextYearStr,
      cost: String(item.cost || '0'),
      notes: item.notes || '',
    });
  };

  const onRenewSubmit = async (data: RenewFormValues) => {
    if (!renewItem) return;
    await renew.mutateAsync({
      id: renewItem.id,
      payload: {
        end_date: data.end_date,
        cost: parseFloat(data.cost || '0') || 0,
        notes: data.notes || null,
      },
    });
    setRenewItem(null);
    resetRenew();
    refetchList();
    refetchDashboard();
  };

  const confirmDelete = async () => {
    if (!deleteId) return;
    await remove.mutateAsync(deleteId);
    setDeleteId(null);
    refetchList();
    refetchDashboard();
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedDomain(text);
    setTimeout(() => setCopiedDomain(null), 2000);
  };

  // ── Table Columns ──
  const columns = useMemo(() => [
    {
      key: 'name',
      header: isAr ? 'الخدمة / الحساب' : 'Service / Account',
      render: (row: ServerSubscription) => (
        <div className="flex flex-col gap-0.5">
          <span className="font-semibold text-text text-sm hover:text-primary-400 transition-colors cursor-pointer" onClick={() => setViewItem(row)}>
            {row.name}
          </span>
          {row.company_name && (
            <span className="text-xs text-text-muted flex items-center gap-1">
              <Building2 size={11} />
              {row.company_name}
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'type',
      header: isAr ? 'النوع' : 'Type',
      render: (row: ServerSubscription) => <TypeBadge type={row.type} isAr={isAr} />,
    },
    {
      key: 'domain',
      header: isAr ? 'الدومين / الإيميل' : 'Domain / Email',
      render: (row: ServerSubscription) => {
        if (!row.domain) return <span className="text-text-muted text-xs">—</span>;
        return (
          <div className="flex items-center gap-1.5 group">
            <span className="text-xs font-mono text-text bg-surface-lighter px-2 py-0.5 rounded border border-border/60">
              {row.domain}
            </span>
            <button
              onClick={() => copyToClipboard(row.domain!)}
              className="p-1 rounded text-text-muted hover:text-text hover:bg-surface-lighter transition-all opacity-0 group-hover:opacity-100"
              title={isAr ? 'نسخ' : 'Copy'}
            >
              {copiedDomain === row.domain ? <Check size={12} className="text-emerald-400" /> : <Copy size={12} />}
            </button>
          </div>
        );
      },
    },
    {
      key: 'provider',
      header: isAr ? 'المزود' : 'Provider',
      render: (row: ServerSubscription) => (
        <span className="text-xs text-text-muted font-medium">
          {row.provider || '—'}
        </span>
      ),
    },
    {
      key: 'cost',
      header: isAr ? 'التكلفة' : 'Cost',
      render: (row: ServerSubscription) => (
        <span className="font-semibold text-text text-sm">
          {row.cost > 0 ? formatCurrency(row.cost, row.currency || 'USD') : '—'}
        </span>
      ),
    },
    {
      key: 'end_date',
      header: isAr ? 'تاريخ الانتهاء' : 'End Date',
      render: (row: ServerSubscription) => (
        <div className="flex flex-col gap-0.5">
          <span className="text-xs font-medium text-text">
            {formatDate(row.end_date)}
          </span>
          {row.start_date && (
            <span className="text-[11px] text-text-muted">
              من {formatDate(row.start_date)}
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'days_remaining',
      header: isAr ? 'المتبقي' : 'Remaining',
      render: (row: ServerSubscription) => {
        const days = getDaysRemaining(row.end_date);
        if (days === null) return <span className="text-text-muted text-xs">—</span>;

        if (days < 0) {
          return (
            <span className="inline-flex items-center gap-1 text-xs font-bold text-danger-text bg-danger-bg px-2 py-0.5 rounded-full border border-danger-text/15">
              <AlertTriangle size={11} />
              {isAr ? `منتهي منذ ${Math.abs(days)} يوم` : `${Math.abs(days)}d expired`}
            </span>
          );
        }

        if (days <= 30) {
          return (
            <span className="inline-flex items-center gap-1 text-xs font-bold text-warning-text bg-warning-bg px-2 py-0.5 rounded-full border border-warning-text/15">
              <Clock size={11} />
              {isAr ? `${days} يوم متبقي` : `${days}d left`}
            </span>
          );
        }

        return (
          <span className="inline-flex items-center gap-1 text-xs font-medium text-success-text bg-success-bg px-2 py-0.5 rounded-full border border-success-text/15">
            {isAr ? `${days} يوم` : `${days}d`}
          </span>
        );
      },
    },
    {
      key: 'status',
      header: isAr ? 'الحالة' : 'Status',
      render: (row: ServerSubscription) => <SubscriptionStatusBadge sub={row} isAr={isAr} />,
    },
    {
      key: 'actions',
      header: isAr ? 'إجراءات' : 'Actions',
      render: (row: ServerSubscription) => (
        <SubscriptionActionDropdown
          isAr={isAr}
          onView={() => setViewItem(row)}
          onEdit={() => openEditForm(row)}
          onRenew={() => openRenewModal(row)}
          onDelete={() => setDeleteId(row.id)}
        />
      ),
    },
  ], [isAr, copiedDomain]);

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      {/* ─── HEADER ─────────────────────────────────────────── */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 via-primary-700 to-indigo-800 p-6 md:p-8 text-white shadow-xl">
        <div className="absolute inset-0 bg-pattern opacity-10 pointer-events-none" />
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div className="space-y-1.5">
            <div className="flex items-center gap-3">
              <div className="p-2.5 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20">
                <Server size={24} className="text-white" />
              </div>
              <h1 className="text-2xl md:text-3xl font-bold tracking-tight">
                {isAr ? 'لوحة إدارة الاشتراكات السيرفرية' : 'Server Subscriptions Management'}
              </h1>
            </div>
            <p className="text-white/80 text-sm max-w-xl">
              {isAr
                ? 'إدارة ومتابعة تجديدات السيرفرات، الاستضافات، الدومينات، والإيميلات بشكل منفصل تماماً'
                : 'Manage and track VPS servers, hosting, domains, and business emails infrastructure renewals'}
            </p>
          </div>

          <div className="flex items-center gap-2.5 self-start md:self-auto">
            <button
              onClick={() => { refetchDashboard(); refetchList(); }}
              className="p-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-all active:scale-95 border border-white/10"
              title={isAr ? 'تحديث' : 'Refresh'}
            >
              <RotateCw size={18} />
            </button>

            <button
              onClick={openCreateForm}
              className="flex items-center gap-2 bg-white text-primary-700 hover:bg-white/95 px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-black/10 transition-all hover:shadow-xl active:scale-95"
            >
              <Plus size={18} />
              <span>{isAr ? 'إضافة اشتراك جديد' : 'Add Subscription'}</span>
            </button>
          </div>
        </div>

        {/* ── Type Filter Tabs ── */}
        <div className="relative z-10 mt-6 pt-4 border-t border-white/15 flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
          {SUBSCRIPTION_TYPES.map((t) => {
            const count = t.value === 'all'
              ? dashboardData?.total
              : dashboardData?.by_type?.[t.value as keyof typeof dashboardData.by_type];
            const isSelected = typeFilter === t.value;

            return (
              <button
                key={t.value}
                onClick={() => { setTypeFilter(t.value); setPage(1); }}
                className={cn(
                  'flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap',
                  isSelected
                    ? 'bg-white text-primary-700 shadow-md scale-105'
                    : 'bg-white/10 hover:bg-white/20 text-white/90 border border-white/10'
                )}
              >
                <span>{isAr ? t.labelAr : t.labelEn}</span>
                {count !== undefined && (
                  <span className={cn('px-1.5 py-0.5 rounded-md text-[11px] font-mono', isSelected ? 'bg-primary-100 text-primary-700' : 'bg-black/20 text-white')}>
                    {count}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </div>

      {/* ─── KPI CARDS ──────────────────────────────────────── */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3.5">
        <StatCard
          label={isAr ? 'إجمالي الاشتراكات' : 'Total Subscriptions'}
          value={isDashboardLoading ? '...' : dashboardData?.total ?? 0}
          icon={<Server size={20} />}
          accentColor="border-primary-500"
        />
        <StatCard
          label={isAr ? 'الاشتراكات النشطة' : 'Active'}
          value={isDashboardLoading ? '...' : dashboardData?.active ?? 0}
          icon={<Activity size={20} className="text-emerald-500" />}
          accentColor="border-emerald-500"
        />
        <StatCard
          label={isAr ? 'تنتهي قريباً (30 يوم)' : 'Expiring Soon (30d)'}
          value={isDashboardLoading ? '...' : dashboardData?.expiring_soon ?? 0}
          icon={<AlertTriangle size={20} className="text-amber-500" />}
          accentColor="border-amber-500"
        />
        <StatCard
          label={isAr ? 'منتهية الصلاحية' : 'Expired'}
          value={isDashboardLoading ? '...' : dashboardData?.expired ?? 0}
          icon={<XCircle size={20} className="text-rose-500" />}
          accentColor="border-rose-500"
        />
        <StatCard
          label={isAr ? 'التكلفة الإجمالية' : 'Total Cost'}
          value={isDashboardLoading ? '...' : formatCurrency(dashboardData?.total_cost ?? 0, 'USD')}
          icon={<DollarSign size={20} className="text-indigo-500" />}
          accentColor="border-indigo-500"
        />
      </div>

      {/* ─── FILTERS ────────────────────────────────────────── */}
      <Card className="p-4">
        <div className="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
          <div className="flex-1 flex flex-col sm:flex-row gap-2.5">
            {/* Search */}
            <div className="relative flex-1">
              <Search size={16} className="absolute start-3 top-1/2 -translate-y-1/2 text-text-muted" />
              <input
                type="text"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                placeholder={isAr ? 'بحث بالاسم، الدومين، الشركة، أو المزود...' : 'Search by name, domain, company, provider...'}
                className="input ps-9 pe-3 py-2 text-xs w-full"
              />
            </div>

            {/* Company Filter */}
            {dashboardData?.companies && dashboardData.companies.length > 0 && (
              <select
                value={companyFilter}
                onChange={(e) => { setCompanyFilter(e.target.value); setPage(1); }}
                className="input py-2 text-xs min-w-[160px]"
              >
                <option value="all">{isAr ? 'جميع الشركات / المالكين' : 'All Companies'}</option>
                {dashboardData.companies.map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>
            )}

            {/* Status Filter */}
            <select
              value={statusFilter}
              onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
              className="input py-2 text-xs min-w-[140px]"
            >
              <option value="all">{isAr ? 'جميع الحالات' : 'All Statuses'}</option>
              <option value="active">{isAr ? 'نشط' : 'Active'}</option>
              <option value="expiring_soon">{isAr ? 'ينتهي قريباً' : 'Expiring Soon'}</option>
              <option value="expired">{isAr ? 'منتهي' : 'Expired'}</option>
              <option value="cancelled">{isAr ? 'ملغي' : 'Cancelled'}</option>
            </select>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={handleSearch}
              className="btn-primary px-4 py-2 text-xs flex items-center gap-1.5 shrink-0"
            >
              <Search size={14} />
              <span>{isAr ? 'بحث' : 'Search'}</span>
            </button>

            {(searchInput || search || typeFilter !== 'all' || companyFilter !== 'all' || statusFilter !== 'all') && (
              <button
                onClick={resetFilters}
                className="btn-outline px-3 py-2 text-xs shrink-0"
                title={isAr ? 'إعادة تعيين' : 'Reset'}
              >
                <RefreshCcw size={14} />
              </button>
            )}
          </div>
        </div>
      </Card>

      {/* ─── TABLE ──────────────────────────────────────────── */}
      <Card className="overflow-hidden">
        {isListLoading ? (
          <div className="p-12 flex justify-center items-center">
            <Spinner size="lg" />
          </div>
        ) : (
          <>
            <Table
              data={subscriptions}
              columns={columns}
            />

            {/* Pagination */}
            {meta && meta.last_page > 1 && (
              <div className="p-4 border-t border-border flex items-center justify-between text-xs text-text-muted">
                <span>
                  {isAr
                    ? `عرض صفحة ${meta.current_page} من ${meta.last_page} (إجمالي ${meta.total})`
                    : `Page ${meta.current_page} of ${meta.last_page} (total ${meta.total})`}
                </span>
                <div className="flex items-center gap-1.5">
                  <button
                    disabled={meta.current_page <= 1}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    className="p-1.5 rounded-lg border border-border hover:bg-surface-lighter disabled:opacity-40 disabled:pointer-events-none"
                  >
                    <ChevronRight size={16} />
                  </button>
                  <button
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => setPage((p) => p + 1)}
                    className="p-1.5 rounded-lg border border-border hover:bg-surface-lighter disabled:opacity-40 disabled:pointer-events-none"
                  >
                    <ChevronLeft size={16} />
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </Card>

      {/* ─── CREATE / EDIT MODAL ─────────────────────────────── */}
      <Modal
        isOpen={formOpen}
        onClose={() => { setFormOpen(false); setEditingItem(null); reset(); }}
        title={editingItem ? (isAr ? 'تعديل اشتراك سيرفر' : 'Edit Server Subscription') : (isAr ? 'إضافة اشتراك سيرفر جديد' : 'New Server Subscription')}
        size="lg"
      >
        <form onSubmit={handleSubmit(onSubmitForm)} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Service Name */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                {isAr ? 'اسم الخدمة / الحساب *' : 'Service / Account Name *'}
              </label>
              <input
                type="text"
                {...register('name')}
                placeholder={isAr ? 'مثال: Managed Linux VPS L أو adnan@...' : 'e.g. Managed Linux VPS L'}
                className={cn('input w-full text-xs', errors.name && 'border-danger-text')}
              />
              {errors.name && <p className="text-[11px] text-danger-text mt-1">{errors.name.message}</p>}
            </div>

            {/* Company / Owner Name */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                {isAr ? 'الجهة / الشركة / المالك' : 'Company / Owner'}
              </label>
              <input
                type="text"
                {...register('company_name')}
                placeholder={isAr ? 'مثال: BW Business World' : 'e.g. BW Business World'}
                className="input w-full text-xs"
              />
            </div>

            {/* Type */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                {isAr ? 'نوع الاشتراك *' : 'Subscription Type *'}
              </label>
              <select {...register('type')} className="input w-full text-xs">
                <option value="vps">VPS سيرفر</option>
                <option value="hosting">استضافة (Web Hosting)</option>
                <option value="domain">دومين (Domain Registration)</option>
                <option value="email">بريد إلكتروني (Email)</option>
                <option value="ssl">شهادة SSL</option>
              </select>
            </div>

            {/* Domain / Email */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                {isAr ? 'الدومين أو الإيميل' : 'Domain or Email'}
              </label>
              <input
                type="text"
                {...register('domain')}
                placeholder="example.com or user@example.com"
                className="input w-full text-xs"
              />
            </div>

            {/* Provider */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                {isAr ? 'مزود الخدمة' : 'Provider'}
              </label>
              <input
                type="text"
                {...register('provider')}
                placeholder="GoDaddy, Contabo, Hetzner, etc."
                className="input w-full text-xs"
              />
            </div>

            {/* Cost & Currency */}
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-xs font-semibold text-text mb-1.5">
                  {isAr ? 'التكلفة السنوية' : 'Annual Cost'}
                </label>
                <input
                  type="number"
                  step="0.01"
                  {...register('cost')}
                  placeholder="0.00"
                  className="input w-full text-xs"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-text mb-1.5">
                  {isAr ? 'العملة' : 'Currency'}
                </label>
                <select {...register('currency')} className="input w-full text-xs">
                  <option value="USD">USD ($)</option>
                  <option value="SAR">SAR (ر.س)</option>
                  <option value="EUR">EUR (€)</option>
                  <option value="AED">AED (د.إ)</option>
                  <option value="SYP">SYP (ل.س)</option>
                </select>
              </div>
            </div>

            {/* Start Date */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                {isAr ? 'تاريخ البدء' : 'Start Date'}
              </label>
              <input
                type="date"
                {...register('start_date')}
                className="input w-full text-xs"
              />
            </div>

            {/* End Date */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                {isAr ? 'تاريخ الانتهاء *' : 'End Date *'}
              </label>
              <input
                type="date"
                {...register('end_date')}
                className={cn('input w-full text-xs', errors.end_date && 'border-danger-text')}
              />
              {errors.end_date && <p className="text-[11px] text-danger-text mt-1">{errors.end_date.message}</p>}
            </div>

            {/* Status */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                {isAr ? 'الحالة' : 'Status'}
              </label>
              <select {...register('status')} className="input w-full text-xs">
                <option value="active">{isAr ? 'نشط' : 'Active'}</option>
                <option value="expiring_soon">{isAr ? 'ينتهي قريباً' : 'Expiring Soon'}</option>
                <option value="expired">{isAr ? 'منتهي' : 'Expired'}</option>
                <option value="cancelled">{isAr ? 'ملغي' : 'Cancelled'}</option>
              </select>
            </div>
          </div>

          {/* Notes */}
          <div>
            <label className="block text-xs font-semibold text-text mb-1.5">
              {isAr ? 'ملاحظات' : 'Notes'}
            </label>
            <textarea
              {...register('notes')}
              rows={2}
              placeholder={isAr ? 'أي تفاصيل أو ملاحظات إضافية...' : 'Additional notes...'}
              className="input w-full text-xs"
            />
          </div>

          {/* Buttons */}
          <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-border">
            <button
              type="button"
              onClick={() => { setFormOpen(false); setEditingItem(null); reset(); }}
              className="btn-outline px-4 py-2 text-xs"
            >
              {isAr ? 'إلغاء' : 'Cancel'}
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="btn-primary px-5 py-2 text-xs flex items-center gap-1.5"
            >
              {isSubmitting && <Spinner size="sm" />}
              <span>{editingItem ? (isAr ? 'حفظ التعديلات' : 'Save Changes') : (isAr ? 'إضافة الاشتراك' : 'Create Subscription')}</span>
            </button>
          </div>
        </form>
      </Modal>

      {/* ─── RENEW MODAL ─────────────────────────────────────── */}
      <Modal
        isOpen={!!renewItem}
        onClose={() => { setRenewItem(null); resetRenew(); }}
        title={isAr ? `تجديد الاشتراك: ${renewItem?.name}` : `Renew Subscription: ${renewItem?.name}`}
        size="md"
      >
        <form onSubmit={handleRenewSubmit(onRenewSubmit)} className="space-y-4">
          <div className="bg-surface-lighter p-3 rounded-xl border border-border text-xs space-y-1">
            <p><span className="text-text-muted">{isAr ? 'الخدمة:' : 'Service:'}</span> <strong className="text-text">{renewItem?.name}</strong></p>
            {renewItem?.domain && <p><span className="text-text-muted">{isAr ? 'الدومين/الإيميل:' : 'Domain/Email:'}</span> <strong className="text-text font-mono">{renewItem?.domain}</strong></p>}
            <p><span className="text-text-muted">{isAr ? 'تاريخ الانتهاء الحالي:' : 'Current End Date:'}</span> <strong className="text-warning-text">{formatDate(renewItem?.end_date || '')}</strong></p>
          </div>

          <div>
            <label className="block text-xs font-semibold text-text mb-1.5">
              {isAr ? 'تاريخ الانتهاء الجديد *' : 'New End Date *'}
            </label>
            <input
              type="date"
              {...registerRenew('end_date')}
              className={cn('input w-full text-xs', renewErrors.end_date && 'border-danger-text')}
            />
            {renewErrors.end_date && <p className="text-[11px] text-danger-text mt-1">{renewErrors.end_date.message}</p>}
          </div>

          <div>
            <label className="block text-xs font-semibold text-text mb-1.5">
              {isAr ? 'تكلفة التجديد' : 'Renewal Cost'}
            </label>
            <input
              type="number"
              step="0.01"
              {...registerRenew('cost')}
              className="input w-full text-xs"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-text mb-1.5">
              {isAr ? 'ملاحظات التجديد' : 'Renewal Notes'}
            </label>
            <textarea
              {...registerRenew('notes')}
              rows={2}
              placeholder={isAr ? 'رقم فاتورة التجديد أو ملاحظات...' : 'Invoice number or renewal notes...'}
              className="input w-full text-xs"
            />
          </div>

          <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-border">
            <button
              type="button"
              onClick={() => { setRenewItem(null); resetRenew(); }}
              className="btn-outline px-4 py-2 text-xs"
            >
              {isAr ? 'إلغاء' : 'Cancel'}
            </button>
            <button
              type="submit"
              disabled={isRenewing}
              className="btn-primary px-5 py-2 text-xs flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700"
            >
              {isRenewing && <Spinner size="sm" />}
              <span>{isAr ? 'تأكيد التجديد' : 'Confirm Renewal'}</span>
            </button>
          </div>
        </form>
      </Modal>

      {/* ─── DELETE CONFIRMATION MODAL ───────────────────────── */}
      <Modal
        isOpen={!!deleteId}
        onClose={() => setDeleteId(null)}
        title={isAr ? 'تأكيد حذف الاشتراك' : 'Confirm Delete Subscription'}
        size="sm"
      >
        <div className="space-y-4">
          <p className="text-sm text-text">
            {isAr
              ? 'هل أنت متأكد من رغبتك في حذف هذا الاشتراك السيرفري؟ لا يمكن التراجع عن هذا الإجراء.'
              : 'Are you sure you want to delete this server subscription? This action cannot be undone.'}
          </p>

          <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-border">
            <button
              type="button"
              onClick={() => setDeleteId(null)}
              className="btn-outline px-4 py-2 text-xs"
            >
              {isAr ? 'إلغاء' : 'Cancel'}
            </button>
            <button
              type="button"
              onClick={confirmDelete}
              className="btn-primary px-4 py-2 text-xs bg-danger-600 hover:bg-danger-700 text-white"
            >
              {isAr ? 'نعم، حذف' : 'Yes, Delete'}
            </button>
          </div>
        </div>
      </Modal>

      {/* ─── VIEW DETAILS MODAL ──────────────────────────────── */}
      <Modal
        isOpen={!!viewItem}
        onClose={() => setViewItem(null)}
        title={isAr ? 'تفاصيل الاشتراك السيرفري' : 'Server Subscription Details'}
        size="md"
      >
        {viewItem && (
          <div className="space-y-4 text-xs">
            <div className="p-4 rounded-xl bg-surface-lighter border border-border space-y-3">
              <div className="flex items-center justify-between">
                <span className="font-bold text-base text-text">{viewItem.name}</span>
                <TypeBadge type={viewItem.type} isAr={isAr} />
              </div>

              {viewItem.company_name && (
                <div className="flex items-center gap-1.5 text-text-muted">
                  <Building2 size={14} />
                  <span>{isAr ? 'الشركة / المالك:' : 'Owner:'}</span>
                  <strong className="text-text">{viewItem.company_name}</strong>
                </div>
              )}

              {viewItem.domain && (
                <div className="flex items-center justify-between bg-surface p-2.5 rounded-lg border border-border/80">
                  <span className="text-text-muted">{isAr ? 'الدومين / الإيميل:' : 'Domain / Email:'}</span>
                  <div className="flex items-center gap-1.5">
                    <span className="font-mono font-semibold text-text">{viewItem.domain}</span>
                    <button
                      onClick={() => copyToClipboard(viewItem.domain!)}
                      className="p-1 rounded hover:bg-surface-lighter text-text-muted hover:text-text"
                    >
                      {copiedDomain === viewItem.domain ? <Check size={13} className="text-emerald-400" /> : <Copy size={13} />}
                    </button>
                  </div>
                </div>
              )}
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="p-3 rounded-lg bg-surface-lighter border border-border">
                <span className="text-text-muted block mb-1">{isAr ? 'المزود' : 'Provider'}</span>
                <strong className="text-text">{viewItem.provider || '—'}</strong>
              </div>

              <div className="p-3 rounded-lg bg-surface-lighter border border-border">
                <span className="text-text-muted block mb-1">{isAr ? 'التكلفة السنوية' : 'Annual Cost'}</span>
                <strong className="text-text">{viewItem.cost > 0 ? formatCurrency(viewItem.cost, viewItem.currency) : '—'}</strong>
              </div>

              <div className="p-3 rounded-lg bg-surface-lighter border border-border">
                <span className="text-text-muted block mb-1">{isAr ? 'تاريخ البدء' : 'Start Date'}</span>
                <strong className="text-text">{viewItem.start_date ? formatDate(viewItem.start_date) : '—'}</strong>
              </div>

              <div className="p-3 rounded-lg bg-surface-lighter border border-border">
                <span className="text-text-muted block mb-1">{isAr ? 'تاريخ الانتهاء' : 'End Date'}</span>
                <strong className="text-text">{formatDate(viewItem.end_date)}</strong>
              </div>
            </div>

            <div className="p-3 rounded-lg bg-surface-lighter border border-border flex items-center justify-between">
              <span className="text-text-muted">{isAr ? 'الحالة الحالية:' : 'Current Status:'}</span>
              <SubscriptionStatusBadge sub={viewItem} isAr={isAr} />
            </div>

            {viewItem.notes && (
              <div className="p-3 rounded-lg bg-surface-lighter border border-border">
                <span className="text-text-muted block mb-1">{isAr ? 'ملاحظات:' : 'Notes:'}</span>
                <p className="text-text whitespace-pre-wrap">{viewItem.notes}</p>
              </div>
            )}

            <div className="flex items-center justify-end gap-2 pt-2 border-t border-border">
              <button
                type="button"
                onClick={() => {
                  const item = viewItem;
                  setViewItem(null);
                  openRenewModal(item);
                }}
                className="btn-primary px-3 py-1.5 text-xs flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700"
              >
                <RefreshCw size={13} />
                <span>{isAr ? 'تجديد' : 'Renew'}</span>
              </button>
              <button
                type="button"
                onClick={() => {
                  const item = viewItem;
                  setViewItem(null);
                  openEditForm(item);
                }}
                className="btn-outline px-3 py-1.5 text-xs flex items-center gap-1.5"
              >
                <Edit2 size={13} />
                <span>{isAr ? 'تعديل' : 'Edit'}</span>
              </button>
              <button
                type="button"
                onClick={() => setViewItem(null)}
                className="btn-outline px-3 py-1.5 text-xs"
              >
                {isAr ? 'إغلاق' : 'Close'}
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};
