import React from 'react';
import { cn } from '@/utils';

export interface SpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export const Spinner: React.FC<SpinnerProps> = ({ size = 'md', className }) => {
  const sizes = {
    sm: 'h-5 w-5',
    md: 'h-8 w-8',
    lg: 'h-12 w-12',
  };

  return (
    <div className={cn('flex items-center justify-center', className)}>
      <img 
        src="/logo_mark.png" 
        alt="Loading..." 
        className={cn('animate-pulse opacity-80', sizes[size])} 
        style={{ animationDuration: '1s' }}
      />
    </div>
  );
};
