import { apiClient, type Query } from './client';
import type { Paginated } from './slots';

export async function listNetworkAds(query: Query): Promise<Paginated<Record<string, unknown>>> {
  const res = await apiClient.get<Record<string, unknown>[]>('/network-ads', query);
  return { items: res.data, pagination: res.meta.pagination };
}

export async function getHeavyRequests(query: Query): Promise<{
  summary: Record<string, number>;
  by_vendor: Array<Record<string, unknown>>;
  heaviest: Array<Record<string, unknown>>;
}> {
  const res = await apiClient.get<{
    summary: Record<string, number>;
    by_vendor: Array<Record<string, unknown>>;
    heaviest: Array<Record<string, unknown>>;
  }>('/network-ads/heavy-requests', query);
  return res.data;
}
