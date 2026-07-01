import { apiClient, type Query } from './client';
import type { DashboardOverview } from '../types/models';

export async function getOverview(query: Query): Promise<DashboardOverview> {
  const res = await apiClient.get<DashboardOverview>('/dashboard/overview', query);
  return res.data;
}
