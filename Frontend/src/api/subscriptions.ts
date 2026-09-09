import api from '@/lib/axios';
import type { ApiResponse, PaginatedResponse, Contract, SubscriptionDashboard } from '@/types';

export const subscriptionApi = {
  dashboard: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<SubscriptionDashboard>>('/subscriptions/dashboard', { params }),
  list: (params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<Contract>>('/subscriptions', { params }),
};
