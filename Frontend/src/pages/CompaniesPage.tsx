import React, { useState } from 'react';
import { useCompanies, useEmployees, useCompanyMutations, useCompany, useContactMutations, useContacts } from '@/hooks/queries';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { Modal } from '@/components/ui/Modal';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Card } from '@/components/ui/Card';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Plus, Search, Edit2, Trash2, Eye, UserPlus, Phone, User } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const companySchema = z.object({
  name: z.string().min(2, { message: 'اسم الشركة مطلوب (حرفين على الأقل).' }),
  client_name: z.string().nullable().or(z.literal('')),
  phone: z.string().nullable().or(z.literal('')),
  activity: z.string().nullable().or(z.literal('')),
  address: z.string().nullable().or(z.literal('')),
  employee_id: z.string().nullable().or(z.literal('')),
  notes: z.string().nullable().or(z.literal('')),
});

const contactSchema = z.object({
  name: z.string().min(2, { message: 'Contact name is required.' }),
  position: z.string().nullable().or(z.literal('')),
  mobile: z.string().nullable().or(z.literal('')),
  notes: z.string().nullable().or(z.literal('')),
});

type CompanyFormFields = z.infer<typeof companySchema>;
type ContactFormFields = z.infer<typeof contactSchema>;

export const CompaniesPage: React.FC = () => {
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const { data: companiesRes, isLoading } = useCompanies({ search });
  const { data: employeesRes } = useEmployees({ per_page: 100 });
  const { create, update, remove } = useCompanyMutations();

  // Modal / Dialogue States
  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [viewCompanyId, setViewCompanyId] = useState<number | null>(null);

  const { register, handleSubmit, reset, setValue, formState: { errors } } = useForm<CompanyFormFields>({
    resolver: zodResolver(companySchema),
  });

  const onSubmit = async (data: CompanyFormFields) => {
    const payload = {
      name: data.name,
      client_name: data.client_name || null,
      phone: data.phone || null,
      activity: data.activity || null,
      address: data.address || null,
      employee_id: data.employee_id ? parseInt(data.employee_id) : null,
      notes: data.notes || null,
    };

    if (editingId) {
      await update.mutateAsync({ id: editingId, payload });
    } else {
      await create.mutateAsync(payload);
    }
    setFormOpen(false);
    reset();
  };

  const handleEdit = (company: any) => {
    setEditingId(company.id);
    setValue('name', company.name);
    setValue('client_name', company.client_name || '');
    setValue('phone', company.phone || '');
    setValue('activity', company.activity || '');
    setValue('address', company.address || '');
    setValue('employee_id', company.employee ? company.employee.id.toString() : '');
    setValue('notes', company.notes || '');
    setFormOpen(true);
  };

  const handleDeleteConfirm = async () => {
    if (deleteId) {
      await remove.mutateAsync(deleteId);
      setDeleteId(null);
    }
  };

  // Contacts Sub-Component inside View Panel
  const ContactsManager: React.FC<{ companyId: number }> = ({ companyId }) => {
    const { data: contacts, isLoading: contactsLoading } = useContacts(companyId);
    const { create: createContact, update: updateContact, remove: removeContact } = useContactMutations(companyId);
    
    const [contactFormOpen, setContactFormOpen] = useState(false);
    const [editingContactId, setEditingContactId] = useState<number | null>(null);

    const { register: regContact, handleSubmit: handleContactSubmit, reset: resetContact, setValue: setContactValue } = useForm<ContactFormFields>({
      resolver: zodResolver(contactSchema),
    });

    const onContactSubmit = async (data: ContactFormFields) => {
      const payload = {
        name: data.name,
        position: data.position || null,
        mobile: data.mobile || null,
        notes: data.notes || null,
      };

      if (editingContactId) {
        await updateContact.mutateAsync({ id: editingContactId, payload });
      } else {
        await createContact.mutateAsync(payload);
      }
      setContactFormOpen(false);
      resetContact();
      setEditingContactId(null);
    };

    const handleEditContact = (c: any) => {
      setEditingContactId(c.id);
      setContactValue('name', c.name);
      setContactValue('position', c.position || '');
      setContactValue('mobile', c.mobile || '');
      setContactValue('notes', c.notes || '');
      setContactFormOpen(true);
    };

    const columns = [
      { key: 'name', header: t('common.name', 'Contact Name') },
      { key: 'position', header: t('contact.position', 'Position'), render: (row: any) => row.position || '—' },
      { key: 'mobile', header: t('contact.mobile', 'Mobile'), render: (row: any) => row.mobile || '—' },
      { key: 'actions', header: t('common.actions', 'Actions'), className: 'text-end', render: (row: any) => (
        <div className="flex justify-end gap-2">
          <Button variant="ghost" size="sm" onClick={() => handleEditContact(row)}>
            <Edit2 size={12} className="text-primary-text" />
          </Button>
          <Button variant="ghost" size="sm" onClick={() => removeContact.mutate(row.id)}>
            <Trash2 size={12} className="text-danger-text" />
          </Button>
        </div>
      )},
    ];

    return (
      <div className="mt-6 border-t border-border pt-6">
        <div className="flex items-center justify-between mb-4">
          <h4 className="text-sm font-bold text-text uppercase tracking-wider">{t('contact.title', 'جهات الاتصال')}</h4>
          <Button
            size="sm"
            onClick={() => {
              setEditingContactId(null);
              resetContact();
              setContactFormOpen(true);
            }}
            className="flex items-center gap-1"
          >
            <UserPlus size={14} />
            {t('contact.add', 'إضافة جهة اتصال')}
          </Button>
        </div>

        <Table
          columns={columns}
          data={contacts || []}
          isLoading={contactsLoading}
        />

        {/* Contact Creation / Modification Form Modal */}
        <Modal
          isOpen={contactFormOpen}
          onClose={() => setContactFormOpen(false)}
          title={editingContactId ? t('common.edit', 'تعديل جهة الاتصال') : t('contact.add', 'إضافة جهة اتصال')}
        >
          <form onSubmit={handleContactSubmit(onContactSubmit)} className="flex flex-col gap-4">
            <Input label={t('common.name', 'الاسم')} {...regContact('name')} />
            <Input label={t('contact.position', 'المنصب')} {...regContact('position')} />
            <Input label={t('contact.mobile', 'رقم الموبايل')} {...regContact('mobile')} />
            <Textarea label={t('common.notes', 'ملاحظات')} {...regContact('notes')} />
            <div className="flex justify-end gap-3 border-t border-border pt-4 mt-2">
              <Button variant="ghost" type="button" onClick={() => setContactFormOpen(false)}>
                {t('common.cancel', 'إلغاء')}
              </Button>
              <Button type="submit" isLoading={createContact.isPending || updateContact.isPending}>
                {t('common.save', 'حفظ')}
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    );
  };

  // View Company Details modal component
  const CompanyDetailsModal: React.FC<{ id: number }> = ({ id }) => {
    const { data: company, isLoading: companyLoading } = useCompany(id);

    if (companyLoading) return <div className="text-center py-6 text-sm text-text-muted">جاري التحميل...</div>;
    if (!company) return <div className="text-center py-6 text-sm text-red-500">فشل تحميل البيانات.</div>;

    return (
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">اسم الشركة</div>
            <div className="text-sm font-semibold text-text mt-1">{company.name}</div>
          </div>
          <div>
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">اسم العميل</div>
            <div className="text-sm font-semibold text-text mt-1 flex items-center gap-1.5">
              {company.client_name ? (
                <><User size={13} className="text-primary-400" />{company.client_name}</>
              ) : '—'}
            </div>
          </div>
          <div>
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">رقم الهاتف</div>
            <div className="text-sm font-semibold text-text mt-1 flex items-center gap-1.5">
              {company.phone ? (
                <><Phone size={13} className="text-emerald-400" />{company.phone}</>
              ) : '—'}
            </div>
          </div>
          <div className="col-span-2">
            <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">ملاحظات</div>
            <div className="text-sm text-text-muted mt-1 bg-surface-lighter p-3 rounded-lg border border-border">{company.notes || '—'}</div>
          </div>
        </div>

        {/* Contacts section */}
        <ContactsManager companyId={id} />
      </div>
    );
  };

  const employeeOptions = [
    { value: '', label: '— اختر مدير الحساب —' },
    ...(employeesRes?.data || []).map((emp: any) => ({
      value: emp.id.toString(),
      label: emp.name,
    })),
  ];

  const columns = [
    { key: 'name', header: 'اسم الشركة' },
    { key: 'client_name', header: 'اسم العميل', render: (row: any) => (
      <span className="flex items-center gap-1.5">
        {row.client_name ? <><User size={13} className="text-primary-400 shrink-0" />{row.client_name}</> : '—'}
      </span>
    )},
    { key: 'phone', header: 'الهاتف', render: (row: any) => (
      <span className="flex items-center gap-1.5">
        {row.phone ? <><Phone size={13} className="text-emerald-400 shrink-0" />{row.phone}</> : '—'}
      </span>
    )},
    { key: 'actions', header: 'إجراءات', className: 'text-end', render: (row: any) => (
      <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
        <Button variant="ghost" size="sm" onClick={() => setViewCompanyId(row.id)}>
          <Eye size={14} className="text-info-text" />
        </Button>
        <Button variant="ghost" size="sm" onClick={() => handleEdit(row)}>
          <Edit2 size={14} className="text-primary-text" />
        </Button>
        <Button variant="ghost" size="sm" onClick={() => setDeleteId(row.id)}>
          <Trash2 size={14} className="text-danger-text" />
        </Button>
      </div>
    )},
  ];

  return (
    <div className="flex flex-col gap-6">
      {/* Title & Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-black text-text uppercase tracking-wider">عملاؤنا</h1>
          <p className="text-xs text-text-muted mt-1">إدارة العملاء والشركات المتعاقدة مع فوني تيك.</p>
        </div>
        <Button
          onClick={() => {
            setEditingId(null);
            reset();
            setFormOpen(true);
          }}
          className="flex items-center gap-1.5"
        >
          <Plus size={16} />
          إضافة عميل
        </Button>
      </div>

      {/* Filter / Search bar */}
      <Card className="p-4 border-border bg-surface-light">
        <div className="relative max-w-md">
          <Search className="absolute start-3 top-3.5 h-4 w-4 text-text-muted" />
          <input
            type="text"
            placeholder="بحث بالاسم أو رقم الهاتف..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full bg-surface border border-border rounded-lg py-2.5 ps-10 pe-4 text-sm text-text placeholder-text-muted focus:outline-none focus:ring-1 focus:ring-blue-500"
          />
        </div>
      </Card>

      {/* Data Table */}
      <Table
        columns={columns}
        data={companiesRes?.data || []}
        isLoading={isLoading}
      />

      {/* Create / Edit Company Modal */}
      <Modal
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        title={editingId ? 'تعديل بيانات العميل' : 'إضافة عميل جديد'}
      >
        <form onSubmit={handleSubmit(onSubmit)} className="grid grid-cols-2 gap-4">
          <div className="col-span-2">
            <Input label="اسم الشركة" placeholder="مثال: فوني تيك" error={errors.name?.message} {...register('name')} />
          </div>
          <Input label="اسم العميل / الشخص المسؤول" placeholder="مثال: أحمد محمد" error={errors.client_name?.message} {...register('client_name')} />
          <Input label="رقم الهاتف" placeholder="مثال: 0912345678" error={errors.phone?.message} {...register('phone')} />
          <div className="col-span-2">
            <Textarea label="ملاحظات" error={errors.notes?.message} {...register('notes')} />
          </div>
          <div className="col-span-2 flex justify-end gap-3 border-t border-border pt-4 mt-2">
            <Button variant="ghost" type="button" onClick={() => setFormOpen(false)}>
              إلغاء
            </Button>
            <Button type="submit" isLoading={create.isPending || update.isPending}>
              حفظ
            </Button>
          </div>
        </form>
      </Modal>

      {/* View Company details Modal */}
      <Modal
        isOpen={viewCompanyId !== null}
        onClose={() => setViewCompanyId(null)}
        title="تفاصيل العميل"
        size="lg"
      >
        {viewCompanyId && <CompanyDetailsModal id={viewCompanyId} />}
      </Modal>

      {/* Confirm Deletion */}
      <ConfirmDialog
        isOpen={deleteId !== null}
        onClose={() => setDeleteId(null)}
        onConfirm={handleDeleteConfirm}
        title="حذف العميل"
        message="هل أنت متأكد من حذف هذا العميل؟ لا يمكن حذف عميل لديه عقود نشطة."
        isLoading={remove.isPending}
      />
    </div>
  );
};
