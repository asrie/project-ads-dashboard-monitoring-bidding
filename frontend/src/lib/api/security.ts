import { apiClient, type Query } from './client';
import type { Paginated } from './slots';
import type { SecurityScanDetail, SecurityScanSummary } from '../types/models';

export async function runScan(domainId: string): Promise<SecurityScanDetail> {
  const res = await apiClient.post<SecurityScanDetail>('/security/scan', { domain_id: domainId });
  return res.data;
}

export async function listScans(query: Query): Promise<Paginated<SecurityScanSummary>> {
  const res = await apiClient.get<SecurityScanSummary[]>('/security/scans', query);
  return { items: res.data, pagination: res.meta.pagination };
}

export async function getScan(id: string): Promise<SecurityScanDetail> {
  const res = await apiClient.get<SecurityScanDetail>(`/security/scans/${id}`);
  return res.data;
}
