import { writable, derived, get } from 'svelte/store';
import type { AuthUser } from '../types/models';

const TOKEN_KEY = 'ads_dashboard_token';
const USER_KEY = 'ads_dashboard_user';

function readUser(): AuthUser | null {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as AuthUser;
  } catch {
    return null;
  }
}

export const token = writable<string | null>(localStorage.getItem(TOKEN_KEY));
export const user = writable<AuthUser | null>(readUser());

export const isAuthenticated = derived(token, ($token) => !!$token);

/** Synchronous token accessor used by the API client (no token logging). */
export function getToken(): string | null {
  return get(token);
}

export function setSession(accessToken: string, authUser: AuthUser): void {
  localStorage.setItem(TOKEN_KEY, accessToken);
  localStorage.setItem(USER_KEY, JSON.stringify(authUser));
  token.set(accessToken);
  user.set(authUser);
}

export function clearSession(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
  token.set(null);
  user.set(null);
}
