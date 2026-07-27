import React from 'react';
import { cn } from '@/utils';

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  hoverLift?: boolean;
}

export const Card: React.FC<CardProps> = ({ children, className, hoverLift = false, ...props }) => {
  return (
    <div
      className={cn(
        'card transition-all duration-300',
        hoverLift && 'hover:-translate-y-1',
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
};
