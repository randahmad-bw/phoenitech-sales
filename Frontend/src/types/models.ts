export interface User {
  id: number;
  name: string;
  username: string | null;
  email: string;
  is_active: boolean;
  /** Set by an admin password reset — the UI must force a change before anything else. */
  must_change_password: boolean;
  /** Role names, e.g. `['manager']`. */
  roles: string[];
  /** Flattened permission names from every role plus any direct grants. */
  permissions: string[];
  employee: Employee | null;
  created_at: string;
}

export type LeaveType = 'annual' | 'sick' | 'unpaid' | 'emergency' | 'special';
export type OvertimeType = 'workday' | 'weekend' | 'holiday';
export type StatusType = 'pending' | 'approved' | 'rejected';

export interface EmployeeLeave {
  id: number;
  employee_id: number;
  leave_type: LeaveType;
  start_date: string;
  end_date: string;
  days_count: number;
  status: StatusType;
  reason?: string | null;
  notes?: string | null;
  approved_by?: number | null;
  created_at: string;
}

export interface EmployeeLeaveSummary {
  annual_allowance: number;
  annual_used: number;
  annual_remaining: number;
  sick_days: number;
  unpaid_days: number;
  emergency_days: number;
  total_leaves_count: number;
  pending_count: number;
}

export interface EmployeeOvertime {
  id: number;
  employee_id: number;
  overtime_date: string;
  hours: number;
  days_equivalent: number;
  rate_multiplier: number;
  overtime_type: OvertimeType;
  reason: string;
  status: StatusType;
  notes?: string | null;
  created_at: string;
}

export interface EmployeeOvertimeSummary {
  total_hours: number;
  total_days_equivalent: number;
  workday_hours: number;
  weekend_hours: number;
  holiday_hours: number;
  total_overtimes_count: number;
  pending_count: number;
}

export interface Employee {
  id: number;
  name: string;
  phone: string | null;
  email: string | null;
  department?: string | null;
  job_title?: string | null;
  base_salary?: number | null;
  annual_leave_allowance?: number;
  job_description?: string | null;
  responsibilities?: string[] | null;
  employment_date: string | null;
  companies_count?: number;
  contracts_count?: number;
  leaves_count?: number;
  overtimes_count?: number;
  approved_leaves_days?: number;
  approved_overtime_hours?: number;
  approved_overtime_days?: number;
  remaining_leave_balance?: number;
  leaves?: EmployeeLeave[];
  overtimes?: EmployeeOvertime[];
  created_at: string;
}

export interface EmployeeStats {
  total_companies: number;
  total_contracts: number;
  total_value: number;
  total_paid: number;
  remaining: number;
  avg_value: number;
  annual_leave_allowance?: number;
  annual_leaves_taken?: number;
  annual_leaves_remaining?: number;
  total_overtime_hours?: number;
  total_overtime_days?: number;
}

export interface EmployeeOverallStats {
  total_employees: number;
  departments: {
    design: number;
    photography: number;
    sales: number;
    dev: number;
    management: number;
  };
  total_annual_leaves_taken: number;
  total_overtime_hours: number;
  total_overtime_days: number;
  pending_leaves_count: number;
  pending_overtime_count: number;
}


export interface Company {
  id: number;
  name: string;
  client_name: string | null;
  phone: string | null;
  activity: string | null;
  address: string | null;
  notes: string | null;
  employee: Employee | null;
  contacts?: Contact[];
  contacts_count?: number;
  contracts_count?: number;
  created_at: string;
}

export interface Contact {
  id: number;
  company_id: number;
  name: string;
  position: string | null;
  mobile: string | null;
  notes: string | null;
  created_at: string;
}

export interface Service {
  id: number;
  name_ar: string;
  name_en: string;
  is_active: boolean;
  contracts_count?: number;
  created_at: string;
}

export type ContractStatus = 'draft' | 'signed' | 'active' | 'completed' | 'cancelled' | 'suspended' | 'renewed';
export type ProductType = 'phoenitech' | 'onocode' | 'other';
export type PaymentMethod = 'cash' | 'bank_transfer' | 'check' | 'other';
export type PaymentStatus = 'paid' | 'pending';

export interface ContractHistory {
  id: number;
  contract_id: number;
  field_name: string;
  old_value: string | null;
  new_value: string | null;
  action: 'created' | 'updated' | 'renewed';
  created_at: string;
}

