import api from '@/lib/axios';
import type { ApiResponse, PaginatedResponse, Contract, SubscriptionDashboard } from '@/types';

export const subscriptionApi = {
  dashboard: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<SubscriptionDashboard>>('/subscriptions/dashboard', { params }),
  list: (params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<Contract>>('/subscriptions', { params }),

  // CRUD — uses /contracts endpoints since subscriptions ARE contracts
  show: (id: number) =>
    api.get<ApiResponse<Contract>>(`/contracts/${id}`),
  create: (data: Record<string, unknown>) =>
    api.post<ApiResponse<Contract>>('/contracts', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put<ApiResponse<Contract>>(`/contracts/${id}`, data),
  delete: (id: number) =>
    api.delete<ApiResponse<null>>(`/contracts/${id}`),
  renew: (id: number, data: Record<string, unknown>) =>
    api.post<ApiResponse<Contract>>(`/contracts/${id}/renew`, data),
};
