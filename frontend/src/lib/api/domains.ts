import { apiClient } from './client';
import type { DomainRef } from '../types/models';

export async function listDomains(): Promise<DomainRef[]> {
  const res = await apiClient.get<DomainRef[]>('/domains');
  return res.data;
}
