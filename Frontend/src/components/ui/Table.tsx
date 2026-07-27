import React, { useState } from 'react';
import { cn } from '@/utils';
import { ChevronDown, ChevronRight } from 'lucide-react';

export interface TableColumn<T> {
  key: string;
  header: string;
  render?: (row: T) => React.ReactNode;
  className?: string;
}

export interface TableProps<T> {
  columns: TableColumn<T>[];
  data: T[];
  onRowClick?: (row: T) => void;
  className?: string;
  isLoading?: boolean;
  expandableContent?: (row: T) => React.ReactNode;
  hasExpandable?: (row: T) => boolean;
}

export function Table<T extends { id: number | string }>({
  columns,
  data,
  onRowClick,
  className,
  isLoading = false,
  expandableContent,
  hasExpandable,
}: TableProps<T>) {
  const [expandedRows, setExpandedRows] = useState<Set<number | string>>(new Set());

  const toggleRow = (id: number | string, e: React.MouseEvent) => {
    e.stopPropagation();
    const newExpanded = new Set(expandedRows);
    if (newExpanded.has(id)) {
      newExpanded.delete(id);
    } else {
      newExpanded.add(id);
    }
    setExpandedRows(newExpanded);
  };

  return (
    <div className={cn('w-full overflow-x-auto rounded-xl border border-border bg-surface-light', className)}>
      <table className="w-full border-collapse text-start text-sm text-text">
        <thead>
          <tr className="border-b border-border bg-surface-lighter text-xs font-semibold uppercase tracking-wider text-text-muted select-none">
            {expandableContent && <th className="px-4 py-4 w-10"></th>}
            {columns.map((col) => (
              <th key={col.key} className={cn('px-6 py-4 font-semibold', col.className)}>
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-border/50">
          {isLoading ? (
            <tr>
              <td colSpan={columns.length} className="px-6 py-10 text-center text-text-muted">
                <div className="flex justify-center items-center gap-2">
                  <svg className="animate-spin h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  <span className="font-medium">جاري التحميل...</span>
                </div>
              </td>
            </tr>
          ) : data.length === 0 ? (
            <tr>
              <td colSpan={columns.length + (expandableContent ? 1 : 0)} className="px-6 py-10 text-center text-text-muted font-medium">
                لا توجد بيانات.
              </td>
            </tr>
          ) : (
            data.map((row) => (
              <React.Fragment key={row.id}>
                <tr
                  onClick={() => onRowClick && onRowClick(row)}
                  className={cn(
                    'transition-colors duration-200',
                    onRowClick && 'cursor-pointer hover:bg-surface-lighter/70',
                    expandedRows.has(row.id) ? 'bg-surface-lighter' : 'hover:bg-surface-lighter/70'
                  )}
                >
                  {expandableContent && (
                    <td className="px-4 py-4 text-center">
                      {(!hasExpandable || hasExpandable(row)) && (
                        <div className="flex items-center justify-center text-text-muted hover:text-text transition-colors cursor-pointer" onClick={(e) => toggleRow(row.id, e)}>
                          {expandedRows.has(row.id) ? <ChevronDown size={18} /> : <ChevronRight size={18} />}
                        </div>
                      )}
                    </td>
                  )}
                  {columns.map((col) => (
                    <td key={col.key} className={cn('px-6 py-4 whitespace-nowrap text-text', col.className)}>
                      {col.render ? col.render(row) : (row as any)[col.key]}
                    </td>
                  ))}
                </tr>
                {expandableContent && expandedRows.has(row.id) && (
                  <tr className="bg-surface border-b border-border">
                    <td colSpan={columns.length + 1} className="p-0">
                      <div className="animate-in fade-in slide-in-from-top-4 duration-300 ease-out">
                        {expandableContent(row)}
                      </div>
                    </td>
                  </tr>
                )}
              </React.Fragment>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
