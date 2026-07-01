// Domain types mirroring backend service payloads.

export type Device = 'desktop' | 'mobile' | 'tablet';
export type HealthStatus = 'healthy' | 'warning' | 'critical';
export type Severity = 'low' | 'medium' | 'high' | 'critical';
export type VitalRating = 'good' | 'needs_improvement' | 'poor';

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  role: string;
  role_label: string;
}

export interface LoginResponse {
  access_token: string;
  token_type: string;
  expires_in: number;
  user: AuthUser;
}

export interface DashboardOverview {
  range: { date_from: string; date_to: string; device: string | null; domain_id: string | null };
  revenue: {
    total_revenue: number;
    total_impressions: number;
    total_ad_requests: number;
    fill_rate: number;
    avg_ecpm: number;
  };
  demand: {
    bid_requests: number;
    bid_responses: number;
    bids_won: number;
    bid_response_rate: number;
    timeout_rate: number;
    no_bid_rate: number;
  };
  prebid: {
    total_auctions: number;
    avg_duration_ms: number;
    timeout_count: number;
    error_count: number;
    avg_bidders: number;
    status: HealthStatus;
  };
  gam: {
    total_requests: number;
    success_requests: number;
    failed_requests: number;
    success_rate: number;
    failure_rate: number;
    avg_latency_ms: number;
    status: HealthStatus;
  };
  web_vitals: Record<string, number> & { samples: number };
  alerts: { active_total: number; critical: number; high: number; medium: number; low: number };
  revenue_trend: Array<{ date: string; revenue: number; impressions: number }>;
}

export interface SlotRow {
  id: string;
  name: string;
  ad_unit_path: string;
  device: Device | null;
  domain: string | null;
  revenue: number;
  impressions: number;
  ad_requests: number;
  fill_rate: number;
  ecpm: number;
  viewability: number;
}

export interface BidderRow {
  id: string;
  name: string;
  code: string;
  bid_requests: number;
  bid_responses: number;
  bids_won: number;
  timeouts: number;
  errors: number;
  revenue: number;
  bid_response_rate: number;
  timeout_rate: number;
  no_bid_rate: number;
  win_rate: number;
  avg_latency_ms: number;
  avg_cpm: number;
  health: HealthStatus;
}

export interface PrebidHealth {
  total_auctions: number;
  avg_duration_ms: number;
  p95_duration_ms: number;
  max_duration_ms: number;
  timeout_count: number;
  timeout_rate: number;
  error_count: number;
  error_rate: number;
  avg_bidders: number;
  status: HealthStatus;
  timeseries: Array<{ date: string; avg_duration_ms: number; auctions: number; timeouts: number }>;
}

export interface GamHealth {
  total_requests: number;
  success_requests: number;
  empty_requests: number;
  failed_requests: number;
  success_rate: number;
  failure_rate: number;
  avg_latency_ms: number;
  status: HealthStatus;
  timeseries: Array<{
    date: string;
    total_requests: number;
    failed_requests: number;
    failure_rate: number;
    avg_latency_ms: number;
  }>;
}

export interface WebVitalsSummary {
  samples: number;
  metrics: Record<string, { value: number; status: VitalRating; unit: string }>;
  timeseries: Array<Record<string, number | string>>;
}

export interface DomainRef {
  id: string;
  name: string;
  url: string;
  is_active: boolean;
}

export interface SecurityScanSummary {
  id: string;
  domain: string | null;
  target_host: string;
  status: string;
  grade: string | null;
  score: number | null;
  created_at: string | null;
}

export interface SecurityScanDetail extends SecurityScanSummary {
  target_url: string;
  started_at: string | null;
  finished_at: string | null;
  results: Record<string, any>;
}

export interface PreviewRect {
  x: number;
  y: number;
  w: number;
  h: number;
}

export interface PreviewSlot {
  element_id: string | null;
  ad_unit_path: string | null;
  rect: PreviewRect | null;
  sizes: Array<[number, number] | string>;
  type: 'header_bidding' | 'direct';
  matched: boolean;
  slot_name: string | null;
  metrics: {
    revenue: number;
    impressions: number;
    fill_rate: number;
    ecpm: number;
    viewability: number;
  } | null;
}

export interface PagePreviewSummary {
  id: string;
  domain: string | null;
  url: string;
  device: string;
  status: string;
  slot_count: number;
  captured_at: string | null;
}

export interface PagePreviewDetail extends PagePreviewSummary {
  page_width: number;
  page_height: number;
  viewport_css_width: number;
  slots: PreviewSlot[];
  header: PreviewRect | null;
  image: string | null;
}

export type ServerStatus = 'up' | 'down' | 'unknown';

export interface ServerDomainHealth {
  domain_id: string;
  domain: string;
  uptime_pct: number;
  avg_response_ms: number;
  current_status: ServerStatus;
  last_checked_at: string | null;
  status: HealthStatus;
}

export interface ServerHealth {
  range: { date_from: string; date_to: string; domain_id: string | null };
  overall: {
    total_checks: number;
    up_checks: number;
    down_checks: number;
    uptime_pct: number;
    avg_response_ms: number;
    p95_response_ms: number;
    max_response_ms: number;
    incidents: number;
    current_status: ServerStatus;
    status: HealthStatus;
  };
  domains: ServerDomainHealth[];
  timeseries: Array<{ date: string; uptime_pct: number; avg_response_ms: number }>;
}

export interface AlertRow {
  id: string;
  severity: Severity;
  category: string;
  metric: string;
  current_value: number | null;
  threshold_value: number | null;
  entity_type: string | null;
  entity_id: string | null;
  entity_label: string | null;
  domain: string | null;
  message: string;
  suggested_action: string | null;
  status: 'open' | 'acknowledged' | 'resolved';
  acknowledged_by: string | null;
  acknowledged_at: string | null;
  triggered_at: string | null;
}

export interface Insight {
  id: string;
  title: string;
  description: string;
  type: string;
  impact: string;
  related_metric: string | null;
  domain: string | null;
  generated_at: string | null;
}
