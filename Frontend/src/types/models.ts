export interface User {
  id: number;
  name: string;
  email: string;
  employee: Employee | null;
  created_at: string;
}

export interface Employee {
  id: number;
  name: string;
  phone: string | null;
  email: string | null;
  department?: string | null;
  employment_date: string | null;
  companies_count?: number;
  contracts_count?: number;
  created_at: string;
}

export interface EmployeeStats {
  total_companies: number;
  total_contracts: number;
  total_value: number;
  total_paid: number;
  remaining: number;
  avg_value: number;
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
  notes: string | null;
  total_paid: number;
  remaining_amount: number;
  collection_percentage: number;
  payments?: Payment[];
  attachments?: Attachment[];
  renewals?: Contract[];
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
