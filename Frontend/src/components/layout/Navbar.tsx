import React from 'react';
import { useAuthStore } from '@/store/authStore';
import { User } from 'lucide-react';
import { useUiStore } from '@/store/uiStore';

export const Navbar: React.FC = () => {
  const { user } = useAuthStore();
  const { language } = useUiStore();

  return (
    <header className="flex h-16 w-full items-center justify-between border-b border-border bg-surface-light px-6 z-20">
      <div className="flex items-center gap-3">
        <h2 className="text-sm font-bold text-text">
          {language === 'ar' ? 'نظام مبيعات فوني تيك' : 'PhoeniTech Sales Management'}
        </h2>
      </div>

      <div className="flex items-center gap-3">
        {/* User Account */}
        <div className="flex items-center gap-2 ps-3 border-s border-border">
          <div className="flex items-center justify-center h-8 w-8 rounded-full bg-primary-bg border border-primary-text/20 text-primary-text">
            <User size={15} />
          </div>
          <span className="text-xs font-semibold text-text select-none hidden sm:inline-block">
            {user?.name}
          </span>
        </div>
      </div>
    </header>
  );
};
