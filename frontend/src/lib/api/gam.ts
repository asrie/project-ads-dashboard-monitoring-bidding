import { apiClient, type Query } from './client';
import type { Paginated } from './slots';
import type { GamHealth } from '../types/models';

export async function getGamHealth(query: Query): Promise<GamHealth> {
  const res = await apiClient.get<GamHealth>('/gam/health', query);
  return res.data;
}

export async function listGamRequests(query: Query): Promise<Paginated<Record<string, unknown>>> {
  const res = await apiClient.get<Record<string, unknown>[]>('/gam/requests', query);
  return { items: res.data, pagination: res.meta.pagination };
}