export interface Contract {
  id: number;
  parent_contract_id: number | null;
  contract_number: string;
  company_id: number;
  company?: Company;
  employee_id: number | null;
  employee?: Employee;
  service_id: number;
  service?: Service;
  contract_value: number;
  currency: string;
  exchange_rate?: number;
  start_date: string;
  end_date: string;
  status: ContractStatus;
  progress_percentage: number;
  category?: string | null;
  category_custom?: string | null;
  product?: ProductType;
  notes: string | null;
  total_paid: number;
  remaining_amount: number;
  collection_percentage: number;
  payments?: Payment[];
  attachments?: Attachment[];
  renewals?: Contract[];
  renewals_count?: number;
  histories?: ContractHistory[];
  created_at: string;
}

export interface Payment {
  id: number;
  contract_id: number;
  amount: number;
  exchange_rate?: number;
  payment_date: string | null;
  method: PaymentMethod;
  status: PaymentStatus;
  notes: string | null;
  created_at: string;
}

export interface Attachment {
  id: number;
  original_name: string;
  url: string;
  mime_type: string | null;
  size_bytes: number | null;
  created_at: string;
}

export interface DashboardStats {
  total_companies: number;
  total_contacts: number;
  total_contracts: number;
  active_contracts: number;
  completed_contracts: number;
  cancelled_contracts: number;
  expired_contracts: number;
  total_contract_value: number;
  total_paid: number;
  total_remaining: number;
  collection_percentage: number;
  avg_contract_value: number;
  largest_contract: number;
  new_contracts_this_month: number;
  renewed_contracts_this_month: number;
  new_companies_this_month: number;
  sales_this_month: number;
  total_sales: number;
  collected_this_month: number;
}

export interface DashboardData {
  stats: DashboardStats;
  charts: {
    monthly_sales: Record<number, number>;
    monthly_collections: Record<number, number>;
    contracts_by_status: Record<string, number>;
    top_employees: { name: string; total: number }[];
    year_comparison: {
      current_year: Record<number, number>;
      previous_year: Record<number, number>;
    };
    employee_monthly_contracts: {
      name: string;
      contracts_this_month: number;
      total_contracts: number;
      sales_this_month: number;
      total_sales: number;
      collected_this_month: number;
      total_collected: number;
    }[];
  };
}

export interface SearchResult {
  companies: Pick<Company, 'id' | 'name' | 'activity'>[];
  employees: Pick<Employee, 'id' | 'name' | 'email'>[];
  contacts: Pick<Contact, 'id' | 'name' | 'mobile' | 'company_id'>[];
  contracts: Pick<Contract, 'id' | 'contract_number' | 'status'>[];
}

export interface WeeklyReport {
  id: number;
  employee_id: number;
  employee?: Employee;
  week_start_date: string;
  kpis: {
    total_contacted: number;
    doctors: number;
    medical_centers: number;
    schools: number;
    restaurants_cafeterias: number;
    pending_decision: number;
    price_offers: number;
  };
  pipeline: {
    signed: { name: string; completion_rate: number }[];
    pending: string[];
  };
  next_plan: {
    follow_ups: string[];
    improvement_strategy: string;
  };
  notes: string | null;
  created_at: string;
}

export interface SubscriptionDashboard {
  total_active: number;
  expiring_soon: number;
  expired: number;
  renewal_rate: number;
  monthly_recurring_revenue: number;
  total_value: number;
  renewed_this_month: number;
  by_product: {
    phoenitech: { active: number; expired: number; value: number };
    onocode: { active: number; expired: number; value: number };
  };
}

export interface ServerSubscription {
  id: number;
  name: string;
  company_name: string | null;
  type: 'vps' | 'hosting' | 'domain' | 'email' | 'ssl';
  domain: string | null;
  provider: string | null;
  cost: number;
  currency: string;
  start_date: string | null;
  end_date: string;
  status: 'active' | 'expiring_soon' | 'expired' | 'cancelled';
  notes: string | null;
  is_expiring_soon?: boolean;
  is_expired?: boolean;
  days_until_expiration?: number | null;
  created_at?: string;
  updated_at?: string;
}

export interface ServerSubscriptionDashboard {
  total: number;
  active: number;
  expiring_soon: number;
  expired: number;
  by_type: {
    vps: number;
    hosting: number;
    domain: number;
    email: number;
    ssl: number;
  };
  total_cost: number;
  companies: string[];
}

