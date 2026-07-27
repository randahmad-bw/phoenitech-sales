import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useAuthStore } from '@/store/authStore';
import { useNavigate, Navigate } from 'react-router';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { useTranslation } from 'react-i18next';
import { ShieldCheck } from 'lucide-react';

import { useUiStore } from '@/store/uiStore';

const loginSchema = z.object({
  email: z.string().email({ message: 'Invalid email address.' }),
  password: z.string().min(6, { message: 'Password must be at least 6 characters.' }),
});

type LoginFields = z.infer<typeof loginSchema>;

export const LoginPage: React.FC = () => {
  const { login, isAuthenticated, isLoading } = useAuthStore();
  const { theme } = useUiStore();
  const navigate = useNavigate();
  const { t } = useTranslation();

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<LoginFields>({
    resolver: zodResolver(loginSchema),
  });

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  const onSubmit = async (data: LoginFields) => {
    try {
      await login(data.email, data.password);
      navigate('/');
    } catch (err: any) {
      setError('root', {
        message: err.response?.data?.message || t('auth.login_error', 'Login failed. Please try again.'),
      });
    }
  };

  return (
    <div className="flex h-screen w-screen items-center justify-center bg-surface px-4">
      <Card className="w-full max-w-md border border-border bg-surface-light p-8 shadow-2xl relative overflow-hidden backdrop-blur-xl">
        <div className="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500" />
        
        <div className="flex flex-col items-center gap-2 text-center mb-8">
          <img
            src={theme === 'light' ? '/logo_dark.png' : '/logo_white.png'}
            alt="PhoeniTech Logo"
            className="h-16 object-contain"
          />
          <p className="text-xs text-text-muted mt-2">
            {t('login.subtitle', 'Sign in to access your administrative dashboard')}
          </p>
        </div>

        {errors.root && (
          <div className="mb-6 rounded-lg bg-rose-500/10 border border-rose-500/20 p-3 text-xs text-rose-500 font-semibold">
            {errors.root.message}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-5">
          <Input
            label={t('auth.email', 'Email Address')}
            type="email"
            placeholder="admin@phoenitech.com"
            error={errors.email?.message}
            {...register('email')}
          />

          <Input
            label={t('auth.password', 'Password')}
            type="password"
            placeholder="••••••••"
            error={errors.password?.message}
            {...register('password')}
          />

          <Button type="submit" className="w-full h-11 text-sm mt-3" isLoading={isLoading}>
            {t('auth.login_btn', 'Authenticate')}
          </Button>
        </form>
      </Card>
    </div>
  );
};
