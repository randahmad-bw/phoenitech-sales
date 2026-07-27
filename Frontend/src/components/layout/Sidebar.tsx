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
} from 'lucide-react';

export const Sidebar: React.FC = () => {
  const { theme, sidebarOpen, language, toggleSidebar, toggleLanguage, toggleTheme } = useUiStore();
  const { logout, user } = useAuthStore();
  const { t } = useTranslation();
  const isRtl = language === 'ar';

  const menuItems = [
    { to: '/',               label: t('nav.dashboard', 'لوحة التحكم'),      icon: LayoutDashboard },
    { to: '/clients',        label: t('nav.clients', 'عملاؤنا'),             icon: Building2 },
    { to: '/contracts',      label: t('nav.contracts', 'العقود'),           icon: FileText },
    { to: '/employees',      label: t('nav.employees', 'الموظفون'),         icon: Users },
    { to: '/reports',        label: t('nav.reports',   'التقارير'),         icon: BarChart3 },
    { to: '/weekly-reports', label: t('nav.weekly_reports', 'التقارير الأسبوعية'), icon: ClipboardList },
    { to: '/social-media',   label: t('nav.social_media', 'إدارة السوشال ميديا'), icon: ClipboardList },
    { to: '/settings',       label: t('nav.settings',  'الإعدادات'),       icon: SettingsIcon },
  ];

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
        {menuItems.map((item) => {
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
