import React from 'react';
import { useUiStore } from '@/store/uiStore';
import { useAuthStore } from '@/store/authStore';
import { Card } from '@/components/ui/Card';
import { Select } from '@/components/ui/Select';
import { useTranslation } from 'react-i18next';
import { Globe, User, ShieldAlert } from 'lucide-react';

export const SettingsPage: React.FC = () => {
  const { language, setLanguage } = useUiStore();
  const { user } = useAuthStore();
  const { t } = useTranslation();

  const handleLanguageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setLanguage(e.target.value as 'ar' | 'en');
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Title Header */}
      <div>
        <h1 className="text-2xl font-black text-text uppercase tracking-wider">{t('settings.title', 'Settings')}</h1>
        <p className="text-xs text-text-muted mt-1">{t('settings.subtitle', 'Configure system options, interface locales, and read security policy.')}</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Localization settings */}
        <Card className="flex flex-col gap-4 border-border bg-surface-light/40">
          <div className="flex items-center gap-2 text-text">
            <Globe size={18} className="text-blue-400" />
            <h3 className="font-bold text-sm uppercase tracking-wider">{t('settings.locale_title', 'Localization')}</h3>
          </div>
          <p className="text-xs text-text-muted">{t('settings.locale_desc', 'Set the primary language of the user interface. Changing this will translate dashboard cards, chart labels, and adjust layout direction.')}</p>
          <div className="w-full sm:w-48 mt-2">
            <Select
              label={t('settings.language_label', 'Display Language')}
              value={language}
              onChange={handleLanguageChange}
              options={[
                { value: 'en', label: 'English (US)' },
                { value: 'ar', label: 'العربية (RTL)' },
              ]}
            />
          </div>
        </Card>

        {/* User Account settings */}
        <Card className="flex flex-col gap-4 border-border bg-surface-light/40">
          <div className="flex items-center gap-2 text-text">
            <User size={18} className="text-indigo-400" />
            <h3 className="font-bold text-sm uppercase tracking-wider">{t('settings.profile_title', 'Current Account')}</h3>
          </div>
          <p className="text-xs text-text-muted">{t('settings.profile_desc', 'You are currently signed in as an administrator. Only administrators have access to client configuration files, payments ledger, and monthly metrics.')}</p>
          
          <div className="flex flex-col gap-3 mt-2 border-t border-border pt-4">
            <div>
              <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('settings.name_label', 'Admin Name')}</div>
              <div className="text-sm font-semibold text-text mt-0.5">{user?.name}</div>
            </div>
            <div>
              <div className="text-[10px] text-text-muted font-bold uppercase tracking-wider">{t('settings.email_label', 'E-mail Address')}</div>
              <div className="text-sm font-semibold text-text mt-0.5">{user?.email}</div>
            </div>
          </div>
        </Card>

        {/* System security notes */}
        <Card className="md:col-span-2 flex items-start gap-4 border-amber-500/20 bg-amber-500/5 p-5 rounded-xl text-amber-500">
          <ShieldAlert size={24} className="shrink-0 mt-0.5" />
          <div className="flex flex-col gap-1.5">
            <h4 className="font-bold text-sm uppercase tracking-wider">{t('settings.security_title', 'System Compliance & Access Controls')}</h4>
            <p className="text-xs text-amber-500/80 leading-relaxed">{t('settings.security_desc', 'This is a secure internal application. All modifications (contract activations, files uploading, payment additions) are logged automatically against your profile credentials. Sharing logins or distributing PDF/Excel reports to outside networks is strictly forbidden.')}</p>
          </div>
        </Card>
      </div>
    </div>
  );
};
