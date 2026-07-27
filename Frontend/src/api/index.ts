import api from '@/lib/axios';
import type { ApiResponse, PaginatedResponse, Contract, Payment, SearchResult, DashboardData, Service, WeeklyReport } from '@/types';

export { employeeApi } from './employees';
export { companyApi } from './companies';
export { smApi } from './social-media';

export const contractApi = {
  list: (params?: Record<string, unknown>) => api.get<PaginatedResponse<Contract>>('/contracts', { params }),
  show: (id: number) => api.get<ApiResponse<Contract>>(`/contracts/${id}`),
  create: (data: Record<string, unknown>) => api.post<ApiResponse<Contract>>('/contracts', data),
  update: (id: number, data: Record<string, unknown>) => api.put<ApiResponse<Contract>>(`/contracts/${id}`, data),
  delete: (id: number) => api.delete<ApiResponse<null>>(`/contracts/${id}`),
  renew: (id: number, data: Partial<Contract>) => api.post<{ data: Contract }>(`/contracts/${id}/renew`, data),
  tree: (id: number) => api.get<{ data: Contract }>(`/contracts/${id}/tree`),
};

export const paymentApi = {
  list: (contractId: number) => api.get<ApiResponse<Payment[]>>(`/contracts/${contractId}/payments`),
  create: (contractId: number, data: Partial<Payment>) => api.post<ApiResponse<Payment>>(`/contracts/${contractId}/payments`, data),
  update: (contractId: number, paymentId: number, data: Partial<Payment>) => api.put<ApiResponse<Payment>>(`/contracts/${contractId}/payments/${paymentId}`, data),
  delete: (contractId: number, paymentId: number) => api.delete<ApiResponse<null>>(`/contracts/${contractId}/payments/${paymentId}`),
};

export const serviceApi = {
  list: (params?: Record<string, unknown>) => api.get<ApiResponse<Service[]>>('/services', { params }),
  create: (data: Partial<Service>) => api.post<ApiResponse<Service>>('/services', data),
  update: (id: number, data: Partial<Service>) => api.put<ApiResponse<Service>>(`/services/${id}`, data),
  delete: (id: number) => api.delete<ApiResponse<null>>(`/services/${id}`),
};

export const dashboardApi = {
  get: (year?: number) => api.get<ApiResponse<DashboardData>>('/dashboard', { params: { year } }),
};

export const searchApi = {
  search: (q: string) => api.get<ApiResponse<SearchResult>>('/search', { params: { q } }),
};

export const reportApi = {
  monthly: (year: number, month: number) => api.get<ApiResponse<Record<string, unknown>>>('/reports/monthly', { params: { year, month } }),
  yearly: (year: number) => api.get<ApiResponse<Record<string, unknown>>>('/reports/yearly', { params: { year } }),
};

export const attachmentApi = {
  upload: (formData: FormData) => api.post<ApiResponse<unknown>>('/attachments', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
  delete: (id: number) => api.delete<ApiResponse<null>>(`/attachments/${id}`),
};

export const weeklyReportApi = {
  list: (params?: Record<string, unknown>) => api.get<PaginatedResponse<WeeklyReport>>('/weekly-reports', { params }),
  show: (id: number) => api.get<ApiResponse<WeeklyReport>>(`/weekly-reports/${id}`),
  create: (data: Record<string, unknown>) => api.post<ApiResponse<WeeklyReport>>('/weekly-reports', data),
  delete: (id: number) => api.delete<ApiResponse<null>>(`/weekly-reports/${id}`),
};
