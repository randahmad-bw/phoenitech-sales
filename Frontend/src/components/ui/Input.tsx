import React from 'react';
import { cn } from '@/utils';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type = 'text', label, error, ...props }, ref) => {
    return (
      <div className="w-full flex flex-col gap-1.5">
        {label && (
          <label className="text-xs font-semibold text-text-muted select-none">
            {label}
          </label>
        )}
        <input
          type={type}
          className={cn(
            'input-field h-10',
            error && 'border-danger-text focus:ring-danger-text/30',
            className
          )}
          ref={ref}
          {...props}
        />
        {error && (
          <span className="text-xs text-danger-text font-medium">
            {error}
          </span>
        )}
      </div>
    );
  }
);

Input.displayName = 'Input';
