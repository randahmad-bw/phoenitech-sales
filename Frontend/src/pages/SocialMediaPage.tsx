import React, { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { smApi } from '@/api/social-media';
import { contractApi, employeeApi } from '@/api';
import type { ContentPlan, ContentItem, PhotoSession, SmPackage } from '@/types/social-media';
import type { Contract, Employee } from '@/types';
import {
  Bell, Plus, X, Check, Clock, Loader2, Calendar,
  Edit2, Trash2, Camera, Video, FileText, AlertTriangle, ChevronDown, ChevronUp
} from 'lucide-react';

const MONTHS_AR = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

export const SocialMediaPage: React.FC = () => {
  const { t, i18n } = useTranslation();
  const isAr = i18n.language === 'ar';

  const [loading, setLoading] = useState(false);
  const [alerts, setAlerts] = useState<ContentItem[]>([]);
  const [showAlertDetails, setShowAlertDetails] = useState(false);

  // Core Data
  const [contracts, setContracts] = useState<Contract[]>([]);
  const [selectedContractId, setSelectedContractId] = useState<string>('all');
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [plans, setPlans] = useState<ContentPlan[]>([]);
  const [items, setItems] = useState<ContentItem[]>([]);
  const [sessions, setSessions] = useState<PhotoSession[]>([]);
  const [packages, setPackages] = useState<SmPackage[]>([]);

  // Modals
  const [showItemModal, setShowItemModal] = useState(false);
  const [showSessionModal, setShowSessionModal] = useState(false);
  const [showPackageModal, setShowPackageModal] = useState(false);
  const [showBatchModal, setShowBatchModal] = useState(false);
  const [editingItem, setEditingItem] = useState<ContentItem | null>(null);

  // Batch Form State
  const [batchContractId, setBatchContractId] = useState('');
  const [batchMonth, setBatchMonth] = useState(new Date().getMonth() + 1);
  const [batchYear, setBatchYear] = useState(new Date().getFullYear());
  const [batchRows, setBatchRows] = useState<Array<{ title: string; content_type: 'reel' | 'post' | 'story'; assigned_to: string; design_date: string; publish_date: string }>>([]);

  // Forms
  const [itemForm, setItemForm] = useState({
    contract_id: '',
    title: '',
    content_type: 'reel' as 'post' | 'reel' | 'story',
    design_date: '',
    publish_date: '',
    assigned_to: '',
    notes: '',
  });

  const [sessionForm, setSessionForm] = useState({
    contract_id: '',
    session_date: '',
    session_time: '10:00',
    photographer_id: '',
    notes: '',
  });

  const [packageForm, setPackageForm] = useState({
    contract_id: '',
    package_name: '',
    monthly_posts: 6,
    monthly_reels: 6,
    monthly_stories: 12,
  });

  // ─── Loaders ────────────────────────────────────────────
  const loadAlerts = useCallback(async () => {
    try {
      const res = await smApi.getAlerts();
      setAlerts(res.data.data);
    } catch (e) { console.error(e); }
  }, []);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [cRes, eRes, pRes, plansRes, itemsRes, sessRes] = await Promise.all([
        contractApi.list({ per_page: 200 }).catch(err => { console.error('Contracts error:', err); return { data: { data: [] } }; }),
        employeeApi.list({ per_page: 200 }).catch(err => { console.error('Employees error:', err); return { data: { data: [] } }; }),
        smApi.listPackages().catch(err => { console.error('Packages error:', err); return { data: { data: [] } }; }),
        smApi.listPlans({ per_page: 200 }).catch(err => { console.error('Plans error:', err); return { data: { data: [] } }; }),
        smApi.listItems({ per_page: 300 }).catch(err => { console.error('Items error:', err); return { data: { data: [] } }; }),
        smApi.listSessions({ per_page: 200 }).catch(err => { console.error('Sessions error:', err); return { data: { data: [] } }; }),
      ]);
      setContracts(cRes?.data?.data || []);
      setEmployees(eRes?.data?.data || []);
      setPackages(pRes?.data?.data || []);
      setPlans(plansRes?.data?.data || []);
      setItems(itemsRes?.data?.data || []);
      setSessions(sessRes?.data?.data || []);
    } catch (e) { console.error(e); }
    setLoading(false);
  }, []);

  useEffect(() => {
    loadData();
    loadAlerts();
  }, [loadData, loadAlerts]);

  // ─── Checkbox Toggle Handlers ────────────────────────────
  const handleToggleDesigned = async (item: ContentItem) => {
    const newVal = !item.is_designed;
    setItems(prev => prev.map(i => i.id === item.id ? { ...i, is_designed: newVal } : i));
    try {
      await smApi.toggleCheckboxes(item.id, { is_designed: newVal });
      loadData();
      loadAlerts();
    } catch (e) {
      console.error(e);
      loadData();
    }
  };

  const handleTogglePublished = async (item: ContentItem) => {
    const newVal = !item.is_published;
    setItems(prev => prev.map(i => i.id === item.id ? { ...i, is_published: newVal } : i));
    try {
      await smApi.toggleCheckboxes(item.id, { is_published: newVal });
      loadData();
      loadAlerts();
    } catch (e) {
      console.error(e);
      loadData();
    }
  };

  const handleAssignPerson = async (item: ContentItem, personId: string) => {
    const assigned_to = personId ? Number(personId) : null;
    const emp = employees.find(e => e.id === assigned_to);
    setItems(prev => prev.map(i => i.id === item.id ? { ...i, assigned_to, designer: emp ? { id: emp.id, name: emp.name } : undefined } : i));
    try {
      await smApi.updateItem(item.id, { assigned_to });
    } catch (e) { console.error(e); }
  };

  const handleDateChange = async (item: ContentItem, field: 'design_date' | 'publish_date', val: string) => {
    setItems(prev => prev.map(i => i.id === item.id ? { ...i, [field]: val || null } : i));
    try {
      await smApi.updateItem(item.id, { [field]: val || null });
      if (field === 'publish_date') loadAlerts();
    } catch (e) { console.error(e); }
  };

  const handleDeleteItem = async (id: number) => {
    if (!window.confirm(isAr ? 'هل أنت متأكد من حذف هذه المهمة؟' : 'Delete this item?')) return;
    setItems(prev => prev.filter(i => i.id !== id));
    try {
      await smApi.deleteItem(id);
      loadAlerts();
    } catch (e) { console.error(e); }
  };

  // ─── Save Item ───────────────────────────────────────────
  const handleSaveItem = async () => {
    if (!itemForm.title) return;

    let planId: number | undefined;
    const targetContractId = itemForm.contract_id || selectedContractId;

    if (targetContractId && targetContractId !== 'all') {
      const contract = contracts.find(c => c.id === Number(targetContractId));
      let plan = plans.find(p => p.contract_id === Number(targetContractId) && p.month === new Date().getMonth() + 1);
      if (!plan && contract) {
        try {
          const pkg = packages.find(p => p.contract_id === contract.id);
          const pRes = await smApi.createPlan({
            contract_id: contract.id,
            company_id: contract.company_id,
            sm_package_id: pkg?.id || null,
            month: new Date().getMonth() + 1,
            year: new Date().getFullYear(),
            status: 'active',
          });
          plan = pRes.data.data;
          setPlans(prev => [plan!, ...prev]);
        } catch (e) { console.error(e); }
      }
      planId = plan?.id;
    }

    if (!planId && plans.length > 0) {
      planId = plans[0].id;
    }

    if (!planId) {
      alert(isAr ? 'برجاء تحديد عقد أولاً أو إنشاء خطة شهرية' : 'Please select a contract first');
      return;
    }

    try {
      if (editingItem) {
        await smApi.updateItem(editingItem.id, {
          title: itemForm.title,
          content_type: itemForm.content_type,
          design_date: itemForm.design_date || null,
          publish_date: itemForm.publish_date || null,
          assigned_to: itemForm.assigned_to ? Number(itemForm.assigned_to) : null,
          notes: itemForm.notes || null,
        });
      } else {
        await smApi.createItem({
          content_plan_id: planId,
          title: itemForm.title,
          content_type: itemForm.content_type,
          design_date: itemForm.design_date || null,
          publish_date: itemForm.publish_date || null,
          assigned_to: itemForm.assigned_to ? Number(itemForm.assigned_to) : null,
          notes: itemForm.notes || null,
        });
      }
      setShowItemModal(false);
      setEditingItem(null);
      setItemForm({ contract_id: '', title: '', content_type: 'reel', design_date: '', publish_date: '', assigned_to: '', notes: '' });
      loadData();
      loadAlerts();
    } catch (e) { console.error(e); }
  };

  const handleEditItemClick = (item: ContentItem) => {
    setEditingItem(item);
    setItemForm({
      contract_id: String(item.plan?.contract_id || ''),
      title: item.title,
      content_type: item.content_type,
      design_date: item.design_date || '',
      publish_date: item.publish_date || '',
      assigned_to: item.assigned_to ? String(item.assigned_to) : '',
      notes: item.notes || '',
    });
    setShowItemModal(true);
  };

  // ─── Save Session ────────────────────────────────────────
  const handleSaveSession = async () => {
    const targetContractId = sessionForm.contract_id || selectedContractId;
    if (!targetContractId || targetContractId === 'all' || !sessionForm.session_date) return;

    const contract = contracts.find(c => c.id === Number(targetContractId));
    let plan = plans.find(p => p.contract_id === Number(targetContractId));
    if (!plan && contract) {
      try {
        const pRes = await smApi.createPlan({
          contract_id: contract.id,
          company_id: contract.company_id,
          month: new Date().getMonth() + 1,
          year: new Date().getFullYear(),
        });
        plan = pRes.data.data;
      } catch (e) { console.error(e); }
    }

    if (!plan || !contract) return;

    try {
      await smApi.createSession({
        content_plan_id: plan.id,
        company_id: contract.company_id,
        session_date: sessionForm.session_date,
        session_time: sessionForm.session_time,
        photographer_id: sessionForm.photographer_id ? Number(sessionForm.photographer_id) : null,
        notes: sessionForm.notes || null,
      });
      setShowSessionModal(false);
      setSessionForm({ contract_id: '', session_date: '', session_time: '10:00', photographer_id: '', notes: '' });
      loadData();
    } catch (e) { console.error(e); }
  };

  // ─── Batch Plan Generators ──────────────────────────────
  const handleGenerateBatchRows = (contractIdStr: string) => {
    setBatchContractId(contractIdStr);
    if (!contractIdStr) {
      setBatchRows([]);
      return;
    }
    const pkg = packages.find(p => p.contract_id === Number(contractIdStr));
    const reelsQuota = pkg?.monthly_reels ?? 6;
    const postsQuota = pkg?.monthly_posts ?? 6;
    const storiesQuota = pkg?.monthly_stories ?? 0;

    const generated: Array<{ title: string; content_type: 'reel' | 'post' | 'story'; assigned_to: string; design_date: string; publish_date: string }> = [];

    // Generate Reels
    for (let i = 1; i <= reelsQuota; i++) {
      generated.push({
        title: isAr ? `ريلز ${i}` : `Reel ${i}`,
        content_type: 'reel',
        assigned_to: '',
        design_date: '',
        publish_date: '',
      });
    }

    // Generate Posts
    for (let i = 1; i <= postsQuota; i++) {
      generated.push({
        title: isAr ? `بوست ${i}` : `Post ${i}`,
        content_type: 'post',
        assigned_to: '',
        design_date: '',
        publish_date: '',
      });
    }

    // Generate Stories
    for (let i = 1; i <= storiesQuota; i++) {
      generated.push({
        title: isAr ? `ستوري ${i}` : `Story ${i}`,
        content_type: 'story',
        assigned_to: '',
        design_date: '',
        publish_date: '',
      });
    }

    setBatchRows(generated);
  };

  const handleSaveBatchPlan = async () => {
    if (!batchContractId || batchRows.length === 0) return;
    const contract = contracts.find(c => c.id === Number(batchContractId));
    if (!contract) return;

    const validItems = batchRows.filter(r => r.title.trim() !== '');
    if (validItems.length === 0) return;

    try {
      const pkg = packages.find(p => p.contract_id === contract.id);
      await smApi.createBatchPlan({
        contract_id: contract.id,
        company_id: contract.company_id,
        sm_package_id: pkg?.id || null,
        month: batchMonth,
        year: batchYear,
        items: validItems.map(r => ({
          title: r.title,
          content_type: r.content_type,
          design_date: r.design_date || null,
          publish_date: r.publish_date || null,
          assigned_to: r.assigned_to ? Number(r.assigned_to) : null,
        })),
      });
      setShowBatchModal(false);
      loadData();
      loadAlerts();
    } catch (e) { console.error(e); }
  };

  // ─── Save Package Quotas ──────────────────────────────────
  const handleSavePackage = async () => {
    if (!packageForm.contract_id) return;
    const existing = packages.find(p => p.contract_id === Number(packageForm.contract_id));
    try {
      if (existing) {
        await smApi.updatePackage(existing.id, {
          package_name: packageForm.package_name || null,
          monthly_posts: packageForm.monthly_posts,
          monthly_reels: packageForm.monthly_reels,
          monthly_stories: packageForm.monthly_stories,
        });
      } else {
        await smApi.createPackage({
          contract_id: Number(packageForm.contract_id),
          package_name: packageForm.package_name || null,
          monthly_posts: packageForm.monthly_posts,
          monthly_reels: packageForm.monthly_reels,
          monthly_stories: packageForm.monthly_stories,
        });
      }
      setShowPackageModal(false);
      loadData();
    } catch (e) { console.error(e); }
  };

  // Filter social media contracts (category === 'social' OR service is social OR has a package)
  const filteredSocial = contracts.filter(c => 
    c.category === 'social' || 
    c.service?.name_ar?.includes('سوشيال') || 
    c.service?.name_en?.toLowerCase().includes('social') ||
    packages.some(p => p.contract_id === c.id)
  );

  // Use filtered social contracts, or fallback to all contracts if none match filter
  const socialContracts = filteredSocial.length > 0 ? filteredSocial : contracts;

  // Active social contracts only — for batch plan creation
  const activeSocialContracts = socialContracts.filter(c => c.status === 'active');

  // Helper to format contract label: company name + start date
  const contractLabel = (c: Contract) => {
    const name = c.company?.name || '—';
    const date = c.start_date ? new Date(c.start_date).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
    return date ? `${name} — ${date}` : name;
  };

  // Filter employees for production team (Designers & Photographers only)
  const productionTeam = employees.filter(e => 
    !e.department || ['design', 'photography', 'video', 'media'].includes(e.department)
  );

  // ─── Filtering Items by Contract ──────────────────────────
  const activeContract = selectedContractId !== 'all'
    ? socialContracts.find(c => c.id === Number(selectedContractId))
    : null;

  const currentPackage = activeContract
    ? packages.find(p => p.contract_id === activeContract.id)
    : null;

  const filteredItems = items.filter(item => {
    if (selectedContractId === 'all') return true;
    return item.plan?.contract_id === Number(selectedContractId);
  });

  const filteredSessions = sessions.filter(s => {
    if (selectedContractId === 'all') return true;
    return s.plan?.contract_id === Number(selectedContractId);
  });

  // Calculate Quotas & Completed counts
  const reelsCount = filteredItems.filter(i => i.content_type === 'reel');
  const postsCount = filteredItems.filter(i => i.content_type === 'post');
  const storiesCount = filteredItems.filter(i => i.content_type === 'story');

  const reelsDesigned = reelsCount.filter(i => i.is_designed).length;
  const reelsPublished = reelsCount.filter(i => i.is_published).length;
  const postsDesigned = postsCount.filter(i => i.is_designed).length;
  const postsPublished = postsCount.filter(i => i.is_published).length;

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      {/* 🔴 RED TOP PUBLISHING ALERT BANNER */}
      {alerts.length > 0 && (
        <div className="bg-danger-500 text-white rounded-xl shadow-lg p-4 transition-all">
          <div className="flex items-center justify-between cursor-pointer" onClick={() => setShowAlertDetails(!showAlertDetails)}>
            <div className="flex items-center gap-3">
              <div className="p-2 bg-white/20 rounded-lg animate-pulse">
                <Bell size={22} className="text-white" />
              </div>
              <div>
                <h3 className="font-bold text-base">
                  {isAr
                    ? `⚠️ تنبيه للنشر اليوم وغداً: يوجد (${alerts.length}) منشورات يفضل نشرها الآن!`
                    : `⚠️ Publishing Alert: ${alerts.length} item(s) due today or tomorrow!`}
                </h3>
                <p className="text-xs text-white/80 mt-0.5">
                  {isAr ? 'انقر لعرض التفاصيل وتحديث حالة النشر فوراً' : 'Click to view details and mark published'}
                </p>
              </div>
            </div>
            <button className="p-1 rounded-lg hover:bg-white/20 transition-colors">
              {showAlertDetails ? <ChevronUp size={20} /> : <ChevronDown size={20} />}
            </button>
          </div>

          {/* Expandable Alert Details */}
          {showAlertDetails && (
            <div className="mt-4 pt-3 border-t border-white/20 space-y-2">
              {alerts.map(alertItem => (
                <div key={alertItem.id} className="flex items-center justify-between bg-black/20 rounded-lg p-3 text-sm">
                  <div className="flex items-center gap-3">
                    <span className="font-semibold bg-white/20 px-2 py-0.5 rounded text-xs">
                      {alertItem.content_type.toUpperCase()}
                    </span>
                    <span className="font-medium">{alertItem.title}</span>
                    <span className="text-xs text-white/70">({alertItem.plan?.contract?.company?.name})</span>
                    <span className="text-xs bg-white/10 px-2 py-0.5 rounded">
                      📅 {alertItem.publish_date}
                    </span>
                  </div>
                  <button
                    onClick={(e) => { e.stopPropagation(); handleTogglePublished(alertItem); }}
                    className="btn-primary !bg-white !text-danger-500 hover:!bg-white/90 text-xs font-bold px-3 py-1.5 flex items-center gap-1.5 cursor-pointer shadow"
                  >
                    <Check size={14} />
                    {isAr ? 'تم النشر الآن' : 'Publish Now'}
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* ─── HEADER & CONTRACT SELECTOR ─── */}
      <div className="card !p-5 bg-gradient-to-r from-surface-light via-surface-light to-surface-lighter">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold text-text flex items-center gap-2">
              <FileText className="text-primary-500" size={24} />
              {isAr ? 'إدارة عقود السوشال ميديا والمهام' : 'Social Media Contracts & Tasks'}
              {activeContract && activeContract.status === 'completed' && (
                <span className="badge badge-active text-xs font-bold px-2.5 py-1 bg-success-500 text-white animate-pulse">
                  🎉 {isAr ? 'عقد مكتمل 100%' : 'Completed 100%'}
                </span>
              )}
            </h1>
            <p className="text-xs text-text-muted mt-1">
              {isAr ? 'متابعة تصميم ونشر البوستات والريلز والجلسات لكل عقد بسهولة' : 'Track design, shoot, and publish statuses per contract easily'}
            </p>
          </div>

          {/* Contract Selector */}
          <div className="flex items-center gap-3 flex-wrap">
            <select
              value={selectedContractId}
              onChange={e => setSelectedContractId(e.target.value)}
              className="input-field !w-auto min-w-[240px] font-semibold text-sm border-primary-500/50"
            >
              <option value="all">{isAr ? '🏢 كل عقود السوشال ميديا' : '🏢 All Social Media Contracts'}</option>
              {socialContracts.map(c => (
                <option key={c.id} value={c.id}>
                  {contractLabel(c)}{c.status !== 'active' ? ` (${c.status})` : ''}
                </option>
              ))}
            </select>

            <button
              onClick={() => {
                if (selectedContractId !== 'all') {
                  const pkg = packages.find(p => p.contract_id === Number(selectedContractId));
                  setPackageForm({
                    contract_id: selectedContractId,
                    package_name: pkg?.package_name || '',
                    monthly_posts: pkg?.monthly_posts || 6,
                    monthly_reels: pkg?.monthly_reels || 6,
                    monthly_stories: pkg?.monthly_stories || 12,
                  });
                }
                setShowPackageModal(true);
              }}
              className="btn-secondary text-xs flex items-center gap-1.5"
            >
              <Edit2 size={14} />
              {isAr ? 'تفاصيل الباقة الحصص' : 'Package Quotas'}
            </button>
          </div>
        </div>

        {/* Selected Contract Quota Stats Banner */}
        {activeContract && (
          <div className="mt-4 pt-4 border-t border-border grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-purple-500/10 border border-purple-500/20 rounded-xl p-3 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Video className="text-purple-400" size={20} />
                <div>
                  <div className="text-xs text-text-muted">{isAr ? 'الريلز (Reels)' : 'Reels'}</div>
                  <div className="text-lg font-bold text-text">
                    {reelsCount.length} / {currentPackage?.monthly_reels ?? 6}
                  </div>
                </div>
              </div>
              <div className="text-end text-xs space-y-1">
                <div className="badge badge-active">{isAr ? 'مصمم:' : 'Shot:'} {reelsDesigned}</div>
                <div className="badge badge-signed">{isAr ? 'منشور:' : 'Published:'} {reelsPublished}</div>
              </div>
            </div>

            <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-3 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <FileText className="text-blue-400" size={20} />
                <div>
                  <div className="text-xs text-text-muted">{isAr ? 'البوستات (Posts)' : 'Posts'}</div>
                  <div className="text-lg font-bold text-text">
                    {postsCount.length} / {currentPackage?.monthly_posts ?? 6}
                  </div>
                </div>
              </div>
              <div className="text-end text-xs space-y-1">
                <div className="badge badge-active">{isAr ? 'مصمم:' : 'Designed:'} {postsDesigned}</div>
                <div className="badge badge-signed">{isAr ? 'منشور:' : 'Published:'} {postsPublished}</div>
              </div>
            </div>

            <div className="bg-orange-500/10 border border-orange-500/20 rounded-xl p-3 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Camera className="text-orange-400" size={20} />
                <div>
                  <div className="text-xs text-text-muted">{isAr ? 'الستوريز (Stories)' : 'Stories'}</div>
                  <div className="text-lg font-bold text-text">
                    {storiesCount.length} / {currentPackage?.monthly_stories ?? 12}
                  </div>
                </div>
              </div>
              <div className="text-end text-xs">
                <div className="badge badge-draft">{isAr ? 'إجمالي الجلسات:' : 'Shoots:'} {filteredSessions.length}</div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* ─── ACTION BUTTONS ─── */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h2 className="text-lg font-bold text-text flex items-center gap-2">
          {isAr ? '📋 جدول مهام التصميم والنشر والتصوير' : '📋 Design & Publishing Schedule'}
        </h2>
        <div className="flex items-center gap-2 flex-wrap">
          <button
            onClick={() => {
              setBatchContractId(selectedContractId !== 'all' ? selectedContractId : '');
              if (selectedContractId !== 'all') {
                handleGenerateBatchRows(selectedContractId);
              } else {
                setBatchRows([]);
              }
              setShowBatchModal(true);
            }}
            className="btn-primary !bg-emerald-600 hover:!bg-emerald-700 text-sm font-bold flex items-center gap-2 shadow-md"
          >
            <span>⚡</span>
            {isAr ? 'إنشاء الخطة الشهرية بالكامل (دفعة واحدة)' : 'Create Full Monthly Plan (Batch)'}
          </button>

          <button
            onClick={() => {
              setSessionForm(f => ({ ...f, contract_id: selectedContractId !== 'all' ? selectedContractId : '' }));
              setShowSessionModal(true);
            }}
            className="btn-secondary text-sm flex items-center gap-2"
          >
            <Camera size={16} />
            {isAr ? 'حجز جلسة تصوير' : 'New Shoot Session'}
          </button>

          <button
            onClick={() => {
              setEditingItem(null);
              setItemForm({ contract_id: selectedContractId !== 'all' ? selectedContractId : '', title: '', content_type: 'reel', design_date: '', publish_date: '', assigned_to: '', notes: '' });
              setShowItemModal(true);
            }}
            className="btn-secondary text-sm flex items-center gap-2"
          >
            <Plus size={16} />
            {isAr ? 'إضافة ريل أو بوست' : 'Add Reel or Post'}
          </button>
        </div>
      </div>

      {/* ─── DIRECT INTERACTIVE TABLE WITH 2 CHECKBOXES ─── */}
      <div className="card overflow-x-auto !p-0">
        {loading ? (
          <div className="flex items-center justify-center py-16">
            <Loader2 className="animate-spin text-primary-500" size={32} />
          </div>
        ) : (
          <table className="w-full text-sm border-collapse">
            <thead>
              <tr className="bg-surface-lighter border-b border-border text-text-muted text-xs font-semibold uppercase">
                <th className="p-3.5 text-start w-28">{isAr ? 'نوع المحتوى' : 'Type'}</th>
                <th className="p-3.5 text-start min-w-[200px]">{isAr ? 'العنوان / التفاصيل' : 'Title'}</th>
                <th className="p-3.5 text-start">{isAr ? 'العميل / العقد' : 'Client / Contract'}</th>
                <th className="p-3.5 text-start min-w-[140px]">{isAr ? 'المصمم / المصور' : 'Assigned To'}</th>
                <th className="p-3.5 text-start min-w-[130px]">{isAr ? 'تاريخ التصميم / التصوير' : 'Design / Shoot Date'}</th>
                <th className="p-3.5 text-start min-w-[130px]">{isAr ? 'تاريخ النشر' : 'Publish Date'}</th>
                <th className="p-3.5 text-center min-w-[120px] bg-primary-500/5 border-x border-border">
                  ☑️ {isAr ? 'اتصممت / اتصورت؟' : 'Designed?'}
                </th>
                <th className="p-3.5 text-center min-w-[120px] bg-success-500/5">
                  ☑️ {isAr ? 'انتشرت؟' : 'Published?'}
                </th>
                <th className="p-3.5 text-center w-20">{isAr ? 'إجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/60">
              {filteredItems.map(item => {
                const isReel = item.content_type === 'reel';
                const isPost = item.content_type === 'post';
                const isStory = item.content_type === 'story';

                return (
                  <tr
                    key={item.id}
                    className={`hover:bg-surface-lighter/60 transition-colors ${
                      item.is_published ? 'opacity-75 bg-success-500/5' : ''
                    }`}
                  >
                    {/* Type Badge */}
                    <td className="p-3">
                      <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border ${
                        isReel ? 'bg-purple-500/15 text-purple-400 border-purple-500/30' :
                        isPost ? 'bg-blue-500/15 text-blue-400 border-blue-500/30' :
                        'bg-orange-500/15 text-orange-400 border-orange-500/30'
                      }`}>
                        {isReel && <Video size={12} />}
                        {isPost && <FileText size={12} />}
                        {isStory && <Camera size={12} />}
                        {isReel ? (isAr ? 'ريلز' : 'Reel') : isPost ? (isAr ? 'بوست' : 'Post') : (isAr ? 'ستوري' : 'Story')}
                      </span>
                    </td>

                    {/* Title */}
                    <td className="p-3 font-semibold text-text">
                      <span className={item.is_published ? 'line-through text-text-muted' : ''}>
                        {item.title}
                      </span>
                    </td>

                    {/* Client / Contract */}
                    <td className="p-3 text-xs text-text-muted">
                      <div className="font-medium text-text">{item.plan?.contract?.company?.name || '—'}</div>
                      <div className="text-[11px] opacity-70">{item.plan?.contract?.contract_number}</div>
                    </td>

                    {/* Assigned Person Dropdown */}
                    <td className="p-3">
                      <select
                        value={item.assigned_to || ''}
                        onChange={e => handleAssignPerson(item, e.target.value)}
                        className="input-field !py-1 !px-2 !text-xs !w-auto min-w-[130px]"
                      >
                        <option value="">{isAr ? '— اختر المسؤول —' : '— Select —'}</option>
                        {productionTeam.map(emp => (
                          <option key={emp.id} value={emp.id}>{emp.name}</option>
                        ))}
                      </select>
                    </td>

                    {/* Design / Shoot Date */}
                    <td className="p-3">
                      <input
                        type="date"
                        value={item.design_date || ''}
                        onChange={e => handleDateChange(item, 'design_date', e.target.value)}
                        className="input-field !py-1 !px-2 !text-xs !w-auto"
                      />
                    </td>

                    {/* Publish Date */}
                    <td className="p-3">
                      <input
                        type="date"
                        value={item.publish_date || ''}
                        onChange={e => handleDateChange(item, 'publish_date', e.target.value)}
                        className="input-field !py-1 !px-2 !text-xs !w-auto"
                      />
                    </td>

                    {/* CHECKBOX 1: Is Designed / Shot? */}
                    <td className="p-3 text-center bg-primary-500/5 border-x border-border">
                      <label className="inline-flex items-center justify-center cursor-pointer p-1">
                        <input
                          type="checkbox"
                          checked={item.is_designed}
                          onChange={() => handleToggleDesigned(item)}
                          className="w-5 h-5 accent-primary-500 rounded cursor-pointer transition-transform active:scale-95"
                        />
                      </label>
                    </td>

                    {/* CHECKBOX 2: Is Published? */}
                    <td className="p-3 text-center bg-success-500/5">
                      <label className="inline-flex items-center justify-center cursor-pointer p-1">
                        <input
                          type="checkbox"
                          checked={item.is_published}
                          onChange={() => handleTogglePublished(item)}
                          className="w-5 h-5 accent-success-500 rounded cursor-pointer transition-transform active:scale-95"
                        />
                      </label>
                    </td>

                    {/* Actions */}
                    <td className="p-3 text-center">
                      <div className="flex items-center justify-center gap-1">
                        <button
                          onClick={() => handleEditItemClick(item)}
                          className="p-1.5 rounded-lg text-text-muted hover:bg-surface-lighter hover:text-text transition-colors"
                          title={isAr ? 'تعديل' : 'Edit'}
                        >
                          <Edit2 size={15} />
                        </button>
                        <button
                          onClick={() => handleDeleteItem(item.id)}
                          className="p-1.5 rounded-lg text-text-muted hover:bg-danger-bg hover:text-danger-text transition-colors"
                          title={isAr ? 'حذف' : 'Delete'}
                        >
                          <Trash2 size={15} />
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}

              {filteredItems.length === 0 && (
                <tr>
                  <td colSpan={9} className="text-center py-12 text-text-muted">
                    {isAr ? 'لا توجد مهام مسجلة لهذا العقد بعد. اضغط "إضافة محتوى" لإضافة أول مهمة.' : 'No tasks for this contract yet.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>

      {/* ─── MODALS ─── */}

      {/* Item Modal (Create/Edit) */}
      {showItemModal && (
        <Modal
          title={editingItem ? (isAr ? 'تعديل التفاصيل' : 'Edit Item') : (isAr ? 'إضافة محتوى جديد' : 'Add Content Item')}
          onClose={() => { setShowItemModal(false); setEditingItem(null); }}
        >
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-text mb-1">{isAr ? 'العقد / العميل' : 'Contract / Client'}</label>
              <select
                value={itemForm.contract_id}
                onChange={e => setItemForm(f => ({ ...f, contract_id: e.target.value }))}
                className="input-field"
              >
                <option value="">{isAr ? '— اختر العقد —' : '— Select Contract —'}</option>
                {socialContracts.map(c => (
                  <option key={c.id} value={c.id}>{c.company?.name} ({c.contract_number})</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-text mb-1">{isAr ? 'عنوان المهمة / المحتوى' : 'Title'}</label>
              <input
                type="text"
                value={itemForm.title}
                onChange={e => setItemForm(f => ({ ...f, title: e.target.value }))}
                className="input-field"
                placeholder={isAr ? 'مثال: ريلز عن جهاز فحص الدم' : 'e.g. Reel about blood test'}
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'نوع المحتوى' : 'Type'}</label>
                <select
                  value={itemForm.content_type}
                  onChange={e => setItemForm(f => ({ ...f, content_type: e.target.value as any }))}
                  className="input-field"
                >
                  <option value="reel">{isAr ? '🎬 ريلز (Reel)' : 'Reel'}</option>
                  <option value="post">{isAr ? '📝 بوست (Post)' : 'Post'}</option>
                  <option value="story">{isAr ? '📸 ستوري (Story)' : 'Story'}</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'المصمم / المصور' : 'Assigned To'}</label>
                <select
                  value={itemForm.assigned_to}
                  onChange={e => setItemForm(f => ({ ...f, assigned_to: e.target.value }))}
                  className="input-field"
                >
                  <option value="">{isAr ? '— اختر —' : '— Select —'}</option>
                  {productionTeam.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.name}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'تاريخ التصميم / التصوير' : 'Design/Shoot Date'}</label>
                <input
                  type="date"
                  value={itemForm.design_date}
                  onChange={e => setItemForm(f => ({ ...f, design_date: e.target.value }))}
                  className="input-field"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'تاريخ النشر المخطط' : 'Publish Date'}</label>
                <input
                  type="date"
                  value={itemForm.publish_date}
                  onChange={e => setItemForm(f => ({ ...f, publish_date: e.target.value }))}
                  className="input-field"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-text mb-1">{isAr ? 'ملاحظات إضافية' : 'Notes'}</label>
              <textarea
                value={itemForm.notes}
                onChange={e => setItemForm(f => ({ ...f, notes: e.target.value }))}
                className="input-field"
                rows={2}
              />
            </div>

            <div className="flex gap-3 justify-end pt-2">
              <button onClick={() => { setShowItemModal(false); setEditingItem(null); }} className="btn-secondary text-sm">
                {isAr ? 'إلغاء' : 'Cancel'}
              </button>
              <button onClick={handleSaveItem} className="btn-primary text-sm">
                {isAr ? 'حفظ' : 'Save'}
              </button>
            </div>
          </div>
        </Modal>
      )}

      {/* Session Modal */}
      {showSessionModal && (
        <Modal title={isAr ? 'حجز جلسة تصوير جديدة' : 'New Photography Session'} onClose={() => setShowSessionModal(false)}>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-text mb-1">{isAr ? 'العقد / العميل' : 'Contract / Client'}</label>
              <select
                value={sessionForm.contract_id}
                onChange={e => setSessionForm(f => ({ ...f, contract_id: e.target.value }))}
                className="input-field"
              >
                <option value="">{isAr ? '— اختر العقد —' : '— Select —'}</option>
                {socialContracts.map(c => (
                  <option key={c.id} value={c.id}>{c.company?.name} ({c.contract_number})</option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'تاريخ التصوير' : 'Date'}</label>
                <input
                  type="date"
                  value={sessionForm.session_date}
                  onChange={e => setSessionForm(f => ({ ...f, session_date: e.target.value }))}
                  className="input-field"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'الوقت' : 'Time'}</label>
                <input
                  type="time"
                  value={sessionForm.session_time}
                  onChange={e => setSessionForm(f => ({ ...f, session_time: e.target.value }))}
                  className="input-field"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-text mb-1">{isAr ? 'المصور' : 'Photographer'}</label>
              <select
                value={sessionForm.photographer_id}
                onChange={e => setSessionForm(f => ({ ...f, photographer_id: e.target.value }))}
                className="input-field"
              >
                <option value="">{isAr ? '— اختر المصور —' : '— Select —'}</option>
                {productionTeam.map(emp => (
                  <option key={emp.id} value={emp.id}>{emp.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-text mb-1">{isAr ? 'ملاحظات الجلسة' : 'Notes'}</label>
              <textarea
                value={sessionForm.notes}
                onChange={e => setSessionForm(f => ({ ...f, notes: e.target.value }))}
                className="input-field"
                rows={2}
              />
            </div>

            <div className="flex gap-3 justify-end pt-2">
              <button onClick={() => setShowSessionModal(false)} className="btn-secondary text-sm">{isAr ? 'إلغاء' : 'Cancel'}</button>
              <button onClick={handleSaveSession} className="btn-primary text-sm">{isAr ? 'حفظ' : 'Save'}</button>
            </div>
          </div>
        </Modal>
      )}

      {/* Package Quotas Modal */}
      {showPackageModal && (
        <Modal title={isAr ? 'تحديد حصص الباقة الشهرية للعقد' : 'Edit Package Quotas'} onClose={() => setShowPackageModal(false)}>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-text mb-1">{isAr ? 'العقد' : 'Contract'}</label>
              <select
                value={packageForm.contract_id}
                onChange={e => {
                  const id = e.target.value;
                  const pkg = packages.find(p => p.contract_id === Number(id));
                  setPackageForm({
                    contract_id: id,
                    package_name: pkg?.package_name || '',
                    monthly_posts: pkg?.monthly_posts || 6,
                    monthly_reels: pkg?.monthly_reels || 6,
                    monthly_stories: pkg?.monthly_stories || 12,
                  });
                }}
                className="input-field"
              >
                <option value="">{isAr ? '— اختر العقد —' : '— Select —'}</option>
                {socialContracts.map(c => (
                  <option key={c.id} value={c.id}>{c.company?.name} ({c.contract_number})</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-text mb-1">{isAr ? 'اسم الباقة' : 'Package Name'}</label>
              <input
                type="text"
                value={packageForm.package_name}
                onChange={e => setPackageForm(f => ({ ...f, package_name: e.target.value }))}
                className="input-field"
                placeholder={isAr ? 'مثال: الباقة الذهبية' : 'e.g. Gold Package'}
              />
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'عدد الريلز' : 'Reels Quota'}</label>
                <input
                  type="number"
                  min={0}
                  value={packageForm.monthly_reels}
                  onChange={e => setPackageForm(f => ({ ...f, monthly_reels: Number(e.target.value) }))}
                  className="input-field font-bold"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'عدد البوستات' : 'Posts Quota'}</label>
                <input
                  type="number"
                  min={0}
                  value={packageForm.monthly_posts}
                  onChange={e => setPackageForm(f => ({ ...f, monthly_posts: Number(e.target.value) }))}
                  className="input-field font-bold"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-text mb-1">{isAr ? 'عدد الستوريز' : 'Stories Quota'}</label>
                <input
                  type="number"
                  min={0}
                  value={packageForm.monthly_stories}
                  onChange={e => setPackageForm(f => ({ ...f, monthly_stories: Number(e.target.value) }))}
                  className="input-field font-bold"
                />
              </div>
            </div>

            <div className="flex gap-3 justify-end pt-2">
              <button onClick={() => setShowPackageModal(false)} className="btn-secondary text-sm">{isAr ? 'إلغاء' : 'Cancel'}</button>
              <button onClick={handleSavePackage} className="btn-primary text-sm">{isAr ? 'حفظ' : 'Save'}</button>
            </div>
          </div>
        </Modal>
      )}

      {/* ⚡ BATCH PLAN GENERATOR MODAL */}
      {showBatchModal && (
        <Modal
          title={isAr ? '⚡ إنشاء الخطة الشهرية بالكامل (دفعة واحدة)' : '⚡ Create Full Monthly Plan (Batch)'}
          onClose={() => setShowBatchModal(false)}
          size="lg"
        >
          <div className="space-y-5">
            {/* Header controls */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 bg-surface-lighter/60 p-4 rounded-xl border border-border">
              <div className="md:col-span-2">
                <label className="block text-xs font-bold text-text-muted mb-1">{isAr ? 'اختر العقد' : 'Select Contract'}</label>
                <select
                  value={batchContractId}
                  onChange={e => handleGenerateBatchRows(e.target.value)}
                  className="input-field font-bold text-sm"
                >
                  <option value="">{isAr ? '— اختر عقد سوشال نشط —' : '— Select Active Contract —'}</option>
                  {activeSocialContracts.map(c => (
                    <option key={c.id} value={c.id}>{contractLabel(c)}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-text-muted mb-1">{isAr ? 'الشهر / السنة' : 'Month / Year'}</label>
                <div className="flex items-center gap-2">
                  <select
                    value={batchMonth}
                    onChange={e => setBatchMonth(Number(e.target.value))}
                    className="input-field !py-1.5 text-xs"
                  >
                    {MONTHS_AR.map((m, idx) => idx > 0 ? (
                      <option key={idx} value={idx}>{m}</option>
                    ) : null)}
                  </select>
                  <input
                    type="number"
                    value={batchYear}
                    onChange={e => setBatchYear(Number(e.target.value))}
                    className="input-field !py-1.5 text-xs !w-20 font-bold"
                  />
                </div>
              </div>
            </div>

            {/* Quick Action buttons */}
            {batchContractId && (
              <div className="flex items-center justify-between gap-2">
                <span className="text-xs text-text-muted">
                  {isAr
                    ? `إجمالي عناصر الخطة: (${batchRows.length}) عنصر`
                    : `Total plan items: ${batchRows.length}`}
                </span>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => setBatchRows(r => [...r, { title: isAr ? `ريلز إضافي ${r.filter(x => x.content_type === 'reel').length + 1}` : 'Extra Reel', content_type: 'reel', assigned_to: '', design_date: '', publish_date: '' }])}
                    className="btn-secondary !text-xs !py-1 flex items-center gap-1"
                  >
                    <Plus size={13} /> {isAr ? 'إضافة سطر ريلز' : 'Add Reel Row'}
                  </button>
                  <button
                    type="button"
                    onClick={() => setBatchRows(r => [...r, { title: isAr ? `بوست إضافي ${r.filter(x => x.content_type === 'post').length + 1}` : 'Extra Post', content_type: 'post', assigned_to: '', design_date: '', publish_date: '' }])}
                    className="btn-secondary !text-xs !py-1 flex items-center gap-1"
                  >
                    <Plus size={13} /> {isAr ? 'إضافة سطر بوست' : 'Add Post Row'}
                  </button>
                </div>
              </div>
            )}

            {/* Table of Batch Items */}
            {batchRows.length > 0 ? (
              <div className="max-h-[50vh] overflow-y-auto border border-border rounded-xl">
                <table className="w-full text-xs text-start border-collapse">
                  <thead className="sticky top-0 bg-surface-lighter border-b border-border font-bold text-text-muted">
                    <tr>
                      <th className="p-2.5 w-8">#</th>
                      <th className="p-2.5 w-24">{isAr ? 'النوع' : 'Type'}</th>
                      <th className="p-2.5">{isAr ? 'عنوان المحتوى' : 'Title'}</th>
                      <th className="p-2.5 w-36">{isAr ? 'المسؤول' : 'Assigned To'}</th>
                      <th className="p-2.5 w-32">{isAr ? 'تاريخ التصميم' : 'Design Date'}</th>
                      <th className="p-2.5 w-32">{isAr ? 'تاريخ النشر' : 'Publish Date'}</th>
                      <th className="p-2.5 w-10 text-center"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border/60">
                    {batchRows.map((row, idx) => (
                      <tr key={idx} className="hover:bg-surface-lighter/50">
                        <td className="p-2 text-center text-text-muted font-mono">{idx + 1}</td>
                        <td className="p-2">
                          <select
                            value={row.content_type}
                            onChange={e => {
                              const val = e.target.value as any;
                              setBatchRows(prev => prev.map((r, i) => i === idx ? { ...r, content_type: val } : r));
                            }}
                            className="input-field !py-1 !px-1.5 !text-xs"
                          >
                            <option value="reel">{isAr ? '🎬 ريلز' : 'Reel'}</option>
                            <option value="post">{isAr ? '📝 بوست' : 'Post'}</option>
                            <option value="story">{isAr ? '📸 ستوري' : 'Story'}</option>
                          </select>
                        </td>
                        <td className="p-2">
                          <input
                            type="text"
                            value={row.title}
                            onChange={e => {
                              const val = e.target.value;
                              setBatchRows(prev => prev.map((r, i) => i === idx ? { ...r, title: val } : r));
                            }}
                            className="input-field !py-1 !px-2 !text-xs font-semibold"
                            placeholder={isAr ? 'عنوان المادة...' : 'Title...'}
                          />
                        </td>
                        <td className="p-2">
                          <select
                            value={row.assigned_to}
                            onChange={e => {
                              const val = e.target.value;
                              setBatchRows(prev => prev.map((r, i) => i === idx ? { ...r, assigned_to: val } : r));
                            }}
                            className="input-field !py-1 !px-1.5 !text-xs"
                          >
                            <option value="">{isAr ? '— مسؤول —' : '— Select —'}</option>
                            {productionTeam.map(emp => (
                              <option key={emp.id} value={emp.id}>{emp.name}</option>
                            ))}
                          </select>
                        </td>
                        <td className="p-2">
                          <input
                            type="date"
                            value={row.design_date}
                            onChange={e => {
                              const val = e.target.value;
                              setBatchRows(prev => prev.map((r, i) => i === idx ? { ...r, design_date: val } : r));
                            }}
                            className="input-field !py-1 !px-1.5 !text-xs"
                          />
                        </td>
                        <td className="p-2">
                          <input
                            type="date"
                            value={row.publish_date}
                            onChange={e => {
                              const val = e.target.value;
                              setBatchRows(prev => prev.map((r, i) => i === idx ? { ...r, publish_date: val } : r));
                            }}
                            className="input-field !py-1 !px-1.5 !text-xs"
                          />
                        </td>
                        <td className="p-2 text-center">
                          <button
                            type="button"
                            onClick={() => setBatchRows(prev => prev.filter((_, i) => i !== idx))}
                            className="text-danger-text hover:bg-danger-bg p-1 rounded transition-colors"
                          >
                            <Trash2 size={13} />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-8 text-text-muted text-xs border border-dashed border-border rounded-xl">
                {isAr ? 'اختر عقداً من القائمة أعلاه لتوليد صفوف الباقة تلقائياً' : 'Select a contract above to generate batch plan rows'}
              </div>
            )}

            {/* Modal Actions */}
            <div className="flex items-center justify-between pt-2 border-t border-border">
              <button
                type="button"
                onClick={() => setShowBatchModal(false)}
                className="btn-secondary text-sm"
              >
                {isAr ? 'إلغاء' : 'Cancel'}
              </button>
              <button
                type="button"
                onClick={handleSaveBatchPlan}
                disabled={!batchContractId || batchRows.length === 0}
                className="btn-primary !bg-emerald-600 hover:!bg-emerald-700 text-sm font-bold flex items-center gap-2 shadow-md disabled:opacity-50"
              >
                <Check size={16} />
                {isAr ? `حفظ الخطة الشهرية بالكامل (${batchRows.length} مادة)` : `Save Full Plan (${batchRows.length} items)`}
              </button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
};

// Modal Helper Component
const Modal: React.FC<{ title: string; onClose: () => void; size?: 'sm' | 'md' | 'lg'; children: React.ReactNode }> = ({ title, onClose, size, children }) => (
  <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm animate-fade-in" onClick={onClose}>
    <div
      className={`bg-surface-light border border-border rounded-2xl shadow-2xl w-full max-h-[90vh] overflow-y-auto mx-4 ${
        size === 'lg' ? 'max-w-4xl' : size === 'sm' ? 'max-w-md' : 'max-w-lg'
      }`}
      onClick={e => e.stopPropagation()}
    >
      <div className="flex items-center justify-between p-4 border-b border-border">
        <h2 className="text-base font-bold text-text">{title}</h2>
        <button onClick={onClose} className="p-1 rounded-lg hover:bg-surface-lighter text-text-muted hover:text-text transition-colors">
          <X size={18} />
        </button>
      </div>
      <div className="p-5">{children}</div>
    </div>
  </div>
);
