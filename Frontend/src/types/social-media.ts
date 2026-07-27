// Social Media Module — TypeScript Types

export interface SmPackage {
  id: number;
  contract_id: number;
  package_name: string | null;
  monthly_posts: number;
  monthly_reels: number;
  monthly_stories: number;
  notes: string | null;
  contract?: {
    id: number;
    contract_number: string;
    company?: { id: number; name: string };
  };
  created_at: string;
}

export interface ContentPlan {
  id: number;
  contract_id: number;
  company_id: number;
  sm_package_id: number | null;
  month: number;
  year: number;
  status: 'active' | 'completed';
  notes: string | null;
  contract?: {
    id: number;
    contract_number: string;
    company?: { id: number; name: string };
  };
  package?: SmPackage;
  items?: ContentItem[];
  photo_sessions?: PhotoSession[];
  items_count?: number;
  photo_sessions_count?: number;
  created_at: string;
}

export type ContentType = 'post' | 'reel' | 'story';
export type ContentStatus = 'pending' | 'in_progress' | 'completed';

export interface ContentItem {
  id: number;
  content_plan_id: number;
  title: string;
  content_type: ContentType;
  design_date: string | null;
  publish_date: string | null;
  assigned_to: number | null;
  photo_session_id: number | null;
  is_designed: boolean;
  is_published: boolean;
  status: ContentStatus;
  notes: string | null;
  plan?: ContentPlan;
  designer?: { id: number; name: string };
  photo_session?: PhotoSession;
  created_at: string;
}

export type SessionStatus = 'scheduled' | 'completed' | 'cancelled';

export interface PhotoSession {
  id: number;
  content_plan_id: number;
  company_id: number;
  session_date: string;
  session_time: string;
  photographer_id: number | null;
  status: SessionStatus;
  notes: string | null;
  plan?: ContentPlan;
  company?: { id: number; name: string };
  photographer?: { id: number; name: string };
  content_items?: ContentItem[];
  created_at: string;
}

export interface SmDashboardStats {
  total_items: number;
  pending_design: number;
  designed: number;
  published: number;
  alerts_today_tomorrow: number;
  active_plans: number;
  upcoming_sessions: number;
}
