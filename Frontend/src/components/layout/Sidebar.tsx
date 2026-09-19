import React from 'react';
import { NavLink } from 'react-router';
import { useUiStore } from '@/store/uiStore';
import { useAuthStore } from '@/store/authStore';
import { cn } from '@/utils';
import { useTranslation } from 'react-i18next';
import {
  LayoutDashboard,
  Users,
  FileText,
  BarChart3,
  Settings as SettingsIcon,
  LogOut,
  ChevronLeft,
  ChevronRight,
  Globe,
  Moon,
  Sun,
  ClipboardList,
  Building2,
  Megaphone,
  RefreshCcw,
  ShieldCheck,
  UserCog,
  ScrollText,
} from 'lucide-react';
import { usePermissions } from '@/hooks/usePermissions';

export const Sidebar: React.FC = () => {
  const { theme, sidebarOpen, language, toggleSidebar, toggleLanguage, toggleTheme } = useUiStore();
  const { logout, user } = useAuthStore();
  const { t } = useTranslation();
  const { can } = usePermissions();
  const isRtl = language === 'ar';

  // `permission` mirrors the middleware on the matching API route. Omitting it
  // means the entry is open to any authenticated user (dashboard, settings).
  // The list is filtered below — hiding a link the account cannot open.
  const menuItems = [
    { to: '/',               label: t('nav.dashboard', 'لوحة التحكم'),      icon: LayoutDashboard },
    { to: '/contracts',      label: t('nav.contracts', 'العقود'),           icon: FileText,       permission: ['contracts.view_all', 'contracts.view_own'] },
    { to: '/subscriptions',  label: t('nav.subscriptions', 'الاشتراكات'),   icon: RefreshCcw,     permission: 'subscriptions.view' },
    { to: '/reports',        label: t('nav.reports',   'التقارير'),         icon: BarChart3,      permission: 'reports.view' },
    { to: '/weekly-reports', label: t('nav.weekly_reports', 'التقارير الأسبوعية'), icon: ClipboardList, permission: ['weekly_reports.view_all', 'weekly_reports.view_own'] },
    { to: '/social-media',   label: t('nav.social_media', 'إدارة السوشال ميديا'), icon: Megaphone, permission: 'social_media.view' },
    { to: '/employees',      label: t('nav.employees', 'الموظفون'),         icon: Users,          permission: ['employees.view_all', 'employees.view_own'] },
    { to: '/clients',        label: t('nav.clients', 'عملاؤنا'),             icon: Building2,      permission: 'companies.view' },
    { to: '/settings',       label: t('nav.settings',  'الإعدادات'),       icon: SettingsIcon },
  ];

  // System administration — its own section so it does not crowd daily work.
  const adminItems = [
    { to: '/users',      label: t('nav.users', 'المستخدمون'),       icon: UserCog,     permission: 'users.view' },
    { to: '/roles',      label: t('nav.roles', 'الأدوار والصلاحيات'), icon: ShieldCheck, permission: 'roles.view' },
    { to: '/audit-logs', label: t('nav.audit_logs', 'سجل التدقيق'),  icon: ScrollText,  permission: 'audit.view' },
  ];

  const visibleMenu = menuItems.filter((item) => !item.permission || can(item.permission));
  const visibleAdmin = adminItems.filter((item) => can(item.permission));

  return (
    <aside
      className={cn(
        'relative flex flex-col h-screen border-e border-border bg-surface-light text-text-muted transition-all duration-300 z-30',
        sidebarOpen ? 'w-64' : 'w-20'
      )}
    >
      {/* Brand Header */}
      <div className="flex h-16 items-center justify-between px-4 border-b border-border">
        <div className="flex items-center min-w-0">
          {sidebarOpen ? (
            <img
              src={theme === 'light' ? '/logo_dark.png' : '/logo_white.png'}
              alt="فوني تيك"
              className="h-9 object-contain transition-all duration-300"
            />
          ) : (
            <img
              src="/logo_mark.png"
              alt="فوني تيك"
              className="h-8 w-8 object-contain transition-all duration-300 mx-auto"
            />
          )}
        </div>
        <button
          onClick={toggleSidebar}
          className="rounded-lg p-1.5 hover:bg-surface-lighter hover:text-text transition-colors cursor-pointer"
        >
          {sidebarOpen
            ? (isRtl ? <ChevronRight size={18} /> : <ChevronLeft size={18} />)
            : (isRtl ? <ChevronLeft size={18} />  : <ChevronRight size={18} />)
          }
        </button>
      </div>

      {/* Navigation Items */}
      <nav className="flex-1 space-y-1 px-3 py-4 overflow-y-auto">
        {visibleMenu.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200 cursor-pointer',
                  isActive
                    ? 'bg-primary-bg text-primary-text border-s-2 border-primary-500 ps-2.5 font-semibold'
                    : 'hover:bg-surface-lighter hover:text-text'
                )
              }
            >
              <Icon size={18} className="shrink-0" />
              <span
                className={cn(
                  'transition-opacity duration-300 whitespace-nowrap',
                  sidebarOpen ? 'opacity-100' : 'opacity-0 w-0 overflow-hidden'
                )}
              >
                {item.label}
              </span>
            </NavLink>
          );
        })}

        {visibleAdmin.length > 0 && (
          <div className="pt-4">
            <div className="mb-1 px-3">
              {sidebarOpen ? (
                <span className="text-[10px] font-bold uppercase tracking-wider text-text-muted/70 select-none">
                  {t('nav.administration', 'إدارة النظام')}
                </span>
              ) : (
                <div className="mx-auto h-px w-8 bg-border" />
              )}
            </div>

            {visibleAdmin.map((item) => {
              const Icon = item.icon;
              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={({ isActive }) =>
                    cn(
                      'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200 cursor-pointer',
                      isActive
                        ? 'bg-primary-bg text-primary-text border-s-2 border-primary-500 ps-2.5 font-semibold'
                        : 'hover:bg-surface-lighter hover:text-text'
                    )
                  }
                >
                  <Icon size={18} className="shrink-0" />
                  <span
                    className={cn(
                      'transition-opacity duration-300 whitespace-nowrap',
                      sidebarOpen ? 'opacity-100' : 'opacity-0 w-0 overflow-hidden'
                    )}
                  >
                    {item.label}
                  </span>
                </NavLink>
              );
            })}
          </div>
        )}
      </nav>

      {/* Footer */}
      <div className="border-t border-border p-3 bg-surface-light space-y-1">
        {/* Theme Toggle */}
        <button
          onClick={toggleTheme}
          className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-surface-lighter hover:text-text transition-colors cursor-pointer"
        >
          {theme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}
          <span className={cn('transition-opacity duration-300', sidebarOpen ? 'opacity-100' : 'opacity-0 w-0 overflow-hidden')}>
            {theme === 'dark' ? 'الوضع الفاتح' : 'الوضع الداكن'}
          </span>
        </button>

        {/* Language Toggle */}
        <button
          onClick={toggleLanguage}
          className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-surface-lighter hover:text-text transition-colors cursor-pointer"
        >
          <Globe size={18} />
          <span className={cn('transition-opacity duration-300', sidebarOpen ? 'opacity-100' : 'opacity-0 w-0 overflow-hidden')}>
            {language === 'ar' ? 'English' : 'العربية'}
          </span>
        </button>

        {/* User Info & Logout */}
        <div className="flex items-center justify-between gap-2 pt-1">
          {sidebarOpen && (
            <div className="flex flex-col min-w-0">
              <span className="text-xs font-semibold text-text truncate">{user?.name}</span>
              <span className="text-[10px] text-text-muted truncate">{user?.email}</span>
            </div>
          )}
          <button
            onClick={() => logout()}
            className="rounded-lg p-2 text-text-muted hover:bg-surface-lighter hover:text-danger-text transition-colors cursor-pointer"
            title={t('auth.logout', 'تسجيل الخروج')}
          >
            <LogOut size={18} />
          </button>
        </div>
      </div>
    </aside>
  );
};
