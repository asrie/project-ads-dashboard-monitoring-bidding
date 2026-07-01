import { apiClient, type Query } from './client';
import type { Paginated } from './slots';
import type { BidderRow } from '../types/models';

export async function listBidders(query: Query): Promise<Paginated<BidderRow>> {
  const res = await apiClient.get<BidderRow[]>('/bidders', query);
  return { items: res.data, pagination: res.meta.pagination };
}

export async function getBidder(id: string, query: Query): Promise<unknown> {
  const res = await apiClient.get<unknown>(`/bidders/${id}`, query);
  return res.data;
}
