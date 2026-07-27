import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router';
import {
  useContracts,
  useContract,
  useContractTree,
  useEmployees,
  useCompanies,
  useContractMutations,
  usePaymentMutations,
  useAttachmentMutations,
} from '@/hooks/queries';

import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { Textarea } from '@/components/ui/Textarea';
import { Spinner } from '@/components/ui/Spinner';
import { formatCurrency, formatDate, formatPercentage, downloadBlob } from '@/utils';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import api from '@/lib/axios';
import {
  Plus,
  Search,
  Edit2,
  Trash2,
  Eye,
  FileSpreadsheet,
  FileText,
  Upload,
  Building,
  User,
  RefreshCw,
  History,
  Tag,
  ChevronLeft,
  ChevronRight,
  MoreVertical,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

// ─── 3-DOTS ACTION DROPDOWN COMPONENT ────────────────────────
const ContractActionDropdown: React.FC<{
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
            <span>{isAr ? 'تعديل العقد' : 'Edit Contract'}</span>
          </button>

          <button
            onClick={() => { setIsOpen(false); onRenew(); }}
            className="w-full px-3.5 py-2.5 text-xs font-bold text-text hover:bg-surface-lighter flex items-center gap-2.5 transition-colors"
          >
            <RefreshCw size={16} className="text-emerald-400" />
            <span>{isAr ? 'تجديد العقد' : 'Renew Contract'}</span>
          </button>

          <div className="border-t border-border/60 my-1" />

          <button
            onClick={() => { setIsOpen(false); onDelete(); }}
            className="w-full px-3.5 py-2.5 text-xs font-bold text-danger-500 hover:bg-danger-500/10 flex items-center gap-2.5 transition-colors"
          >
            <Trash2 size={16} className="text-danger-500" />
            <span>{isAr ? 'حذف العقد' : 'Delete Contract'}</span>
          </button>
        </div>
      )}
    </div>
  );
};

const contractSchema = z.object({
  company_id:      z.string().min(1, { message: 'يرجى اختيار العميل.' }),
  employee_id:     z.string().nullable().or(z.literal('')),
  contract_value:  z.string().min(1, { message: 'قيمة العقد مطلوبة.' }),
  currency:        z.string(),
  exchange_rate:   z.string().nullable().or(z.literal('')),
  start_date:      z.string().nullable().or(z.literal('')),
  end_date:        z.string().nullable().or(z.literal('')),
  status:          z.string(),
  category:        z.string().nullable().or(z.literal('')),
  category_custom: z.string().nullable().or(z.literal('')),
  notes:           z.string().nullable().or(z.literal('')),
  initial_payment: z.string().nullable().or(z.literal('')),
});

const paymentSchema = z.object({
  amount: z.string().min(1, { message: 'Amount is required.' }),
  exchange_rate: z.string().nullable().or(z.literal('')),
  payment_date: z.string().min(1, { message: 'Payment date is required.' }),
  method: z.string(),
  status: z.string(),
  notes: z.string().nullable().or(z.literal('')),
});

const renewSchema = z.object({
  contract_value: z.string().min(1, { message: 'قيمة العقد مطلوبة.' }),
  exchange_rate: z.string().nullable().or(z.literal('')),
  start_date: z.string().min(1, { message: 'تاريخ البدء مطلوب.' }),
  end_date: z.string().min(1, { message: 'تاريخ الانتهاء مطلوب.' }),
  category: z.string().nullable().or(z.literal('')),
  category_custom: z.string().nullable().or(z.literal('')),
  initial_payment: z.string().nullable().or(z.literal('')),
  notes: z.string().nullable().or(z.literal('')),
});

type ContractFormFields = z.infer<typeof contractSchema>;
type PaymentFormFields = z.infer<typeof paymentSchema>;
type RenewFormFields = z.infer<typeof renewSchema>;

