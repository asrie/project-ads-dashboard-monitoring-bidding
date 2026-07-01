import { apiClient } from './client';
import { setSession, clearSession } from '../stores/auth';
import type { AuthUser, LoginResponse } from '../types/models';

export async function login(email: string, password: string): Promise<AuthUser> {
  const res = await apiClient.post<LoginResponse>('/auth/login', { email, password }, { auth: false });
  setSession(res.data.access_token, res.data.user);
  return res.data.user;
}

export async function logout(): Promise<void> {
  try {
    await apiClient.post('/auth/logout');
  } finally {
    clearSession();
  }
}

export async function me(): Promise<AuthUser> {
  const res = await apiClient.get<{ user: AuthUser }>('/auth/me');
  return res.data.user;
}
