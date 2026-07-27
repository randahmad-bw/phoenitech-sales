import api from '@/lib/axios';
import type { ApiResponse, PaginatedResponse, Employee, EmployeeStats } from '@/types';

export const employeeApi = {
  list: (params?: Record<string, unknown>) => api.get<PaginatedResponse<Employee>>('/employees', { params }),
  show: (id: number) => api.get<ApiResponse<Employee>>(`/employees/${id}`),
  create: (data: Partial<Employee>) => api.post<ApiResponse<Employee>>('/employees', data),
  update: (id: number, data: Partial<Employee>) => api.put<ApiResponse<Employee>>(`/employees/${id}`, data),
  delete: (id: number) => api.delete<ApiResponse<null>>(`/employees/${id}`),
  stats: (id: number) => api.get<ApiResponse<EmployeeStats>>(`/employees/${id}/stats`),
};
