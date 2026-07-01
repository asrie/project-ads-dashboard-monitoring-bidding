import { apiClient, type Query } from './client';
import type { Paginated } from './slots';
import type { AlertRow, Insight } from '../types/models';

export async function listAlerts(query: Query): Promise<Paginated<AlertRow>> {
  const res = await apiClient.get<AlertRow[]>('/alerts', query);
  return { items: res.data, pagination: res.meta.pagination };
}

export async function getAlert(id: string): Promise<AlertRow> {
  const res = await apiClient.get<AlertRow>(`/alerts/${id}`);
  return res.data;
}

export async function acknowledgeAlert(id: string): Promise<AlertRow> {
  const res = await apiClient.patch<AlertRow>(`/alerts/${id}/acknowledge`);
  return res.data;
}

export async function listInsights(query: Query): Promise<Insight[]> {
  const res = await apiClient.get<Insight[]>('/insights', query);
  return res.data;
}
