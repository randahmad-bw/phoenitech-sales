import React, { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router';
import { AppShell } from '@/components/layout/AppShell';
import { LoginPage } from '@/pages/LoginPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { EmployeesPage } from '@/pages/EmployeesPage';
import { ContractsPage } from '@/pages/ContractsPage';
import { ReportsPage } from '@/pages/ReportsPage';
import { WeeklyReportsPage } from '@/pages/WeeklyReportsPage';
import { SettingsPage } from '@/pages/SettingsPage';
import { SocialMediaPage } from '@/pages/SocialMediaPage';
import { CompaniesPage } from '@/pages/CompaniesPage';
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
        <Route element={<AppShell />}>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/clients" element={<CompaniesPage />} />
          <Route path="/social-media" element={<SocialMediaPage />} />
          <Route path="/employees" element={<EmployeesPage />} />
          <Route path="/contracts" element={<ContractsPage />} />
          <Route path="/reports" element={<ReportsPage />} />
          <Route path="/weekly-reports" element={<WeeklyReportsPage />} />
          <Route path="/settings" element={<SettingsPage />} />
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
