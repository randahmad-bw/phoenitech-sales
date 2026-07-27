import React, { useState } from 'react';
import { useEmployees, useEmployeeStats, useEmployeeMutations } from '@/hooks/queries';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Card } from '@/components/ui/Card';
import { formatCurrency, formatDate } from '@/utils';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Plus, Search, Edit2, Trash2, BarChart2, AlertTriangle, Palette, Camera, Briefcase, Crown, Code } from 'lucide-react';
import { useTranslation } from 'react-i18next';

// Department Definitions
const DEPARTMENTS = [
  { id: 'design', label_ar: 'تصميم', label_en: 'Design', icon: Palette, color: 'bg-purple-500/15 text-purple-400 border-purple-500/30' },
  { id: 'photography', label_ar: 'تصوير', label_en: 'Photography', icon: Camera, color: 'bg-blue-500/15 text-blue-400 border-blue-500/30' },
  { id: 'sales', label_ar: 'مبيعات', label_en: 'Sales', icon: Briefcase, color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' },
  { id: 'dev', label_ar: 'تطوير', label_en: 'Development', icon: Code, color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/30' },
  { id: 'management', label_ar: 'إدارة', label_en: 'Management', icon: Crown, color: 'bg-amber-500/15 text-amber-400 border-amber-500/30' },
];

const employeeSchema = z.object({
  name: z.string().min(2, { message: 'الاسم يجب أن يكون حرفين على الأقل.' }),
  email: z.string().email({ message: 'البريد الإلكتروني غير صحيح.' }).nullable().or(z.literal('')),
  phone: z.string().nullable().or(z.literal('')),
  department: z.string().nullable().or(z.literal('')),
  employment_date: z.string().nullable().or(z.literal('')),
});

type EmployeeFormFields = z.infer<typeof employeeSchema>;

export const EmployeesPage: React.FC = () => {
  const { t, i18n } = useTranslation();
  const isAr = i18n.language === 'ar';
  const [search, setSearch] = useState('');
  const [selectedDeptFilter, setSelectedDeptFilter] = useState<string>('all');
  const { data: employeesRes, isLoading } = useEmployees({ search });
  const { create, update, remove } = useEmployeeMutations();

  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [statsId, setStatsId] = useState<number | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const { register, handleSubmit, reset, setValue, watch, formState: { errors } } = useForm<EmployeeFormFields>({
    resolver: zodResolver(employeeSchema),
    defaultValues: { department: 'design' },
  });

  const selectedDeptValue = watch('department');

  const onSubmit = async (data: EmployeeFormFields) => {
    const payload = {
      name: data.name,
      email: data.email || null,
      phone: data.phone || null,
      department: data.department || 'design',
      employment_date: data.employment_date || null,
    };
    try {
      if (editingId) {
        await update.mutateAsync({ id: editingId, payload });
      } else {
        await create.mutateAsync(payload);
      }
      setFormOpen(false);
      reset();
    } catch (_) {
      // errors shown via form
    }
  };

  const handleEdit = (employee: any) => {
    setEditingId(employee.id);
    setValue('name', employee.name);
    setValue('email', employee.email || '');
    setValue('phone', employee.phone || '');
    setValue('department', employee.department || 'design');
    setValue('employment_date', employee.employment_date ? employee.employment_date.substring(0, 10) : '');
    setFormOpen(true);
  };

  const openDelete = (id: number) => {
    setDeleteError(null);
    setDeleteId(id);
  };

  const handleDeleteConfirm = async () => {
    if (!deleteId) return;
    setDeleteError(null);
    try {
      await remove.mutateAsync(deleteId);
      setDeleteId(null);
    } catch (err: any) {
      const errorCode = err?.response?.data?.error_code;
      if (errorCode === 'EMPLOYEE_HAS_ACTIVE_CONTRACTS') {
        setDeleteError('لا يمكن حذف هذا الموظف لأن لديه عقوداً نشطة أو موقّعة. أنهِ عقوده أولاً ثم أعد المحاولة.');
      } else {
        setDeleteError('حدث خطأ غير متوقع. يرجى المحاولة مجدداً.');
      }
    }
  };

  // Stats Modal Component
  const EmployeeStatsModal: React.FC<{ id: number }> = ({ id }) => {
    const { data: stats, isLoading: statsLoading } = useEmployeeStats(id);

    if (statsLoading) return (
      <div className="flex items-center justify-center py-8 text-text-muted text-sm">
        جاري التحميل...
      </div>
    );
    if (!stats) return (
      <div className="text-center py-6 text-sm text-danger-text">
        تعذّر تحميل الإحصائيات.
      </div>
    );

    return (
      <div className="grid grid-cols-2 gap-4 mt-2">
        {[
          { label: t('employee.contracts_count', 'العقود'), value: stats.total_contracts, color: 'text-text' },
          { label: t('employee.total_value', 'إجمالي قيمة العقود'), value: formatCurrency(stats.total_value), color: 'text-primary-text' },
          { label: t('employee.total_paid', 'إجمالي المحصّل'), value: formatCurrency(stats.total_paid), color: 'text-success-text' },
          { label: t('employee.remaining', 'المتبقي'), value: formatCurrency(stats.remaining), color: 'text-danger-text' },
          { label: t('employee.avg_value', 'متوسط قيمة العقد'), value: formatCurrency(stats.avg_value), color: 'text-text' },
        ].map((stat, i) => (
          <Card key={i} className="!p-4">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider mb-1">{stat.label}</div>
            <div className={`text-xl font-black mt-1 ${stat.color}`}>{stat.value}</div>
          </Card>
        ))}
      </div>
    );
  };

  const columns = [
    {
      key: 'name',
      header: t('common.name', 'الاسم'),
      render: (row: any) => (
        <span className="font-semibold text-text">{row.name}</span>
      ),
    },
    {
      key: 'department',
      header: isAr ? 'القسم / التخصص' : 'Department',
      render: (row: any) => {
        const dept = DEPARTMENTS.find(d => d.id === row.department) || DEPARTMENTS[0];
        const Icon = dept.icon;
        return (
          <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border ${dept.color}`}>
            <Icon size={13} />
            {isAr ? dept.label_ar : dept.label_en}
          </span>
        );
      },
    },
    {
      key: 'email',
      header: t('common.email', 'البريد الإلكتروني'),
      render: (row: any) => (
        <span className="text-text-muted text-xs">{row.email || '—'}</span>
      ),
    },
    {
      key: 'phone',
      header: t('common.phone', 'الهاتف'),
      render: (row: any) => (
        <span className="text-text-muted text-xs">{row.phone || '—'}</span>
      ),
    },
    {
      key: 'employment_date',
      header: t('employee.employment_date', 'تاريخ التعيين'),
      render: (row: any) => (
        <span className="text-text-muted text-xs">{formatDate(row.employment_date)}</span>
      ),
    },
    {
      key: 'contracts_count',
      header: t('employee.contracts_count', 'العقود'),
      className: 'text-center',
      render: (row: any) => (
        <span className="font-bold text-primary-text">{row.contracts_count ?? 0}</span>
      ),
    },
    {
      key: 'actions',
      header: t('common.actions', 'إجراءات'),
      className: 'text-end',
      render: (row: any) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <button
            title="الإحصائيات"
            onClick={() => setStatsId(row.id)}
            className="p-2 rounded-lg text-info-text hover:bg-info-bg transition-colors"
          >
            <BarChart2 size={15} />
          </button>
          <button
            title="تعديل"
            onClick={() => handleEdit(row)}
            className="p-2 rounded-lg text-primary-text hover:bg-primary-bg transition-colors"
          >
            <Edit2 size={15} />
          </button>
          <button
            title="حذف"
            onClick={() => openDelete(row.id)}
            className="p-2 rounded-lg text-danger-text hover:bg-danger-bg transition-colors"
          >
            <Trash2 size={15} />
          </button>
        </div>
      ),
    },
  ];

  const filteredEmployees = (employeesRes?.data || []).filter((emp: any) => {
    if (selectedDeptFilter === 'all') return true;
    return emp.department === selectedDeptFilter;
  });

  return (
    <div className="flex flex-col gap-6 animate-fade-in">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-black text-text tracking-tight">
            {t('employee.title', 'الموظفون وفريق العمل')}
          </h1>
          <p className="text-xs text-text-muted mt-1">
            {t('employee.subtitle', 'إدارة بيانات الموظفين والأقسام ومتابعة أدائهم.')}
          </p>
        </div>
        <Button
          onClick={() => { setEditingId(null); reset({ department: 'design' }); setFormOpen(true); }}
          className="flex items-center gap-1.5"
        >
          <Plus size={16} />
          {t('employee.add', 'إضافة موظف')}
        </Button>
      </div>

      {/* Filters Card */}
      <Card className="!p-4 flex flex-col md:flex-row items-center justify-between gap-4">
        <div className="relative max-w-sm w-full">
          <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-text-muted pointer-events-none" />
          <input
            type="text"
            placeholder={t('employee.search_placeholder', 'ابحث بالاسم أو البريد أو الهاتف...')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="input-field ps-10"
          />
        </div>

        {/* Department Filter Pills */}
        <div className="flex items-center gap-1.5 flex-wrap">
          <button
            onClick={() => setSelectedDeptFilter('all')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
              selectedDeptFilter === 'all'
                ? 'bg-primary-500 text-white shadow-sm'
                : 'bg-surface-lighter text-text-muted hover:text-text'
            }`}
          >
            {isAr ? 'الكل' : 'All'}
          </button>
          {DEPARTMENTS.map(d => {
            const Icon = d.icon;
            return (
              <button
                key={d.id}
                onClick={() => setSelectedDeptFilter(d.id)}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                  selectedDeptFilter === d.id
                    ? 'bg-primary-500 text-white shadow-sm'
                    : 'bg-surface-lighter text-text-muted hover:text-text'
                }`}
              >
                <Icon size={13} />
                {isAr ? d.label_ar : d.label_en}
              </button>
            );
          })}
        </div>
      </Card>

      {/* Table */}
      <Table
        columns={columns}
        data={filteredEmployees}
        isLoading={isLoading}
      />

      {/* Create / Edit Modal */}
      <Modal
        isOpen={formOpen}
        onClose={() => { setFormOpen(false); reset(); }}
        title={editingId ? t('employee.edit', 'تعديل الموظف') : t('employee.add', 'إضافة موظف')}
      >
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <Input
            label={t('employee.full_name', 'الاسم الكامل')}
            error={errors.name?.message}
            {...register('name')}
          />

          {/* Professional Department Selection */}
          <div>
            <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">
              {isAr ? 'القسم / التخصص الوظيفي' : 'Department'}
            </label>
            <div className="grid grid-cols-1 gap-2">
              {DEPARTMENTS.map(d => {
                const Icon = d.icon;
                const isSelected = selectedDeptValue === d.id;
                return (
                  <label
                    key={d.id}
                    className={`flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all ${
                      isSelected
                        ? 'border-primary-500 bg-primary-500/10 shadow-sm'
                        : 'border-border bg-surface-lighter/50 hover:bg-surface-lighter'
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <div className={`p-2 rounded-lg ${d.color}`}>
                        <Icon size={16} />
                      </div>
                      <span className="text-sm font-semibold text-text">
                        {isAr ? d.label_ar : d.label_en}
                      </span>
                    </div>
                    <input
                      type="radio"
                      value={d.id}
                      {...register('department')}
                      className="w-4 h-4 accent-primary-500"
                    />
                  </label>
                );
              })}
            </div>
          </div>

          <Input
            label={t('employee.email', 'البريد الإلكتروني (اختياري)')}
            type="email"
            error={errors.email?.message}
            {...register('email')}
          />
          <Input
            label={t('employee.phone', 'رقم الهاتف (اختياري)')}
            error={errors.phone?.message}
            {...register('phone')}
          />
          <Input
            label={t('employee.employment_date', 'تاريخ التعيين (اختياري)')}
            type="date"
            error={errors.employment_date?.message}
            {...register('employment_date')}
          />
          <div className="flex justify-end gap-3 border-t border-border pt-4 mt-2">
            <Button variant="ghost" type="button" onClick={() => { setFormOpen(false); reset(); }}>
              {t('common.cancel', 'إلغاء')}
            </Button>
            <Button type="submit" isLoading={create.isPending || update.isPending}>
              {t('common.save', 'حفظ')}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Stats Modal */}
      <Modal
        isOpen={statsId !== null}
        onClose={() => setStatsId(null)}
        title={t('employee.stats_title', 'إحصائيات الموظف')}
        size="md"
      >
        {statsId && <EmployeeStatsModal id={statsId} />}
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={deleteId !== null}
        onClose={() => { setDeleteId(null); setDeleteError(null); }}
        title={t('employee.delete', 'حذف الموظف')}
        size="sm"
      >
        <div className="flex flex-col gap-4">
          {deleteError ? (
            <div className="flex items-start gap-3 p-4 bg-danger-bg border border-danger-text/20 rounded-xl">
              <AlertTriangle size={18} className="text-danger-text mt-0.5 shrink-0" />
              <p className="text-sm text-danger-text leading-relaxed">{deleteError}</p>
            </div>
          ) : (
            <p className="text-sm text-text-muted leading-relaxed">
              {t('common.confirm_delete_employee', 'سيتم حذف الموظف نهائياً. هذا الإجراء لا يمكن التراجع عنه.')}
            </p>
          )}
          <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
            <Button
              variant="ghost"
              onClick={() => { setDeleteId(null); setDeleteError(null); }}
            >
              {deleteError ? 'إغلاق' : t('common.no', 'إلغاء')}
            </Button>
            {!deleteError && (
              <Button
                variant="danger"
                onClick={handleDeleteConfirm}
                isLoading={remove.isPending}
              >
                {t('common.yes', 'نعم، احذف')}
              </Button>
            )}
          </div>
        </div>
      </Modal>
    </div>
  );
};
