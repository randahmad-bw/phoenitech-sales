import api from '@/lib/axios';
import type { ApiResponse, PaginatedResponse, Company, Contact } from '@/types';

export const companyApi = {
  list: (params?: Record<string, unknown>) => api.get<PaginatedResponse<Company>>('/companies', { params }),
  show: (id: number) => api.get<ApiResponse<Company>>(`/companies/${id}`),
  create: (data: Partial<Company>) => api.post<ApiResponse<Company>>('/companies', data),
  update: (id: number, data: Partial<Company>) => api.put<ApiResponse<Company>>(`/companies/${id}`, data),
  delete: (id: number) => api.delete<ApiResponse<null>>(`/companies/${id}`),
};

export const contactApi = {
  list: (companyId: number) => api.get<ApiResponse<Contact[]>>(`/companies/${companyId}/contacts`),
  create: (companyId: number, data: Partial<Contact>) => api.post<ApiResponse<Contact>>(`/companies/${companyId}/contacts`, data),
  update: (companyId: number, contactId: number, data: Partial<Contact>) => api.put<ApiResponse<Contact>>(`/companies/${companyId}/contacts/${contactId}`, data),
  delete: (companyId: number, contactId: number) => api.delete<ApiResponse<null>>(`/companies/${companyId}/contacts/${contactId}`),
};
