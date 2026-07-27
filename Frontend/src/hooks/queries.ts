import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { employeeApi } from '@/api/employees';
import { companyApi, contactApi } from '@/api/companies';
import { contractApi, paymentApi, serviceApi, dashboardApi, reportApi, searchApi, attachmentApi, weeklyReportApi } from '@/api';

// ==========================================
// Dashboard Hooks
// ==========================================
export const useDashboard = (year?: number) => {
  return useQuery({
    queryKey: ['dashboard', year],
    queryFn: async () => {
      const { data } = await dashboardApi.get(year);
      return data.data;
    },
  });
};

// ==========================================
// Employees Hooks
// ==========================================
export const useEmployees = (filters?: Record<string, unknown>) => {
  return useQuery({
    queryKey: ['employees', filters],
    queryFn: async () => {
      const { data } = await employeeApi.list(filters);
      return data;
    },
  });
};

export const useEmployee = (id: number) => {
  return useQuery({
    queryKey: ['employee', id],
    queryFn: async () => {
      const { data } = await employeeApi.show(id);
      return data.data;
    },
    enabled: !!id,
  });
};

export const useEmployeeStats = (id: number) => {
  return useQuery({
    queryKey: ['employee-stats', id],
    queryFn: async () => {
      const { data } = await employeeApi.stats(id);
      return data.data;
    },
    enabled: !!id,
  });
};

export const useEmployeeMutations = () => {
  const queryClient = useQueryClient();

  const create = useMutation({
    mutationFn: (payload: Record<string, unknown>) => employeeApi.create(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['employees'] }),
  });

  const update = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) => employeeApi.update(id, payload),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employees'] });
      queryClient.invalidateQueries({ queryKey: ['employee', variables.id] });
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => employeeApi.delete(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['employees'] }),
  });

  return { create, update, remove };
};

// ==========================================
// Companies Hooks
// ==========================================
export const useCompanies = (filters?: Record<string, unknown>) => {
  return useQuery({
    queryKey: ['companies', filters],
    queryFn: async () => {
      const { data } = await companyApi.list(filters);
      return data;
    },
  });
};

export const useCompany = (id: number) => {
  return useQuery({
    queryKey: ['company', id],
    queryFn: async () => {
      const { data } = await companyApi.show(id);
      return data.data;
    },
    enabled: !!id,
  });
};

export const useCompanyMutations = () => {
  const queryClient = useQueryClient();

  const create = useMutation({
    mutationFn: (payload: Record<string, unknown>) => companyApi.create(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['companies'] }),
  });

  const update = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) => companyApi.update(id, payload),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      queryClient.invalidateQueries({ queryKey: ['company', variables.id] });
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => companyApi.delete(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['companies'] }),
  });

  return { create, update, remove };
};

// ==========================================
// Contacts Hooks (Nested under Company)
// ==========================================
export const useContacts = (companyId: number) => {
  return useQuery({
    queryKey: ['contacts', companyId],
    queryFn: async () => {
      const { data } = await contactApi.list(companyId);
      return data.data;
    },
    enabled: !!companyId,
  });
};

export const useContactMutations = (companyId: number) => {
  const queryClient = useQueryClient();

  const create = useMutation({
    mutationFn: (payload: Record<string, unknown>) => contactApi.create(companyId, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['contacts', companyId] }),
  });

  const update = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) => contactApi.update(companyId, id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['contacts', companyId] }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => contactApi.delete(companyId, id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['contacts', companyId] }),
  });

  return { create, update, remove };
};

// ==========================================
// Contracts Hooks
// ==========================================
export const useContracts = (filters?: Record<string, unknown>) => {
  return useQuery({
    queryKey: ['contracts', filters],
    queryFn: async () => {
      const { data } = await contractApi.list(filters);
      return data;
    },
  });
};

export const useContract = (id: number) => {
  return useQuery({
    queryKey: ['contract', id],
    queryFn: async () => {
      const { data } = await contractApi.show(id);
      return data.data;
    },
    enabled: !!id,
  });
};

