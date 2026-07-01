import { apiClient, type Query } from './client';
import type { Paginated } from './slots';
import type { PagePreviewDetail, PagePreviewSummary } from '../types/models';

export async function capturePreview(domainId: string, device = 'mobile'): Promise<PagePreviewDetail> {
  const res = await apiClient.post<PagePreviewDetail>('/previews/capture', {
    domain_id: domainId,
    device,
  });
  return res.data;
}

export async function listPreviews(query: Query): Promise<Paginated<PagePreviewSummary>> {
  const res = await apiClient.get<PagePreviewSummary[]>('/previews', query);
  return { items: res.data, pagination: res.meta.pagination };
}

export async function getPreview(id: string): Promise<PagePreviewDetail> {
  const res = await apiClient.get<PagePreviewDetail>(`/previews/${id}`);
  return res.data;
}
