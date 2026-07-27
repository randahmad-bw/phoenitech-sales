import api from '@/lib/axios';
import type { ApiResponse, PaginatedResponse } from '@/types';
import type {
  SmPackage,
  ContentPlan,
  ContentItem,
  PhotoSession,
  SmDashboardStats,
} from '@/types/social-media';

/**
 * Social Media module API client.
 */
export const smApi = {
  // ─── Packages ───────────────────────────────────────────
  listPackages: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<SmPackage[]>>('/sm/packages', { params }),
  createPackage: (data: Record<string, unknown>) =>
    api.post<ApiResponse<SmPackage>>('/sm/packages', data),
  updatePackage: (id: number, data: Record<string, unknown>) =>
    api.put<ApiResponse<SmPackage>>(`/sm/packages/${id}`, data),
  deletePackage: (id: number) =>
    api.delete<ApiResponse<null>>(`/sm/packages/${id}`),

  // ─── Content Plans ──────────────────────────────────────
  listPlans: (params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<ContentPlan>>('/sm/plans', { params }),
  getPlan: (id: number) =>
    api.get<ApiResponse<ContentPlan>>(`/sm/plans/${id}`),
  createPlan: (data: Record<string, unknown>) =>
    api.post<ApiResponse<ContentPlan>>('/sm/plans', data),
  createBatchPlan: (data: Record<string, unknown>) =>
    api.post<ApiResponse<ContentPlan>>('/sm/plans/batch', data),
  updatePlan: (id: number, data: Record<string, unknown>) =>
    api.put<ApiResponse<ContentPlan>>(`/sm/plans/${id}`, data),
  deletePlan: (id: number) =>
    api.delete<ApiResponse<null>>(`/sm/plans/${id}`),

  // ─── Content Items ──────────────────────────────────────
  listItems: (params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<ContentItem>>('/sm/items', { params }),
  createItem: (data: Record<string, unknown>) =>
    api.post<ApiResponse<ContentItem>>('/sm/items', data),
  updateItem: (id: number, data: Record<string, unknown>) =>
    api.put<ApiResponse<ContentItem>>(`/sm/items/${id}`, data),
  toggleCheckboxes: (id: number, data: { is_designed?: boolean; is_published?: boolean }) =>
    api.patch<ApiResponse<ContentItem>>(`/sm/items/${id}/toggle`, data),
  deleteItem: (id: number) =>
    api.delete<ApiResponse<null>>(`/sm/items/${id}`),

  // ─── Photo Sessions ─────────────────────────────────────
  listSessions: (params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<PhotoSession>>('/sm/sessions', { params }),
  createSession: (data: Record<string, unknown>) =>
    api.post<ApiResponse<PhotoSession>>('/sm/sessions', data),
  updateSession: (id: number, data: Record<string, unknown>) =>
    api.put<ApiResponse<PhotoSession>>(`/sm/sessions/${id}`, data),
  updateSessionStatus: (id: number, status: string) =>
    api.patch<ApiResponse<PhotoSession>>(`/sm/sessions/${id}/status`, { status }),
  deleteSession: (id: number) =>
    api.delete<ApiResponse<null>>(`/sm/sessions/${id}`),

  // ─── Alerts & Dashboard ─────────────────────────────────
  getAlerts: () =>
    api.get<ApiResponse<ContentItem[]>>('/sm/alerts'),
  getDashboard: () =>
    api.get<ApiResponse<SmDashboardStats>>('/sm/dashboard'),
};