export const useContractTree = (id: number) => {
  return useQuery({
    queryKey: ['contractTree', id],
    queryFn: async () => {
      const res = await contractApi.tree(id);
      return res.data.data;
    },
    enabled: !!id,
  });
};

export const useContractMutations = () => {
  const queryClient = useQueryClient();

  const create = useMutation({
    mutationFn: (payload: Record<string, unknown>) => contractApi.create(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['contracts'] }),
  });

  const update = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) => contractApi.update(id, payload),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['contracts'] });
      queryClient.invalidateQueries({ queryKey: ['contract', variables.id] });
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => contractApi.delete(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['contracts'] }),
  });

  const renew = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) => contractApi.renew(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['contracts'] }),
  });

  return { create, update, remove, renew };
};

// ==========================================
// Payments Hooks (Nested under Contract)
// ==========================================
export const usePayments = (contractId: number) => {
  return useQuery({
    queryKey: ['payments', contractId],
    queryFn: async () => {
      const { data } = await paymentApi.list(contractId);
      return data.data;
    },
    enabled: !!contractId,
  });
};

export const usePaymentMutations = (contractId: number) => {
  const queryClient = useQueryClient();

  const create = useMutation({
    mutationFn: (payload: Record<string, unknown>) => paymentApi.create(contractId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payments', contractId] });
      queryClient.invalidateQueries({ queryKey: ['contract', contractId] });
    },
  });

  const update = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) => paymentApi.update(contractId, id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payments', contractId] });
      queryClient.invalidateQueries({ queryKey: ['contract', contractId] });
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => paymentApi.delete(contractId, id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payments', contractId] });
      queryClient.invalidateQueries({ queryKey: ['contract', contractId] });
    },
  });

  return { create, update, remove };
};

// ==========================================
// Services Hooks
// ==========================================
export const useServices = (filters?: Record<string, unknown>) => {
  return useQuery({
    queryKey: ['services', filters],
    queryFn: async () => {
      const { data } = await serviceApi.list(filters);
      return data.data;
    },
  });
};

export const useServiceMutations = () => {
  const queryClient = useQueryClient();

  const create = useMutation({
    mutationFn: (payload: Record<string, unknown>) => serviceApi.create(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['services'] }),
  });

  const update = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) => serviceApi.update(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['services'] }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => serviceApi.delete(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['services'] }),
  });

  return { create, update, remove };
};

// ==========================================
// Reports Hooks
// ==========================================
export const useMonthlyReport = (year: number, month: number) => {
  return useQuery({
    queryKey: ['report-monthly', year, month],
    queryFn: async () => {
      const { data } = await reportApi.monthly(year, month);
      return data.data;
    },
    enabled: !!year && !!month,
  });
};

export const useYearlyReport = (year: number) => {
  return useQuery({
    queryKey: ['report-yearly', year],
    queryFn: async () => {
      const { data } = await reportApi.yearly(year);
      return data.data;
    },
    enabled: !!year,
  });
};

// ==========================================
// Attachments Hooks
// ==========================================
export const useAttachmentMutations = (contractId: number) => {
  const queryClient = useQueryClient();

  const upload = useMutation({
    mutationFn: (formData: FormData) => attachmentApi.upload(formData),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contract', contractId] });
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => attachmentApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contract', contractId] });
    },
  });

  return { upload, remove };
};

// ==========================================
// Weekly Reports Hooks
// ==========================================
export const useWeeklyReports = (filters?: Record<string, unknown>) => {
  return useQuery({
    queryKey: ['weekly-reports', filters],
    queryFn: async () => {
      const { data } = await weeklyReportApi.list(filters);
      return data;
    },
  });
};

export const useWeeklyReport = (id: number) => {
  return useQuery({
    queryKey: ['weekly-report', id],
    queryFn: async () => {
      const { data } = await weeklyReportApi.show(id);
      return data.data;
    },
    enabled: !!id,
  });
};

export const useWeeklyReportMutations = () => {
  const queryClient = useQueryClient();

  const create = useMutation({
    mutationFn: (payload: Record<string, unknown>) => weeklyReportApi.create(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['weekly-reports'] }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => weeklyReportApi.delete(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['weekly-reports'] }),
  });

  return { create, remove };
};
