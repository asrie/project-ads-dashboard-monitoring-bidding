import { apiClient, type Query } from './client';
import type { Paginated } from './slots';
import type { ServerHealth } from '../types/models';

export async function getServerHealth(query: Query): Promise<ServerHealth> {
  const res = await apiClient.get<ServerHealth>('/server/health', query);
  return res.data;
}

export async function listServerChecks(query: Query): Promise<Paginated<Record<string, unknown>>> {
  const res = await apiClient.get<Record<string, unknown>[]>('/server/checks', query);
  return { items: res.data, pagination: res.meta.pagination };
}
