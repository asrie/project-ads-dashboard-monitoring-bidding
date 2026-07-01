import { apiClient, type Query } from './client';
import type { PaginationMeta } from '../types/api';
import type { SlotRow } from '../types/models';

export interface Paginated<T> {
  items: T[];
  pagination?: PaginationMeta;
}

export async function listSlots(query: Query): Promise<Paginated<SlotRow>> {
  const res = await apiClient.get<SlotRow[]>('/slots', query);
  return { items: res.data, pagination: res.meta.pagination };
}

export async function getSlot(id: string, query: Query): Promise<unknown> {
  const res = await apiClient.get<unknown>(`/slots/${id}`, query);
  return res.data;
}