export const ContractsPage: React.FC = () => {
  const { t, i18n } = useTranslation();
  const isAr = i18n.language === 'ar';
  const [searchParams, setSearchParams] = useSearchParams();

  // Read single source of truth directly from searchParams to avoid state sync lag
  const searchParam = searchParams.get('search') || '';
  const [search, setSearch] = useState(searchParam);

  useEffect(() => {
    setSearch(searchParam);
  }, [searchParam]);

  const filterEmployee = searchParams.get('employee_id') || '';
  const filterStatus = searchParams.get('status') || '';
  const filterCategory = searchParams.get('category') || '';
  const filterUncollected = searchParams.get('uncollected') === 'true';
  const filterYear = searchParams.get('year') || '';
  const page = Math.max(1, parseInt(searchParams.get('page') || '1', 10));
  const perPage = parseInt(searchParams.get('per_page') || '15', 10);

  const updateQueryParams = (paramsToUpdate: Record<string, string | undefined>) => {
    const nextParams = new URLSearchParams(searchParams.toString());
    Object.entries(paramsToUpdate).forEach(([key, value]) => {
      if (value) {
        nextParams.set(key, value);
      } else {
        nextParams.delete(key);
      }
    });
    setSearchParams(nextParams);
  };

  const handleFilterChange = (key: string, value: string | undefined) => {
    updateQueryParams({
      [key]: value,
      page: '1',
    });
  };

  const activeFilters = {
    search:      search || undefined,
    employee_id: filterEmployee || undefined,
    status:      filterStatus || undefined,
    category:    filterCategory || undefined,
    uncollected: filterUncollected ? 'true' : undefined,
    year:        filterYear || undefined,
    page:        page,
    per_page:    perPage,
  };

  const { data: contractsRes, isLoading } = useContracts(activeFilters);
  const { data: employeesRes } = useEmployees({ per_page: 100 });
  const { data: companiesRes } = useCompanies({ per_page: 500 });

  const companyOptions = [
    { value: '', label: isAr ? '— اختر العميل / الشركة —' : '— Select Client / Company —' },
    ...(companiesRes?.data || []).map((c: any) => ({
      value: c.id.toString(),
      label: c.client_name ? `${c.name} — ${c.client_name}` : c.name,
    })),
  ];
  const { create, update, remove, renew } = useContractMutations();

  // Modal / Dialog States
  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [viewContractId, setViewContractId] = useState<number | null>(null);
  const [renewId, setRenewId] = useState<number | null>(null);

  const { register, handleSubmit, reset, setValue, watch, formState: { errors } } = useForm<ContractFormFields>({
    resolver: zodResolver(contractSchema),
    defaultValues: {
      company_id:      '',
      currency:        'USD',
      exchange_rate:   '1.0',
      status:          'draft',
      employee_id:     '',
      contract_value:  '',
      start_date:      '',
      end_date:        '',
      category:        '',
      category_custom: '',
      notes:           '',
      initial_payment: '',
    },
  });

  const selectedCurrency = watch('currency');
  const selectedCategory = watch('category');

  const categoryOptions = [
    { value: '', label: t('contract.select_category', '— Select Category —') },
    { value: 'social', label: t('contract.categories.social', 'Social Media') },
    { value: 'menu', label: t('contract.categories.menu', 'Digital Menu') },
    { value: 'visual_identity', label: t('contract.categories.visual_identity', 'Visual Identity') },
    { value: 'accounting_software', label: t('contract.categories.accounting_software', 'Accounting Software') },
    { value: 'hardware', label: t('contract.categories.hardware', 'Hardware Equipment') },
    { value: 'custom_dev', label: t('contract.categories.custom_dev', 'Custom Development') },
    { value: 'other', label: t('contract.categories.other', 'Other') },
  ];

  const getCategoryLabel = (category?: string | null, custom?: string | null) => {
    if (!category) return '—';
    if (category === 'other') return custom || t('contract.categories.other', 'Other');
    return t(`contract.categories.${category}`, category);
  };

  const onSubmit = async (data: ContractFormFields) => {
    const payload: Record<string, unknown> = {
      company_id:      parseInt(data.company_id),
      employee_id:     data.employee_id ? parseInt(data.employee_id) : null,
      contract_value:  parseFloat(data.contract_value),
      currency:        data.currency,
      exchange_rate:   data.exchange_rate ? parseFloat(data.exchange_rate) : 1.0,
      start_date:      data.start_date  || null,
      end_date:        data.end_date    || null,
      status:          data.status,
      category:        data.category    || null,
      category_custom: data.category === 'other' ? (data.category_custom || null) : null,
      notes:           data.notes       || null,
    };
    if (!editingId && data.initial_payment) {
      payload.initial_payment = parseFloat(data.initial_payment);
    }
    try {
      if (editingId) {
        await update.mutateAsync({ id: editingId, payload });
      } else {
        await create.mutateAsync(payload);
      }
      setFormOpen(false);
      reset();
    } catch (_) {}
  };

  const handleEdit = (contract: any) => {
    setEditingId(contract.id);
    setValue('company_id',      contract.company?.id?.toString() || '');
    setValue('employee_id',     contract.employee?.id.toString() || '');
    setValue('contract_value',  contract.contract_value.toString());
    setValue('currency',        contract.currency);
    setValue('exchange_rate',   contract.exchange_rate ? contract.exchange_rate.toString() : '1.0');
    setValue('start_date',      contract.start_date ? contract.start_date.substring(0, 10) : '');
    setValue('end_date',        contract.end_date   ? contract.end_date.substring(0, 10)   : '');
    setValue('status',          contract.status);
    setValue('category',        contract.category || '');
    setValue('category_custom', contract.category_custom || '');
    setValue('notes',           contract.notes || '');
    setFormOpen(true);
  };

  const handleDeleteConfirm = async () => {
    if (!deleteId) return;
    try {
      await remove.mutateAsync(deleteId);
      setDeleteId(null);
    } catch (_) {}
  };

  const { register: regRenew, handleSubmit: handleRenewSubmit, reset: resetRenew, watch: watchRenew, formState: { errors: renewErrors } } = useForm<RenewFormFields>({
    resolver: zodResolver(renewSchema),
    defaultValues: {
      contract_value: '',
      exchange_rate: '1.0',
      start_date: '',
      end_date: '',
      category: '',
      category_custom: '',
      initial_payment: '',
      notes: '',
    },
  });

  const renewCategorySelected = watchRenew('category');
  const contractToRenew = contractsRes?.data?.find((c: any) => c.id === renewId);
  const renewCurrency = contractToRenew?.currency || 'USD';

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
          category_custom: data.category === 'other' ? (data.category_custom || null) : null,
          initial_payment: data.initial_payment ? parseFloat(data.initial_payment) : undefined,
          notes: data.notes || null,
        }
      });
      setRenewId(null);
      resetRenew();
    } catch (_) {}
  };

  const exportData = async (format: 'pdf' | 'excel' | 'csv') => {
    try {
      const response = await api.get('/export/contracts', {
        params: { ...activeFilters, format },
        responseType: 'blob',
      });
      const ext = format === 'pdf' ? 'pdf' : format === 'excel' ? 'xlsx' : 'csv';
      downloadBlob(response.data, `contracts_${Date.now()}.${ext}`);
    } catch (err) {
      console.error('Export failed', err);
    }
  };

  // View Contract Detail Popover / Modal
  const ContractDetailsModal: React.FC<{ id: number }> = ({ id }) => {
    const { data: contract, isLoading: contractLoading } = useContract(id);
    const { create: addPayment, remove: removePayment } = usePaymentMutations(id);
    const { upload: uploadFile, remove: removeFile } = useAttachmentMutations(id);

    const [paymentFormOpen, setPaymentFormOpen] = useState(false);
    const [fileToUpload, setFileToUpload] = useState<File | null>(null);

    const { register: regPayment, handleSubmit: handlePaymentSubmit, reset: resetPayment, setValue: setPaymentValue } = useForm<PaymentFormFields>({
      resolver: zodResolver(paymentSchema),
      defaultValues: {
        amount: '',
        exchange_rate: '1.0',
        payment_date: '',
        method: 'cash',
        status: 'paid',
        notes: '',
      },
    });

    React.useEffect(() => {
      if (contract) {
        setPaymentValue('exchange_rate', contract.exchange_rate ? contract.exchange_rate.toString() : '1.0');
      }
    }, [contract]);

    if (contractLoading) return <div className="text-center py-6 text-sm text-text-muted">{t('common.loading', 'Loading...')}</div>;
    if (!contract) return <div className="text-center py-6 text-sm text-red-500">{t('contract.load_failed', 'Failed to load contract details.')}</div>;

    const onPaymentSubmit = async (data: PaymentFormFields) => {
      await addPayment.mutateAsync({
        amount: parseFloat(data.amount),
        exchange_rate: data.exchange_rate ? parseFloat(data.exchange_rate) : (contract?.exchange_rate ?? 1.0),
        payment_date: data.payment_date,
        method: data.method,
        status: data.status,
        notes: data.notes || null,
      });
      setPaymentFormOpen(false);
      resetPayment();
    };

    const handleFileUpload = async (e: React.FormEvent) => {
      e.preventDefault();
      if (!fileToUpload) return;

      const formData = new FormData();
      formData.append('attachable_type', 'contract');
      formData.append('attachable_id', id.toString());
      formData.append('file', fileToUpload);

      await uploadFile.mutateAsync(formData);
      setFileToUpload(null);
    };

    return (
      <div className="flex flex-col gap-6">
        {/* Upper Metadata Panels */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card className="p-4 border-border bg-surface-lighter">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.contract_number', 'Contract Number')}</div>
            <div className="text-sm font-black text-text mt-1">{contract.contract_number}</div>
          </Card>
          <Card className="p-4 border-border bg-surface-lighter">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.category', 'Contract Category')}</div>
            <div className="text-sm font-bold text-primary-text mt-1 flex items-center gap-1.5">
              <Tag size={14} className="text-primary-text/70" />
              {getCategoryLabel(contract.category, contract.category_custom)}
            </div>
          </Card>
          <Card className="p-4 border-border bg-surface-lighter">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('common.status', 'Contract Status')}</div>
            <div className="mt-1"><Badge status={contract.status} /></div>
          </Card>
          <Card className="p-4 border-border bg-surface-lighter">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.progress', 'Progress')}</div>
            <div className="text-sm font-black text-text mt-1">{formatPercentage(contract.progress_percentage ?? 0)}</div>
          </Card>
        </div>

        {/* Financial Info cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card className="p-4 border-border bg-surface-lighter">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.value', 'Total Value')}</div>
            <div className="text-sm font-black text-text mt-1">{formatCurrency(contract.contract_value, contract.currency)}</div>
            {contract.currency !== 'USD' && contract.exchange_rate && (
              <div className="text-[10px] text-text-muted mt-0.5 font-bold">
                {t('contract.exchange_rate_label', 'Exchange Rate:')} {contract.exchange_rate}
              </div>
            )}
          </Card>
          <Card className="p-4 border-border bg-surface-lighter">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.total_paid', 'Collected Amount')}</div>
            <div className="text-sm font-black text-success-text mt-1">{formatCurrency(contract.total_paid, contract.currency)}</div>
          </Card>
          <Card className="p-4 border-border bg-surface-lighter">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.remaining', 'Remaining Amount')}</div>
            <div className="text-sm font-black text-danger-text mt-1">{formatCurrency(contract.remaining_amount, contract.currency)}</div>
          </Card>
          <Card className="p-4 border-border bg-surface-lighter">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.collection_pct', 'Collection Rate')}</div>
            <div className="text-sm font-black text-info-text mt-1">{formatPercentage(contract.collection_percentage)}</div>
          </Card>
        </div>

        {/* Details Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-border pt-6">
          <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2 text-text-muted">
              <Building size={16} />
              <span className="text-xs font-semibold uppercase tracking-wider">{t('company.title', 'Client Information')}</span>
            </div>
            <div>
              <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('company.company_name', 'Company Name')}</div>
              <div className="text-sm font-semibold text-text mt-0.5">{contract.company?.name ?? '—'}</div>
            </div>
            <div>
              <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('company.activity', 'Industry / Activity')}</div>
              <div className="text-sm text-text mt-0.5">{contract.company?.activity ?? '—'}</div>
            </div>
          </div>

          <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2 text-text-muted">
              <User size={16} />
              <span className="text-xs font-semibold uppercase tracking-wider">{t('contract.internal_management', 'Internal Management')}</span>
            </div>
            <div>
              <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('contract.employee', 'Sales Representative')}</div>
              <div className="text-sm font-semibold text-text mt-0.5">{contract.employee?.name ?? t('contract.unassigned', 'Unassigned')}</div>
            </div>
          </div>
        </div>

        {/* Notes & Terms Section */}
        <div className="border-t border-border pt-6">
          <div className="flex items-center gap-2 text-text-muted mb-3">
            <FileText size={16} />
            <h4 className="text-xs font-bold uppercase tracking-wider">{t('contract.notes_section', 'Notes & Terms')}</h4>
          </div>
          <Card className="p-4 border-border bg-surface-lighter/60 text-sm text-text leading-relaxed whitespace-pre-wrap">
            {contract.notes ? (
              contract.notes
            ) : (
              <span className="text-text-muted italic">{t('common.no_data', 'No notes specified.')}</span>
            )}
          </Card>
        </div>

        {/* Payments Section */}
        <div className="border-t border-border pt-6">
          <div className="flex items-center justify-between mb-4">
            <h4 className="text-sm font-bold text-text uppercase tracking-wider">{t('contract.payments_tab', 'Contract Installments')}</h4>
            <Button size="sm" onClick={() => setPaymentFormOpen(true)} className="flex items-center gap-1">
              <Plus size={14} />
              {t('payment.add', 'Add Payment')}
            </Button>
          </div>

          <Table
            columns={[
              { key: 'payment_date', header: t('payment.payment_date', 'Date'), render: (row: any) => formatDate(row.payment_date) },
              { key: 'amount', header: t('payment.amount', 'Amount'), render: (row: any) => (
                <div>
                  <div>{formatCurrency(row.amount, contract.currency)}</div>
                  {contract.currency !== 'USD' && row.exchange_rate && (
                    <div className="text-[9px] text-text-muted font-bold mt-0.5">
                      {t('contract.exchange_rate_label', 'Exchange Rate:')} {row.exchange_rate}
                    </div>
                  )}
                </div>
              ) },
              { key: 'method', header: t('payment.method', 'Payment Method'), render: (row: any) => t(`payment.${row.method}`, row.method.replace('_', ' ').toUpperCase()) as string },
              { key: 'status', header: t('common.status', 'Status'), render: (row: any) => <Badge status={row.status} /> },
              { key: 'actions', header: t('common.actions', 'Actions'), className: 'text-end', render: (row: any) => (
                <Button variant="ghost" size="sm" onClick={() => removePayment.mutate(row.id)}>
                  <Trash2 size={12} className="text-danger-text" />
                </Button>
              )},
            ]}
            data={contract.payments || []}
          />
        </div>

        {/* Attachments Section */}
        <div className="border-t border-border pt-6">
          <h4 className="text-sm font-bold text-text uppercase tracking-wider mb-4">{t('contract.attachments_tab', 'Contract Files / Scans')}</h4>
          <form onSubmit={handleFileUpload} className="flex gap-3 mb-4">
            <input
              type="file"
              onChange={(e) => setFileToUpload(e.target.files?.[0] || null)}
              className="flex-1 bg-surface border border-border rounded-lg p-2 text-xs text-text file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-600/10 file:text-blue-400 file:cursor-pointer"
            />
            <Button size="sm" type="submit" disabled={!fileToUpload} isLoading={uploadFile.isPending}>
              <Upload size={14} className="mr-1.5" />
              {t('contract.upload_file', 'Upload File')}
            </Button>
          </form>

          <Table
            columns={[
              { key: 'original_name', header: t('common.name', 'File Name') },
              { key: 'actions', header: t('common.actions', 'Actions'), className: 'text-end', render: (row: any) => (
                <div className="flex justify-end gap-2">
                  <a
                    href={row.url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center justify-center p-2 rounded-lg text-text-muted hover:bg-surface-light hover:text-text transition-colors"
                  >
                    <Eye size={12} />
                  </a>
                  <Button variant="ghost" size="sm" onClick={() => removeFile.mutate(row.id)}>
                    <Trash2 size={12} className="text-danger-text" />
                  </Button>
                </div>
              )},
            ]}
            data={contract.attachments || []}
          />
        </div>

        {/* Add Payment Modal */}
        <Modal isOpen={paymentFormOpen} onClose={() => setPaymentFormOpen(false)} title={t('payment.add', 'Register Payment')}>
          <form onSubmit={handlePaymentSubmit(onPaymentSubmit)} className="flex flex-col gap-4">
            <Input label={`${t('payment.amount', 'Installment Amount')} (${contract.currency})`} type="number" step="0.01" {...regPayment('amount')} />
            {contract.currency && contract.currency !== 'USD' && (
              <Input
                label={t('contract.exchange_rate', 'Exchange Rate vs USD')}
                type="number"
                step="0.0001"
                placeholder={contract.currency === 'SYP' ? '15000' : contract.currency === 'SAR' ? '3.75' : '3.67'}
                {...regPayment('exchange_rate')}
              />
            )}
            <Input label={t('payment.payment_date', 'Payment Date')} type="date" {...regPayment('payment_date')} />
            <Select
              label={t('payment.method', 'Payment Method')}
              options={[
                { value: 'cash', label: t('payment.cash', 'Cash') },
                { value: 'bank_transfer', label: t('payment.bank_transfer', 'Bank Transfer') },
                { value: 'check', label: t('payment.check', 'Bank Check') },
                { value: 'other', label: t('payment.other', 'Other') },
              ]}
              {...regPayment('method')}
            />
            <Select
              label={t('common.status', 'Payment Status')}
              options={[
                { value: 'paid', label: t('status.paid', 'Paid') },
                { value: 'pending', label: t('status.pending', 'Pending') },
              ]}
              {...regPayment('status')}
            />
            <Textarea label={t('common.notes', 'Notes')} {...regPayment('notes')} />
            <div className="flex justify-end gap-3 border-t border-border pt-4 mt-2">
              <Button variant="ghost" type="button" onClick={() => setPaymentFormOpen(false)}>
                {t('common.cancel', 'Cancel')}
              </Button>
              <Button type="submit" isLoading={addPayment.isPending}>
                {t('common.save', 'Add Payment')}
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    );
  };

  const ContractTreeRow: React.FC<{ id: number }> = ({ id }) => {
    const { data: contractTree, isLoading } = useContractTree(id);

    if (isLoading) {
      return (
        <div className="p-4 text-center text-sm text-text-muted flex items-center justify-center gap-2">
          <Spinner size="sm" />
          {t('common.loading', 'Loading...')}
        </div>
      );
    }
    
    if (!contractTree || !contractTree.renewals || contractTree.renewals.length === 0) {
      return (
        <div className="p-4 text-center text-sm text-text-muted bg-surface-lighter/50">
          {t('contract.no_renewals_found', 'No renewal contracts found for this contract.')}
        </div>
      );
    }

    return (
      <div className="p-4 bg-surface-lighter/80 pe-6 ps-14 shadow-inner">
        <div className="flex items-center gap-2 mb-3 text-text">
          <History size={16} />
          <h4 className="text-sm font-bold uppercase tracking-wider">{isAr ? 'سجل العقود والتجديدات السابقة' : 'Previous Contracts & Renewals History'}</h4>
        </div>
        <Table
          columns={[
            { key: 'start_date', header: t('contract.renewed_date', 'Renewal Date'), render: (r: any) => formatDate(r.start_date) },
            { key: 'end_date', header: t('contract.end_date', 'End Date'), render: (r: any) => formatDate(r.end_date) },
            { key: 'contract_value', header: t('contract.value', 'Contract Value'), render: (r: any) => formatCurrency(r.contract_value, r.currency) },
            { key: 'total_paid', header: t('contract.total_paid', 'Paid'), render: (r: any) => formatCurrency(r.total_paid, r.currency) },
            { key: 'status', header: t('common.status', 'Status'), render: (r: any) => <Badge status={r.status} /> },
            { key: 'actions', header: t('common.actions', 'Actions'), className: 'text-end', render: (r: any) => (
              <div className="flex justify-end gap-2" onClick={(e) => e.stopPropagation()}>
                <Button variant="ghost" size="sm" onClick={() => setViewContractId(r.id)} title={t('common.view', 'View')}>
                  <Eye size={14} className="text-info-text" />
                </Button>
                <Button variant="ghost" size="sm" onClick={() => handleEdit(r)} title={t('common.edit', 'Edit')}>
                  <Edit2 size={14} className="text-primary-text" />
                </Button>
                <Button variant="ghost" size="sm" onClick={() => setDeleteId(r.id)} title={t('common.delete', 'Delete')}>
                  <Trash2 size={14} className="text-danger-text" />
                </Button>
              </div>
            )},
          ]}
          data={contractTree.renewals}
        />
      </div>
    );
  };

  const salesEmployees = (employeesRes?.data || []).filter((e: any) =>
    !e.department || e.department === 'sales' || e.department === 'management'
  );

  const employeeOptions = [
    { value: '', label: t('contract.select_employee', '— Select Employee —') },
    ...salesEmployees.map((e: any) => ({ value: e.id.toString(), label: e.name })),
  ];

  const columns = [
    { key: 'company', header: t('contract.company', 'Client Company'), render: (row: any) => (
      <div className="flex items-center gap-2">
        <span className="font-bold">{row.company?.name || '—'}</span>
        {row.renewals_count > 0 && (
          <span className="bg-info-50 text-info-text text-[10px] px-2 py-0.5 rounded-md font-bold shadow-sm" title={t('contract.renewed_tag', 'Renewed')}>
            {t('contract.renewed_tag', 'Renewed')} ({row.renewals_count})
          </span>
        )}
      </div>
    )},
    { key: 'category', header: t('contract.category', 'Category'), render: (row: any) => {
      const categoryColors: Record<string, string> = {
        social: 'bg-purple-500/15 text-purple-400 border-purple-500/30',
        menu: 'bg-amber-500/15 text-amber-400 border-amber-500/30',
        visual_identity: 'bg-pink-500/15 text-pink-400 border-pink-500/30',
        accounting_software: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/30',
        hardware: 'bg-orange-500/15 text-orange-400 border-orange-500/30',
        custom_dev: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
        other: 'bg-gray-500/15 text-gray-400 border-gray-500/30',
      };
      const cat = row.category || 'other';
      const colorClass = categoryColors[cat] || categoryColors.other;
      return (
        <span className={`inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-full border ${colorClass}`}>
          {getCategoryLabel(row.category, row.category_custom)}
        </span>
      );
    }},
    { key: 'employee', header: t('contract.employee', 'Account Manager'), render: (row: any) => row.employee?.name || '—' },
    { key: 'contract_value', header: t('contract.value', 'Value'), render: (row: any) => formatCurrency(row.contract_value, row.currency) },
    { key: 'total_paid', header: t('contract.total_paid', 'Paid'), render: (row: any) => formatCurrency(row.total_paid, row.currency) },
    { key: 'start_date', header: t('contract.start_date', 'Start Date'), render: (row: any) => formatDate(row.start_date) },
    { key: 'end_date', header: t('contract.end_date', 'End Date'), render: (row: any) => formatDate(row.end_date) },
    { key: 'status', header: t('common.status', 'Status'), render: (row: any) => <Badge status={row.status} /> },
    { key: 'actions', header: t('common.actions', 'إجراءات'), className: 'text-center w-16', render: (row: any) => (
      <ContractActionDropdown
        isAr={isAr}
        onView={() => setViewContractId(row.id)}
        onEdit={() => handleEdit(row)}
        onRenew={() => {
          setRenewId(row.id);
          resetRenew({
            contract_value: row.contract_value.toString(),
            exchange_rate: row.exchange_rate ? row.exchange_rate.toString() : '1.0',
            category: row.category || '',
            category_custom: row.category_custom || '',
          });
        }}
        onDelete={() => setDeleteId(row.id)}
      />
    )},
  ];

  return (
    <div className="flex flex-col gap-6">
      {/* Title Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-black text-text uppercase tracking-wider">{t('contract.title', 'Contracts')}</h1>
          <p className="text-xs text-text-muted mt-1">{t('contract.subtitle', 'Configure client contract packages, track payment milestones, and upload scans.')}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => exportData('excel')} className="flex items-center gap-1 text-success-text hover:text-success-text/90">
            <FileSpreadsheet size={14} />
            Excel
          </Button>
          <Button variant="outline" size="sm" onClick={() => exportData('pdf')} className="flex items-center gap-1 text-danger-text hover:text-danger-text/90">
            <FileText size={14} />
            PDF
          </Button>
          <Button
            onClick={() => {
              setEditingId(null);
              reset();
              setFormOpen(true);
            }}
            className="flex items-center gap-1.5"
          >
            <Plus size={16} />
            {t('contract.add', 'Create Contract')}
          </Button>
        </div>
      </div>

      {/* Advanced Filter Bar Grid */}
      <Card className="p-6 border-border bg-surface-light">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
          <div className="relative">
            <Search className="absolute start-3 top-3 h-4 w-4 text-text-muted" />
            <input
              type="text"
              placeholder={t('common.search', 'Search...')}
              value={search}
              onChange={(e) => {
                const val = e.target.value;
                setSearch(val);
                handleFilterChange('search', val || undefined);
              }}
              className="w-full bg-surface border border-border rounded-lg py-1.5 ps-9 pe-4 text-xs text-text placeholder-text-muted focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <Select
            className="h-9 text-xs"
            options={employeeOptions}
            value={filterEmployee}
            onChange={(e) => {
              const val = e.target.value;
              handleFilterChange('employee_id', val || undefined);
            }}
          />
          <Select
            className="h-9 text-xs"
            options={[
              { value: '', label: t('contract.filter_status', '— Filter by Status —') },
              { value: 'draft', label: t('status.draft', 'Draft') },
              { value: 'signed', label: t('status.signed', 'Signed') },
              { value: 'active', label: t('status.active', 'Active') },
              { value: 'completed', label: t('status.completed', 'Completed') },
              { value: 'cancelled', label: t('status.cancelled', 'Cancelled') },
              { value: 'suspended', label: t('status.suspended', 'Suspended') },
              { value: 'renewed', label: t('status.renewed', 'Renewed') },
            ]}
            value={filterStatus}
            onChange={(e) => {
              const val = e.target.value;
              handleFilterChange('status', val || undefined);
            }}
          />
          <Select
            className="h-9 text-xs"
            options={categoryOptions}
            value={filterCategory}
            onChange={(e) => {
              const val = e.target.value;
              handleFilterChange('category', val || undefined);
            }}
          />
          <Select
            className="h-9 text-xs"
            options={[
              { value: '', label: t('contract.filter_all_collection', '— Collection Status —') },
              { value: 'all', label: t('contract.filter_all', 'All Contracts') },
              { value: 'uncollected', label: t('contract.filter_uncollected', 'Not Fully Collected') },
            ]}
            value={filterUncollected ? 'uncollected' : ''}
            onChange={(e) => {
              const val = e.target.value;
              handleFilterChange('uncollected', val === 'uncollected' ? 'true' : undefined);
            }}
          />
          <Select
            className="h-9 text-xs"
            options={[
              { value: '', label: t('contract.filter_year', '— Filter by Year —') },
              { value: '2026', label: '2026' },
              { value: '2025', label: '2025' },
              { value: '2024', label: '2024' },
              { value: '2023', label: '2023' },
            ]}
            value={filterYear}
            onChange={(e) => {
              const val = e.target.value;
              handleFilterChange('year', val || undefined);
            }}
          />
        </div>
      </Card>

      {/* Data Table */}
      <Table
        columns={columns}
        data={contractsRes?.data || []}
        isLoading={isLoading}
        expandableContent={(row: any) => <ContractTreeRow id={row.id} />}
        hasExpandable={(row: any) => row.renewals_count > 0}
      />

      {/* Pagination Controls */}
      {contractsRes?.meta && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-xl border border-border bg-surface-light text-xs text-text-muted">
          <div className="flex items-center gap-2">
            <span>{t('common.per_page', 'Per page')}:</span>
            <Select
              className="h-8 w-20 text-xs py-0"
              value={perPage.toString()}
              options={[
                { value: '10', label: '10' },
                { value: '15', label: '15' },
                { value: '25', label: '25' },
                { value: '50', label: '50' },
                { value: '100', label: '100' },
              ]}
              onChange={(e) => {
                const newPerPage = e.target.value;
                updateQueryParams({ per_page: newPerPage, page: '1' });
              }}
            />
            {contractsRes.meta.total > 0 && (
              <span className="ms-2 font-medium">
                {t('common.showing', 'Showing')} {contractsRes.meta.from ?? 0} {t('common.to', 'to')} {contractsRes.meta.to ?? 0} {t('common.of', 'of')} {contractsRes.meta.total}
              </span>
            )}
          </div>

          {contractsRes.meta.last_page > 1 && (
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => {
                  updateQueryParams({ page: (page - 1).toString() });
                }}
                className="flex items-center gap-1 h-8 text-xs"
              >
                {i18n.language === 'ar' ? <ChevronRight size={14} /> : <ChevronLeft size={14} />}
                {t('common.previous', 'Previous')}
              </Button>

              <span className="font-semibold text-text px-2">
                {t('common.page', 'Page')} {contractsRes.meta.current_page} {t('common.of', 'of')} {contractsRes.meta.last_page}
              </span>

              <Button
                variant="outline"
                size="sm"
                disabled={page >= contractsRes.meta.last_page}
                onClick={() => {
                  updateQueryParams({ page: (page + 1).toString() });
                }}
                className="flex items-center gap-1 h-8 text-xs"
              >
                {t('common.next', 'Next')}
                {i18n.language === 'ar' ? <ChevronLeft size={14} /> : <ChevronRight size={14} />}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Create / Edit Form Modal */}
      <Modal
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        title={editingId ? t('contract.edit', 'Modify Contract Details') : t('contract.add', 'Create Contract')}
      >
        <form onSubmit={handleSubmit(onSubmit)} className="grid grid-cols-2 gap-4">
          <div className="col-span-2">
            <Select
              label={isAr ? 'العميل / الشركة' : 'Client / Company'}
              options={companyOptions}
              disabled={!!editingId}
              error={errors.company_id?.message}
              {...register('company_id')}
            />
          </div>
          <Select label={t('contract.employee', 'Account Manager')} options={employeeOptions} error={errors.employee_id?.message} {...register('employee_id')} />
          <Select label={t('contract.category', 'Contract Category')} options={categoryOptions} error={errors.category?.message} {...register('category')} />
          
          {selectedCategory === 'other' && (
            <div className="col-span-2">
              <Input
                label={t('contract.category_custom', 'Category Details (if other)')}
                placeholder="e.g. Website Maintenance & Support"
                error={errors.category_custom?.message}
                {...register('category_custom')}
              />
            </div>
          )}

          <Input label={t('contract.value', 'Contract Value')} type="number" step="0.01" error={errors.contract_value?.message} {...register('contract_value')} />
          <Select
            label={t('contract.currency', 'Currency')}
            options={[
              { value: 'USD', label: 'USD ($)' },
              { value: 'SAR', label: 'SAR (ر.س)' },
              { value: 'AED', label: 'AED (د.إ)' },
              { value: 'SYP', label: 'SYP (ل.س)' },
            ]}
            error={errors.currency?.message}
            {...register('currency')}
          />
          {selectedCurrency && selectedCurrency !== 'USD' && (
            <Input
              label={t('contract.exchange_rate', 'Exchange Rate vs USD')}
              type="number"
              step="0.0001"
              placeholder={selectedCurrency === 'SYP' ? '15000' : selectedCurrency === 'SAR' ? '3.75' : '3.67'}
              error={errors.exchange_rate?.message}
              {...register('exchange_rate')}
            />
          )}
          <Input label={t('contract.start_date', 'Start Date')} type="date" error={errors.start_date?.message} {...register('start_date')} />
          <Input label={t('contract.end_date',   'End Date')} type="date" error={errors.end_date?.message}   {...register('end_date')} />
          <Select
            label={t('common.status', 'Contract Status')}
            options={[
              { value: 'draft',     label: t('status.draft',     'Draft') },
              { value: 'signed',    label: t('status.signed',    'Signed') },
              { value: 'active',    label: t('status.active',    'Active') },
              { value: 'completed', label: t('status.completed', 'Completed') },
              { value: 'cancelled', label: t('status.cancelled', 'Cancelled') },
              { value: 'suspended', label: t('status.suspended', 'Suspended') },
            ]}
            error={errors.status?.message}
            {...register('status')}
          />
          {!editingId && (
            <Input
              label={t('contract.initial_payment', 'Initial Payment at Signing')}
              type="number"
              step="0.01"
              placeholder="0.00"
              error={errors.initial_payment?.message}
              {...register('initial_payment')}
            />
          )}
          <div className="col-span-2">
            <Textarea label={t('common.notes', 'Notes')} error={errors.notes?.message} {...register('notes')} />
          </div>
          <div className="col-span-2 flex justify-end gap-3 border-t border-border pt-4 mt-2">
            <Button variant="ghost" type="button" onClick={() => setFormOpen(false)}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button type="submit" isLoading={create.isPending || update.isPending}>
              {t('common.save', 'Save')}
            </Button>
          </div>
        </form>
      </Modal>

      {/* View Details modal */}
      <Modal
        isOpen={viewContractId !== null}
        onClose={() => setViewContractId(null)}
        title={t('contract.dossier_title', 'Contract Dossier')}
        size="xl"
      >
        {viewContractId && <ContractDetailsModal id={viewContractId} />}
      </Modal>

      {/* Delete Contract Modal */}
      <Modal
        isOpen={deleteId !== null}
        onClose={() => setDeleteId(null)}
        title={t('contract.delete', 'Delete Contract')}
        size="sm"
      >
        <div className="flex flex-col gap-4">
          <p className="text-sm text-text-muted leading-relaxed">
            {t('contract.confirm_delete', 'Are you sure you want to permanently delete this contract? All associated payments and attachments will be removed.')}
          </p>
          <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
            <Button variant="ghost" onClick={() => setDeleteId(null)}>
              {t('common.no', 'Cancel')}
            </Button>
            <Button variant="danger" onClick={handleDeleteConfirm} isLoading={remove.isPending}>
              {t('common.yes', 'Yes, Delete')}
            </Button>
          </div>
        </div>
      </Modal>

      {/* Renew Contract Modal */}
      <Modal
        isOpen={renewId !== null}
        onClose={() => { setRenewId(null); resetRenew(); }}
        title={t('contract.renew', 'Renew Contract')}
      >
        <form onSubmit={handleRenewSubmit(onRenewSubmit)} className="flex flex-col gap-4">
          <p className="text-sm text-text-muted mb-2">
            {t('contract.renew_desc', 'The current contract status will be changed to "Renewed" and a new contract will be generated. Payment history will not be copied.')}
          </p>
          <Select label={t('contract.category', 'Contract Category')} options={categoryOptions} error={renewErrors.category?.message} {...regRenew('category')} />
          {renewCategorySelected === 'other' && (
            <Input
              label={t('contract.category_custom', 'Category Details (if other)')}
              placeholder="e.g. Website Maintenance & Support"
              error={renewErrors.category_custom?.message}
              {...regRenew('category_custom')}
            />
          )}
          <Input label={t('contract.value', 'New Contract Value')} type="number" step="0.01" error={renewErrors.contract_value?.message} {...regRenew('contract_value')} />
          {renewCurrency && renewCurrency !== 'USD' && (
            <Input
              label={t('contract.exchange_rate', 'Exchange Rate vs USD')}
              type="number"
              step="0.0001"
              placeholder={renewCurrency === 'SYP' ? '15000' : renewCurrency === 'SAR' ? '3.75' : '3.67'}
              error={renewErrors.exchange_rate?.message}
              {...regRenew('exchange_rate')}
            />
          )}
          <div className="grid grid-cols-2 gap-4">
            <Input label={t('contract.start_date', 'New Start Date')} type="date" error={renewErrors.start_date?.message} {...regRenew('start_date')} />
            <Input label={t('contract.end_date', 'New End Date')} type="date" error={renewErrors.end_date?.message} {...regRenew('end_date')} />
          </div>
          <Input 
            label={t('contract.initial_payment', 'Initial Payment at Renewal')} 
            type="number" 
            step="0.01" 
            placeholder="0.00" 
            error={renewErrors.initial_payment?.message} 
            {...regRenew('initial_payment')} 
          />
          <Textarea label={t('common.notes', 'Notes')} error={renewErrors.notes?.message} {...regRenew('notes')} />
          <div className="flex justify-end gap-3 border-t border-border pt-4 mt-2">
            <Button variant="ghost" type="button" onClick={() => { setRenewId(null); resetRenew(); }}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button type="submit" isLoading={renew.isPending}>
              {t('contract.renew', 'Renew Contract')}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
};
