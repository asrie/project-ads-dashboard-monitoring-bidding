import { apiClient, type Query } from './client';
import type { Paginated } from './slots';
import type { PrebidHealth } from '../types/models';

export async function getPrebidHealth(query: Query): Promise<PrebidHealth> {
  const res = await apiClient.get<PrebidHealth>('/prebid/health', query);
  return res.data;
}

export async function listAuctions(query: Query): Promise<Paginated<Record<string, unknown>>> {
  const res = await apiClient.get<Record<string, unknown>[]>('/prebid/auctions', query);
  return { items: res.data, pagination: res.meta.pagination };
}
