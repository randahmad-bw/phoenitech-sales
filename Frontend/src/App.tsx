import React, { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router';
import { AppShell } from '@/components/layout/AppShell';
import { RequirePermission } from '@/components/auth/RequirePermission';
import { LoginPage } from '@/pages/LoginPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { EmployeesPage } from '@/pages/EmployeesPage';
import { ContractsPage } from '@/pages/ContractsPage';
import { ReportsPage } from '@/pages/ReportsPage';
import { WeeklyReportsPage } from '@/pages/WeeklyReportsPage';
import { SettingsPage } from '@/pages/SettingsPage';
import { SocialMediaPage } from '@/pages/SocialMediaPage';
import { CompaniesPage } from '@/pages/CompaniesPage';
import { SubscriptionsPage } from '@/pages/SubscriptionsPage';
import { UsersPage } from '@/pages/UsersPage';
import { RolesPage } from '@/pages/RolesPage';
import { AuditLogPage } from '@/pages/AuditLogPage';
import { ForbiddenPage } from '@/pages/ForbiddenPage';
import { useAuthStore } from '@/store/authStore';
import { useUiStore } from '@/store/uiStore';

export const App: React.FC = () => {
  const { isAuthenticated } = useAuthStore();
  const { theme, language } = useUiStore();

  useEffect(() => {
    if (theme === 'light') {
      document.documentElement.classList.add('light');
    } else {
      document.documentElement.classList.remove('light');
    }
  }, [theme]);

  useEffect(() => {
    document.documentElement.dir = language === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = language;
  }, [language]);

  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />

        {/*
          Each guarded route declares the same permission as the API route
          behind it. The dashboard, settings and the 403 page stay open to any
          authenticated user — the data they show is scoped server-side.
        */}
        <Route element={<AppShell />}>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route path="/403" element={<ForbiddenPage />} />

          <Route element={<RequirePermission permission="companies.view" />}>
            <Route path="/clients" element={<CompaniesPage />} />
          </Route>

          <Route element={<RequirePermission permission="social_media.view" />}>
            <Route path="/social-media" element={<SocialMediaPage />} />
          </Route>

          <Route element={<RequirePermission permission={['employees.view_all', 'employees.view_own']} />}>
            <Route path="/employees" element={<EmployeesPage />} />
          </Route>

          <Route element={<RequirePermission permission={['contracts.view_all', 'contracts.view_own']} />}>
            <Route path="/contracts" element={<ContractsPage />} />
          </Route>

          <Route element={<RequirePermission permission="subscriptions.view" />}>
            <Route path="/subscriptions" element={<SubscriptionsPage />} />
          </Route>

          <Route element={<RequirePermission permission="reports.view" />}>
            <Route path="/reports" element={<ReportsPage />} />
          </Route>

          <Route element={<RequirePermission permission={['weekly_reports.view_all', 'weekly_reports.view_own']} />}>
            <Route path="/weekly-reports" element={<WeeklyReportsPage />} />
          </Route>

          {/* System administration */}
          <Route element={<RequirePermission permission="users.view" />}>
            <Route path="/users" element={<UsersPage />} />
          </Route>

          <Route element={<RequirePermission permission="roles.view" />}>
            <Route path="/roles" element={<RolesPage />} />
          </Route>

          <Route element={<RequirePermission permission="audit.view" />}>
            <Route path="/audit-logs" element={<AuditLogPage />} />
          </Route>
        </Route>

        <Route
          path="*"
          element={<Navigate to={isAuthenticated ? '/' : '/login'} replace />}
        />
      </Routes>
    </BrowserRouter>
  );
};

export default App;
