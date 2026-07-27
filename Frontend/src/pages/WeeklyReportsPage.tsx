import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '@/store/authStore';
import { useWeeklyReports, useWeeklyReportMutations, useEmployees } from '@/hooks/queries';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { Spinner } from '@/components/ui/Spinner';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { formatDate } from '@/utils';
import {
  ClipboardList,
  Plus,
  Trash2,
  Calendar,
  Users,
  CheckCircle,
  Clock,
  TrendingUp,
  ChevronRight,
  ChevronLeft,
  X,
  FileText,
  Percent,
} from 'lucide-react';
import api from '@/lib/axios';

// Helper to get Saturday of the current week (Start of working week in Syria)
const getStartOfWeekDate = (date: Date = new Date()) => {
  const d = new Date(date);
  const day = d.getDay(); // 0: Sun, 1: Mon, ..., 6: Sat
  // Distance to Saturday (6)
  const offset = day === 6 ? 0 : day + 1;
  d.setDate(d.getDate() - offset);
  return d.toISOString().split('T')[0];
};

export const WeeklyReportsPage: React.FC = () => {
  const { t, i18n } = useTranslation();
  const { user } = useAuthStore();
  const isRtl = i18n.language === 'ar';
  
  const isEmployee = !!user?.employee;
  const employeeId = user?.employee?.id;

  // Filters state
  const [filterEmployee, setFilterEmployee] = useState<string>('');
  const [filterDate, setFilterDate] = useState<string>('');
  const [page, setPage] = useState(1);

  // Queries
  const { data: reportsData, isLoading: reportsLoading, refetch } = useWeeklyReports({
    employee_id: isEmployee ? employeeId : (filterEmployee || undefined),
    week_start_date: filterDate || undefined,
    page,
    per_page: 10,
  }) as any;

  const { data: employeesData } = useEmployees({ per_page: 100 }) as any;
  const { create, remove } = useWeeklyReportMutations();

  // Modals state
  const [isSubmitOpen, setIsSubmitOpen] = useState(false);
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [selectedReport, setSelectedReport] = useState<any>(null);
  const [deleteReportId, setDeleteReportId] = useState<number | null>(null);

  // Step-by-step submission state
  const [activeStep, setActiveStep] = useState(1);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitLoading, setSubmitLoading] = useState(false);

  // Form states
  const [weekStartDate, setWeekStartDate] = useState(getStartOfWeekDate());
  const [kpis, setKpis] = useState({
    total_contacted: 0,
    doctors: 0,
    medical_centers: 0,
    schools: 0,
    restaurants_cafeterias: 0,
    pending_decision: 0,
    price_offers: 0,
  });
  
  // Pipeline
  const [signedDeals, setSignedDeals] = useState<{ name: string; completion_rate: number }[]>([]);
  const [pendingDeals, setPendingDeals] = useState<string[]>([]);
  
  // Plans
  const [followUps, setFollowUps] = useState<string[]>([]);
  const [improvementStrategy, setImprovementStrategy] = useState('');
  const [notes, setNotes] = useState('');

  // Local input managers for dynamic lists
  const [newSignedName, setNewSignedName] = useState('');
  const [newSignedRate, setNewSignedRate] = useState(100);
  const [newPendingName, setNewPendingName] = useState('');
  const [newFollowUp, setNewFollowUp] = useState('');

  // Reset form
  const resetForm = () => {
    setWeekStartDate(getStartOfWeekDate());
    setKpis({
      total_contacted: 0,
      doctors: 0,
      medical_centers: 0,
      schools: 0,
      restaurants_cafeterias: 0,
      pending_decision: 0,
      price_offers: 0,
    });
    setSignedDeals([]);
    setPendingDeals([]);
    setFollowUps([]);
    setImprovementStrategy('');
    setNotes('');
    setNewSignedName('');
    setNewSignedRate(100);
    setNewPendingName('');
    setNewFollowUp('');
    setActiveStep(1);
    setSubmitError(null);
  };

  const handleOpenSubmit = () => {
    resetForm();
    setIsSubmitOpen(true);
  };

  // Add items functions
  const addSignedDeal = () => {
    if (!newSignedName.trim()) return;
    setSignedDeals([...signedDeals, { name: newSignedName.trim(), completion_rate: newSignedRate }]);
    setNewSignedName('');
    setNewSignedRate(100);
  };

  const removeSignedDeal = (index: number) => {
    setSignedDeals(signedDeals.filter((_, i) => i !== index));
  };

  const addPendingDeal = () => {
    if (!newPendingName.trim()) return;
    setPendingDeals([...pendingDeals, newPendingName.trim()]);
    setNewPendingName('');
  };

  const removePendingDeal = (index: number) => {
    setPendingDeals(pendingDeals.filter((_, i) => i !== index));
  };

  const addFollowUp = () => {
    if (!newFollowUp.trim()) return;
    setFollowUps([...followUps, newFollowUp.trim()]);
    setNewFollowUp('');
  };

  const removeFollowUp = (index: number) => {
    setFollowUps(followUps.filter((_, i) => i !== index));
  };

  const handleNextStep = () => {
    if (activeStep === 1) {
      if (!weekStartDate) {
        setSubmitError(isRtl ? 'يرجى تحديد تاريخ بداية الأسبوع.' : 'Please select a week start date.');
        return;
      }
      if (kpis.total_contacted < 0) {
        setSubmitError(isRtl ? 'إجمالي العملاء لا يمكن أن يكون سالباً.' : 'Total contacted cannot be negative.');
        return;
      }
    }
    setSubmitError(null);
    setActiveStep(activeStep + 1);
  };

  const handleBackStep = () => {
    setSubmitError(null);
    setActiveStep(activeStep - 1);
  };

  // Form submission
  const handleSubmitReport = async () => {
    if (!improvementStrategy.trim()) {
      setSubmitError(isRtl ? 'يرجى كتابة استراتيجية التحسين.' : 'Please enter the improvement strategy.');
      return;
    }

    setSubmitLoading(true);
    setSubmitError(null);

    const payload = {
      week_start_date: weekStartDate,
      kpis,
      pipeline: {
        signed: signedDeals,
        pending: pendingDeals,
      },
      next_plan: {
        follow_ups: followUps,
        improvement_strategy: improvementStrategy.trim(),
      },
      notes: notes.trim() || null,
    };

    create.mutate(payload, {
      onSuccess: () => {
        setSubmitLoading(false);
        setIsSubmitOpen(false);
        refetch();
        resetForm();
      },
      onError: (err: any) => {
        setSubmitLoading(false);
        const errMsg = err.response?.data?.message || err.message;
        setSubmitError(errMsg || (isRtl ? 'حدث خطأ أثناء الإرسال.' : 'An error occurred during submission.'));
      },
    });
  };

  const handleDeleteReport = () => {
    if (deleteReportId) {
      remove.mutate(deleteReportId, {
        onSuccess: () => {
          setDeleteReportId(null);
          refetch();
        },
      });
    }
  };

  const employeesOptions = [
    { value: '', label: isRtl ? 'جميع الموظفين' : 'All Employees' },
    ...(employeesData?.data?.map((emp: any) => ({
      value: emp.id.toString(),
      label: emp.name,
    })) || []),
  ];

  return (
    <div className="flex flex-col gap-6">
      {/* Title Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-black text-text uppercase tracking-wider">
            {isEmployee ? t('weekly_reports.title', 'التقارير الأسبوعية') : t('weekly_reports.admin_title', 'تقارير المبيعات الأسبوعية')}
          </h1>
          <p className="text-xs text-text-muted mt-1">
            {isEmployee 
              ? t('weekly_reports.subtitle', 'سجل أداءك الأسبوعي وقدّم تقاريرك الدورية للإدارة بسهولة.')
              : t('weekly_reports.admin_subtitle', 'متابعة وتقييم أداء فريق المبيعات الأسبوعي ومراجعة خططهم القادمة.')
            }
          </p>
        </div>
        {isEmployee && (
          <Button onClick={handleOpenSubmit} className="flex items-center gap-1.5 self-start sm:self-auto btn-primary">
            <Plus size={16} />
            {isRtl ? 'تقديم تقرير أسبوعي' : 'Submit Weekly Report'}
          </Button>
        )}
      </div>

      {/* Admin Filters */}
      {!isEmployee && (
        <Card className="p-5 border-border bg-surface-light/40">
          <div className="flex flex-wrap items-center gap-4">
            <div className="w-56">
              <Select
                label={isRtl ? 'تصفية بالموظف' : 'Filter by Employee'}
                options={employeesOptions}
                value={filterEmployee}
                onChange={(e) => { setFilterEmployee(e.target.value); setPage(1); }}
              />
            </div>
            <div className="w-56">
              <Input
                label={isRtl ? 'تصفية بالتاريخ (بداية الأسبوع)' : 'Filter by Week Start'}
                type="date"
                value={filterDate}
                onChange={(e) => { setFilterDate(e.target.value); setPage(1); }}
              />
            </div>
            {(filterEmployee || filterDate) && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => { setFilterEmployee(''); setFilterDate(''); setPage(1); }}
                className="mt-5 text-xs text-text-muted hover:text-text"
              >
                {isRtl ? 'إعادة تعيين' : 'Reset'}
              </Button>
            )}
          </div>
        </Card>
      )}

      {/* Reports List */}
      {reportsLoading ? (
        <div className="flex justify-center items-center py-20">
          <Spinner size="lg" />
        </div>
      ) : reportsData?.data?.length === 0 ? (
        <Card className="flex flex-col items-center justify-center text-center py-16 border-dashed border-border bg-surface-light/10">
          <ClipboardList size={48} className="text-text-muted/40 mb-3" />
          <h3 className="font-bold text-base text-text">
            {isRtl ? 'لا توجد تقارير أسبوعية' : 'No weekly reports found'}
          </h3>
          <p className="text-xs text-text-muted mt-1 max-w-sm">
            {isRtl 
              ? 'لم يتم تقديم أي تقارير أسبوعية حتى الآن في هذا القسم.' 
              : 'No weekly reports have been submitted yet under this section.'
            }
          </p>
          {isEmployee && (
            <Button onClick={handleOpenSubmit} size="sm" className="mt-4 btn-primary">
              {isRtl ? 'تقديم أول تقرير أسبوعي' : 'Submit First Report'}
            </Button>
          )}
        </Card>
      ) : (
        <div className="flex flex-col gap-4">
          <div className="overflow-x-auto border border-border rounded-xl bg-surface-light">
            <table className="w-full text-sm text-start">
              <thead className="text-[10px] text-text-muted font-bold uppercase tracking-wider bg-surface-lighter border-b border-border">
                <tr>
                  <th className="px-5 py-4 text-start">
                    {isRtl ? 'بداية الأسبوع' : 'Week Start'}
                  </th>
                  {!isEmployee && (
                    <th className="px-5 py-4 text-start">
                      {isRtl ? 'الموظف' : 'Employee'}
                    </th>
                  )}
                  <th className="px-5 py-4 text-start">
                    {isRtl ? 'العملاء المتواصل معهم' : 'Clients Contacted'}
                  </th>
                  <th className="px-5 py-4 text-start">
                    {isRtl ? 'عروض الأسعار' : 'Price Offers'}
                  </th>
                  <th className="px-5 py-4 text-start">
                    {isRtl ? 'الصفقات الموقعة' : 'Signed Deals'}
                  </th>
                  <th className="px-5 py-4 text-start">
                    {isRtl ? 'تاريخ التقديم' : 'Submitted Date'}
                  </th>
                  <th className="px-5 py-4 text-center">
                    {isRtl ? 'الإجراءات' : 'Actions'}
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {reportsData?.data?.map((report: any) => {
                  const signedCount = report.pipeline?.signed?.length || 0;
                  return (
                    <tr key={report.id} className="hover:bg-surface-lighter/50 transition-colors">
                      <td className="px-5 py-4 font-semibold text-text whitespace-nowrap">
                        <div className="flex items-center gap-2">
                          <Calendar size={14} className="text-primary-500" />
                          {formatDate(report.week_start_date, i18n.language)}
                        </div>
                      </td>
                      {!isEmployee && (
                        <td className="px-5 py-4 font-medium text-text whitespace-nowrap">
                          <div className="flex items-center gap-2">
                            <Users size={14} className="text-text-muted" />
                            {report.employee?.name || '—'}
                          </div>
                        </td>
                      )}
                      <td className="px-5 py-4 text-text-muted">
                        <span className="font-bold text-text text-sm">{report.kpis?.total_contacted || 0}</span> {isRtl ? 'عميل' : 'clients'}
                      </td>
                      <td className="px-5 py-4 text-text-muted">
                        <span className="font-bold text-text text-sm">{report.kpis?.price_offers || 0}</span>
                      </td>
                      <td className="px-5 py-4">
                        {signedCount > 0 ? (
                          <span className="badge badge-active">
                            {signedCount} {isRtl ? 'صفقات' : 'deals'}
                          </span>
                        ) : (
                          <span className="badge badge-draft">—</span>
                        )}
                      </td>
                      <td className="px-5 py-4 text-xs text-text-muted whitespace-nowrap">
                        {formatDate(report.created_at, i18n.language)}
                      </td>
                      <td className="px-5 py-4 text-center whitespace-nowrap">
                        <div className="flex items-center justify-center gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setSelectedReport(report);
                              setIsDetailOpen(true);
                            }}
                            className="text-xs px-2.5 py-1"
                          >
                            {isRtl ? 'عرض التفاصيل' : 'View Details'}
                          </Button>
                          {!isEmployee && (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => setDeleteReportId(report.id)}
                              className="text-xs px-2 py-1 text-danger-text border-danger-text/20 hover:bg-danger-bg/50"
                            >
                              <Trash2 size={13} />
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {reportsData?.meta && reportsData.meta.last_page > 1 && (
            <div className="flex justify-between items-center mt-2 px-1">
              <Button
                variant="outline"
                size="sm"
                disabled={page === 1}
                onClick={() => setPage(page - 1)}
                className="flex items-center gap-1 text-xs"
              >
                {isRtl ? <ChevronRight size={14} /> : <ChevronLeft size={14} />}
                {isRtl ? 'السابق' : 'Previous'}
              </Button>
              <span className="text-xs text-text-muted">
                {isRtl 
                  ? `الصفحة ${reportsData.meta.current_page} من ${reportsData.meta.last_page}`
                  : `Page ${reportsData.meta.current_page} of ${reportsData.meta.last_page}`
                }
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={page === reportsData.meta.last_page}
                onClick={() => setPage(page + 1)}
                className="flex items-center gap-1 text-xs"
              >
                {isRtl ? 'التالي' : 'Next'}
                {isRtl ? <ChevronLeft size={14} /> : <ChevronRight size={14} />}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* DETAIL MODAL */}
      <Modal
        isOpen={isDetailOpen}
        onClose={() => setIsDetailOpen(false)}
        title={isRtl ? ' تفاصيل التقرير الأسبوعي' : 'Weekly Report Details'}
        size="lg"
      >
        {selectedReport && (
          <div className="flex flex-col gap-6 text-start max-h-[80vh] overflow-y-auto pr-1">
            {/* Header info */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-border gap-2">
              <div>
                <span className="text-[10px] text-text-muted font-bold uppercase tracking-wider">
                  {isRtl ? 'بداية الأسبوع المستهدف' : 'Target Week Start'}
                </span>
                <h4 className="text-base font-extrabold text-text flex items-center gap-1.5 mt-0.5">
                  <Calendar size={16} className="text-primary-500" />
                  {formatDate(selectedReport.week_start_date, i18n.language)}
                </h4>
              </div>
              <div className="text-start sm:text-end">
                <span className="text-[10px] text-text-muted font-bold uppercase tracking-wider">
                  {isRtl ? 'اسم الموظف' : 'Employee Name'}
                </span>
                <h4 className="text-base font-bold text-text mt-0.5">
                  {selectedReport.employee?.name || '—'}
                </h4>
              </div>
            </div>

            {/* KPIs Grid */}
            <div className="flex flex-col gap-3">
              <h3 className="text-xs font-black text-text-muted uppercase tracking-wider flex items-center gap-1.5">
                <TrendingUp size={14} className="text-primary-500" />
                {isRtl ? '1. ملخص الأداء الأسبوعي (Weekly KPIs)' : '1. Weekly KPIs'}
              </h3>
              <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3">
                <div className="p-3 border border-border bg-surface-lighter rounded-lg flex flex-col items-center text-center">
                  <div className="text-[10px] text-text-muted font-bold whitespace-nowrap">{isRtl ? 'إجمالي التواصل' : 'Total'}</div>
                  <div className="text-base font-black text-text mt-1">{selectedReport.kpis?.total_contacted || 0}</div>
                </div>
                <div className="p-3 border border-border bg-surface-lighter rounded-lg flex flex-col items-center text-center">
                  <div className="text-[10px] text-text-muted font-bold whitespace-nowrap">{isRtl ? 'أطباء' : 'Doctors'}</div>
                  <div className="text-base font-black text-blue-400 mt-1">{selectedReport.kpis?.doctors || 0}</div>
                </div>
                <div className="p-3 border border-border bg-surface-lighter rounded-lg flex flex-col items-center text-center">
                  <div className="text-[10px] text-text-muted font-bold whitespace-nowrap">{isRtl ? 'مراكز طبية' : 'Centers'}</div>
                  <div className="text-base font-black text-indigo-400 mt-1">{selectedReport.kpis?.medical_centers || 0}</div>
                </div>
                <div className="p-3 border border-border bg-surface-lighter rounded-lg flex flex-col items-center text-center">
                  <div className="text-[10px] text-text-muted font-bold whitespace-nowrap">{isRtl ? 'مدارس' : 'Schools'}</div>
                  <div className="text-base font-black text-purple-400 mt-1">{selectedReport.kpis?.schools || 0}</div>
                </div>
                <div className="p-3 border border-border bg-surface-lighter rounded-lg flex flex-col items-center text-center">
                  <div className="text-[10px] text-text-muted font-bold whitespace-nowrap">{isRtl ? 'مطاعم' : 'Restaurants'}</div>
                  <div className="text-base font-black text-amber-400 mt-1">{selectedReport.kpis?.restaurants_cafeterias || 0}</div>
                </div>
                <div className="p-3 border border-border bg-surface-lighter rounded-lg flex flex-col items-center text-center">
                  <div className="text-[10px] text-text-muted font-bold whitespace-nowrap">{isRtl ? 'بانتظار القرار' : 'Pending Decision'}</div>
                  <div className="text-base font-black text-rose-400 mt-1">{selectedReport.kpis?.pending_decision || 0}</div>
                </div>
                <div className="p-3 border border-border bg-surface-lighter rounded-lg flex flex-col items-center text-center">
                  <div className="text-[10px] text-text-muted font-bold whitespace-nowrap">{isRtl ? 'عروض أسعار' : 'Offers'}</div>
                  <div className="text-base font-black text-emerald-400 mt-1">{selectedReport.kpis?.price_offers || 0}</div>
                </div>
              </div>
            </div>

            {/* Pipeline Section */}
            <div className="flex flex-col gap-3">
              <h3 className="text-xs font-black text-text-muted uppercase tracking-wider flex items-center gap-1.5">
                <ClipboardList size={14} className="text-primary-500" />
                {isRtl ? '2. وضع الصفقات (Pipeline & Probability)' : '2. Pipeline & Probability'}
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Signed Deals */}
                <div className="p-4 border border-border bg-surface-lighter/35 rounded-lg flex flex-col gap-3">
                  <h4 className="text-xs font-bold text-success-text flex items-center gap-1">
                    <CheckCircle size={14} />
                    {isRtl ? 'صفقات تم توقيعها' : 'Deals Signed'}
                  </h4>
                  {selectedReport.pipeline?.signed?.length > 0 ? (
                    <div className="flex flex-col gap-2.5">
                      {selectedReport.pipeline.signed.map((deal: any, index: number) => (
                        <div key={index} className="flex flex-col gap-1">
                          <div className="flex justify-between items-center text-xs font-semibold text-text">
                            <span>{deal.name}</span>
                            <span className="text-success-text">{deal.completion_rate}%</span>
                          </div>
                          <div className="w-full bg-border h-1.5 rounded-full overflow-hidden">
                            <div 
                              className="bg-success-500 h-1.5 rounded-full" 
                              style={{ width: `${deal.completion_rate}%` }}
                            />
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-xs text-text-muted italic">{isRtl ? 'لا توجد صفقات موقعة' : 'No signed deals'}</p>
                  )}
                </div>

                {/* Pending Deals */}
                <div className="p-4 border border-border bg-surface-lighter/35 rounded-lg flex flex-col gap-3">
                  <h4 className="text-xs font-bold text-warning-text flex items-center gap-1">
                    <Clock size={14} />
                    {isRtl ? 'صفقات قيد الانتظار' : 'Deals Pending'}
                  </h4>
                  {selectedReport.pipeline?.pending?.length > 0 ? (
                    <ul className="list-disc list-inside text-xs text-text space-y-1.5">
                      {selectedReport.pipeline.pending.map((deal: string, index: number) => (
                        <li key={index} className="marker:text-warning-text font-medium">{deal}</li>
                      ))}
                    </ul>
                  ) : (
                    <p className="text-xs text-text-muted italic">{isRtl ? 'لا توجد صفقات قيد الانتظار' : 'No pending deals'}</p>
                  )}
                </div>
              </div>
            </div>

            {/* Next Plan Section */}
            <div className="flex flex-col gap-3">
              <h3 className="text-xs font-black text-text-muted uppercase tracking-wider flex items-center gap-1.5">
                <FileText size={14} className="text-primary-500" />
                {isRtl ? '3. الخطة القادمة' : '3. Upcoming Plan'}
              </h3>
              <div className="p-4 border border-border bg-surface-lighter/35 rounded-lg flex flex-col gap-4">
                {/* Follow Ups */}
                <div>
                  <h4 className="text-xs font-bold text-text mb-2">
                    {isRtl ? 'المتابعات:' : 'Follow-ups:'}
                  </h4>
                  {selectedReport.next_plan?.follow_ups?.length > 0 ? (
                    <ul className="list-disc list-inside text-xs text-text-muted space-y-1.5">
                      {selectedReport.next_plan.follow_ups.map((item: string, index: number) => (
                        <li key={index} className="marker:text-primary-500">{item}</li>
                      ))}
                    </ul>
                  ) : (
                    <p className="text-xs text-text-muted italic">{isRtl ? 'لا توجد بنود متابعة محددة' : 'No follow-up items specified'}</p>
                  )}
                </div>

                {/* Improvement Strategy */}
                <div className="border-t border-border/60 pt-3">
                  <h4 className="text-xs font-bold text-text mb-1">
                    {isRtl ? 'استراتيجية التحسين:' : 'Improvement Strategy:'}
                  </h4>
                  <p className="text-xs text-text-muted leading-relaxed whitespace-pre-wrap">
                    {selectedReport.next_plan?.improvement_strategy || '—'}
                  </p>
                </div>
              </div>
            </div>

            {/* Notes Section */}
            {selectedReport.notes && (
              <div className="flex flex-col gap-2">
                <h4 className="text-xs font-bold text-text">
                  {isRtl ? 'ملاحظات إضافية' : 'Additional Notes'}
                </h4>
                <div className="p-3 bg-surface-lighter rounded-lg text-xs text-text-muted leading-relaxed border border-border whitespace-pre-wrap">
                  {selectedReport.notes}
                </div>
              </div>
            )}
            
            <div className="flex justify-end pt-2">
              <Button onClick={() => setIsDetailOpen(false)} size="sm" className="btn-secondary">
                {isRtl ? 'إغلاق' : 'Close'}
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* SUBMISSION MODAL (Multi-step) */}
      <Modal
        isOpen={isSubmitOpen}
        onClose={() => setIsSubmitOpen(false)}
        title={isRtl ? 'إرسال التقرير الأسبوعي للمبيعات' : 'Submit Weekly Sales Report'}
        size="lg"
      >
        <div className="flex flex-col gap-5 text-start">
          {/* Step Indicators */}
          <div className="flex justify-center items-center gap-3 py-1 border-b border-border">
            {[1, 2, 3].map((step) => (
              <React.Fragment key={step}>
                <div className="flex items-center gap-1.5">
                  <div
                    className={`h-6 w-6 rounded-full flex items-center justify-center text-xs font-extrabold transition-colors ${
                      activeStep === step
                        ? 'bg-primary-500 text-white'
                        : activeStep > step
                        ? 'bg-success-500 text-white'
                        : 'bg-surface-lighter text-text-muted border border-border'
                    }`}
                  >
                    {step}
                  </div>
                  <span
                    className={`text-xs font-bold whitespace-nowrap ${
                      activeStep === step ? 'text-primary-500' : 'text-text-muted'
                    }`}
                  >
                    {step === 1 
                      ? (isRtl ? 'المؤشرات' : 'KPIs') 
                      : step === 2 
                      ? (isRtl ? 'الصفقات' : 'Pipeline') 
                      : (isRtl ? 'الخطة القادمة' : 'Next Plan')
                    }
                  </span>
                </div>
                {step < 3 && <div className="h-0.5 w-8 bg-border" />}
              </React.Fragment>
            ))}
          </div>

          {submitError && (
            <div className="p-3 bg-danger-bg text-danger-text rounded-lg text-xs font-semibold flex items-center gap-2 border border-danger-text/10">
              <X size={14} className="shrink-0" />
              <span>{submitError}</span>
            </div>
          )}

          {/* STEP 1: KPIs & Date */}
          {activeStep === 1 && (
            <div className="flex flex-col gap-4">
              <div className="w-full sm:w-64">
                <Input
                  label={isRtl ? 'تاريخ بداية الأسبوع (السبت)' : 'Week Start Date (Saturday)'}
                  type="date"
                  value={weekStartDate}
                  onChange={(e) => setWeekStartDate(e.target.value)}
                  required
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                <Input
                  label={isRtl ? 'إجمالي العملاء المتواصل معهم' : 'Total Contacted Clients'}
                  type="number"
                  min="0"
                  value={kpis.total_contacted}
                  onChange={(e) => setKpis({ ...kpis, total_contacted: parseInt(e.target.value) || 0 })}
                  required
                />
                <Input
                  label={isRtl ? 'أطباء' : 'Doctors'}
                  type="number"
                  min="0"
                  value={kpis.doctors}
                  onChange={(e) => setKpis({ ...kpis, doctors: parseInt(e.target.value) || 0 })}
                  required
                />
                <Input
                  label={isRtl ? 'مراكز طبية' : 'Medical Centers'}
                  type="number"
                  min="0"
                  value={kpis.medical_centers}
                  onChange={(e) => setKpis({ ...kpis, medical_centers: parseInt(e.target.value) || 0 })}
                  required
                />
                <Input
                  label={isRtl ? 'مدارس' : 'Schools'}
                  type="number"
                  min="0"
                  value={kpis.schools}
                  onChange={(e) => setKpis({ ...kpis, schools: parseInt(e.target.value) || 0 })}
                  required
                />
                <Input
                  label={isRtl ? 'مطاعم / كافتيريات' : 'Restaurants / Cafes'}
                  type="number"
                  min="0"
                  value={kpis.restaurants_cafeterias}
                  onChange={(e) => setKpis({ ...kpis, restaurants_cafeterias: parseInt(e.target.value) || 0 })}
                  required
                />
                <Input
                  label={isRtl ? 'حالات بانتظار القرار' : 'Pending Decisions'}
                  type="number"
                  min="0"
                  value={kpis.pending_decision}
                  onChange={(e) => setKpis({ ...kpis, pending_decision: parseInt(e.target.value) || 0 })}
                  required
                />
                <Input
                  label={isRtl ? 'عروض سعر مقدمة' : 'Price Offers Submitted'}
                  type="number"
                  min="0"
                  value={kpis.price_offers}
                  onChange={(e) => setKpis({ ...kpis, price_offers: parseInt(e.target.value) || 0 })}
                  required
                />
              </div>
            </div>
          )}

          {/* STEP 2: Pipeline */}
          {activeStep === 2 && (
            <div className="flex flex-col gap-5">
              {/* Signed Deals Addition */}
              <div className="p-4 border border-border bg-surface-lighter/30 rounded-lg flex flex-col gap-3">
                <h4 className="text-xs font-bold text-success-text">
                  {isRtl ? 'إضافة الصفقات الموقعة' : 'Add Signed Deals'}
                </h4>
                <div className="flex flex-col sm:flex-row gap-3 items-end">
                  <div className="flex-1">
                    <Input
                      label={isRtl ? 'اسم العميل / الصفقة' : 'Deal / Client Name'}
                      value={newSignedName}
                      onChange={(e) => setNewSignedName(e.target.value)}
                      placeholder="e.g. Agalya, المول الصيني"
                    />
                  </div>
                  <div className="w-full sm:w-32">
                    <Input
                      label={isRtl ? 'نسبة الإتمام %' : 'Completion %'}
                      type="number"
                      min="0"
                      max="100"
                      value={newSignedRate}
                      onChange={(e) => setNewSignedRate(parseInt(e.target.value) || 0)}
                    />
                  </div>
                  <Button onClick={addSignedDeal} size="sm" className="btn-secondary h-[42px] px-3">
                    <Plus size={16} />
                  </Button>
                </div>
                
                {/* List of Signed Deals */}
                {signedDeals.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {signedDeals.map((deal, idx) => (
                      <span key={idx} className="badge badge-active py-1.5 px-3 flex items-center gap-2">
                        <span>{deal.name} ({deal.completion_rate}%)</span>
                        <X size={12} className="cursor-pointer text-success-text hover:text-red-400" onClick={() => removeSignedDeal(idx)} />
                      </span>
                    ))}
                  </div>
                )}
              </div>

              {/* Pending Deals Addition */}
              <div className="p-4 border border-border bg-surface-lighter/30 rounded-lg flex flex-col gap-3">
                <h4 className="text-xs font-bold text-warning-text">
                  {isRtl ? 'إضافة الصفقات قيد الانتظار' : 'Add Pending Deals'}
                </h4>
                <div className="flex gap-3 items-end">
                  <div className="flex-1">
                    <Input
                      label={isRtl ? 'اسم العميل قيد الانتظار' : 'Pending Deal Client Name'}
                      value={newPendingName}
                      onChange={(e) => setNewPendingName(e.target.value)}
                      placeholder="e.g. Mango, Obada Dental"
                    />
                  </div>
                  <Button onClick={addPendingDeal} size="sm" className="btn-secondary h-[42px] px-3">
                    <Plus size={16} />
                  </Button>
                </div>

                {/* List of Pending Deals */}
                {pendingDeals.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {pendingDeals.map((deal, idx) => (
                      <span key={idx} className="badge badge-draft py-1.5 px-3 flex items-center gap-2">
                        <span>{deal}</span>
                        <X size={12} className="cursor-pointer text-text-muted hover:text-red-400" onClick={() => removePendingDeal(idx)} />
                      </span>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* STEP 3: Plan & Notes */}
          {activeStep === 3 && (
            <div className="flex flex-col gap-4">
              {/* Follow-ups */}
              <div className="p-4 border border-border bg-surface-lighter/30 rounded-lg flex flex-col gap-3">
                <h4 className="text-xs font-bold text-text">
                  {isRtl ? 'المتابعات والمهام القادمة' : 'Next Follow-ups & Tasks'}
                </h4>
                <div className="flex gap-3 items-end">
                  <div className="flex-1">
                    <Input
                      label={isRtl ? 'إضافة بند متابعة' : 'Add Follow-up Item'}
                      value={newFollowUp}
                      onChange={(e) => setNewFollowUp(e.target.value)}
                      placeholder={isRtl ? 'مثال: متابعة التواصل مع الأطباء' : 'e.g. Follow up with doctors'}
                    />
                  </div>
                  <Button onClick={addFollowUp} size="sm" className="btn-secondary h-[42px] px-3">
                    <Plus size={16} />
                  </Button>
                </div>

                {/* List of Follow-ups */}
                {followUps.length > 0 && (
                  <div className="flex flex-col gap-1.5 mt-2 bg-surface-light border border-border p-3 rounded-lg">
                    {followUps.map((item, idx) => (
                      <div key={idx} className="flex justify-between items-center text-xs text-text border-b border-border/40 pb-1.5 last:border-b-0 last:pb-0">
                        <span className="font-medium">• {item}</span>
                        <X size={12} className="cursor-pointer text-text-muted hover:text-danger-text" onClick={() => removeFollowUp(idx)} />
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Strategy & Notes */}
              <Textarea
                label={isRtl ? 'استراتيجية التحسين' : 'Improvement Strategy'}
                value={improvementStrategy}
                onChange={(e) => setImprovementStrategy(e.target.value)}
                placeholder={isRtl ? 'اكتب خطتك لتحسين التواصل والصفقات الأسبوع المقبل...' : 'Describe your strategy to improve sales next week...'}
                required
                rows={3}
              />

              <Textarea
                label={isRtl ? 'ملاحظات إضافية (اختياري)' : 'Additional Notes (Optional)'}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder={isRtl ? 'أي ملاحظات تريد إيصالها للإدارة...' : 'Any comments to the management...'}
                rows={2}
              />
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex justify-between items-center pt-4 border-t border-border">
            <div>
              {activeStep > 1 && (
                <Button onClick={handleBackStep} variant="outline" size="sm" className="flex items-center gap-1">
                  {isRtl ? <ChevronLeft size={16} /> : <ChevronRight size={16} />}
                  {isRtl ? 'السابق' : 'Back'}
                </Button>
              )}
            </div>
            
            <div className="flex gap-2">
              <Button onClick={() => setIsSubmitOpen(false)} size="sm" className="btn-secondary">
                {isRtl ? 'إلغاء' : 'Cancel'}
              </Button>
              
              {activeStep < 3 ? (
                <Button onClick={handleNextStep} size="sm" className="btn-primary flex items-center gap-1">
                  {isRtl ? 'التالي' : 'Next'}
                  {isRtl ? <ChevronRight size={16} /> : <ChevronLeft size={16} />}
                </Button>
              ) : (
                <Button 
                  onClick={handleSubmitReport} 
                  size="sm" 
                  className="btn-primary" 
                  disabled={submitLoading}
                >
                  {submitLoading ? (
                    <Spinner size="sm" />
                  ) : (
                    isRtl ? 'إرسال التقرير' : 'Submit Report'
                  )}
                </Button>
              )}
            </div>
          </div>
        </div>
      </Modal>

      {/* DELETE CONFIRM */}
      <ConfirmDialog
        isOpen={deleteReportId !== null}
        onClose={() => setDeleteReportId(null)}
        onConfirm={handleDeleteReport}
        title={isRtl ? 'حذف التقرير الأسبوعي' : 'Delete Weekly Report'}
        message={isRtl 
          ? 'هل أنت متأكد من رغبتك في حذف هذا التقرير الأسبوعي؟ لا يمكن التراجع عن هذا الإجراء.' 
          : 'Are you sure you want to delete this weekly report? This action cannot be undone.'
        }
      />
    </div>
  );
};
