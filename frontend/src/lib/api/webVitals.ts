import { apiClient, type Query } from './client';
import type { Paginated } from './slots';
import type { WebVitalsSummary } from '../types/models';

export async function getWebVitalsSummary(query: Query): Promise<WebVitalsSummary> {
  const res = await apiClient.get<WebVitalsSummary>('/web-vitals', query);
  return res.data;
}

export async function listVitalsPages(query: Query): Promise<Paginated<Record<string, unknown>>> {
  const res = await apiClient.get<Record<string, unknown>[]>('/web-vitals/pages', query);
  return { items: res.data, pagination: res.meta.pagination };
}
