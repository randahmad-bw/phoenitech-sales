import api from '@/lib/axios';
import type { ApiResponse, PaginatedResponse, ServerSubscription, ServerSubscriptionDashboard } from '@/types';

export const subscriptionApi = {
  dashboard: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<ServerSubscriptionDashboard>>('/subscriptions/dashboard', { params }),
  list: (params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<ServerSubscription>>('/subscriptions', { params }),
  show: (id: number) =>
    api.get<ApiResponse<ServerSubscription>>(`/subscriptions/${id}`),
  create: (data: Record<string, unknown>) =>
    api.post<ApiResponse<ServerSubscription>>('/subscriptions', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put<ApiResponse<ServerSubscription>>(`/subscriptions/${id}`, data),
  delete: (id: number) =>
    api.delete<ApiResponse<null>>(`/subscriptions/${id}`),
  renew: (id: number, data: Record<string, unknown>) =>
    api.post<ApiResponse<ServerSubscription>>(`/subscriptions/${id}/renew`, data),
};
