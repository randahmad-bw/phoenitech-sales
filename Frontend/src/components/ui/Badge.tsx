import React from 'react';
import { cn } from '@/utils';
import { useTranslation } from 'react-i18next';

export interface BadgeProps {
  status: string;
  className?: string;
}

export const Badge: React.FC<BadgeProps> = ({ status, className }) => {
  const { t } = useTranslation();

  const styles: Record<string, string> = {
    active:    'badge-active',
    signed:    'badge-signed',
    completed: 'badge-completed',
    draft:     'badge-draft',
    suspended: 'badge-suspended',
    cancelled: 'badge-cancelled',
    renewed:   'badge-signed',
    paid:      'badge-paid',
    pending:   'badge-pending',
  };

  const labels: Record<string, string> = {
    active:    t('contract.status_active',    'نشط'),
    signed:    t('contract.status_signed',    'موقّع'),
    completed: t('contract.status_completed', 'مكتمل'),
    draft:     t('contract.status_draft',     'مسودة'),
    suspended: t('contract.status_suspended', 'معلّق'),
    cancelled: t('contract.status_cancelled', 'ملغى'),
    renewed:   t('contract.status_renewed',   'مُجدّد'),
    paid:      t('payment.status_paid',       'مدفوع'),
    pending:   t('payment.status_pending',    'معلّق'),
  };

  const classVal = styles[status] || 'badge-draft';
  const label = labels[status] || status;

  return (
    <span className={cn('badge uppercase tracking-wider', classVal, className)}>
      {label}
    </span>
  );
};
