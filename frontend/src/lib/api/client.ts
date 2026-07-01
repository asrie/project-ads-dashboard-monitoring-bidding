import { ApiException, type ApiResponse } from '../types/api';
import { getToken, clearSession } from '../stores/auth';

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1';

type Query = Record<string, string | number | boolean | null | undefined>;

function buildUrl(path: string, query?: Query): string {
  const url = new URL(`${BASE_URL}${path}`, window.location.origin);
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== null && value !== undefined && value !== '') {
        url.searchParams.set(key, String(value));
      }
    }
  }
  return url.toString();
}

interface RequestOptions {
  query?: Query;
  body?: unknown;
  // Skip the Authorization header (e.g. login).
  auth?: boolean;
}

async function request<T>(method: string, path: string, opts: RequestOptions = {}): Promise<ApiResponse<T>> {
  const { query, body, auth = true } = opts;

  const headers: Record<string, string> = {
    Accept: 'application/json',
  };

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  if (auth) {
    const tk = getToken();
    if (tk) {
      headers['Authorization'] = `Bearer ${tk}`;
    }
  }

  let response: Response;
  try {
    response = await fetch(buildUrl(path, query), {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiException(0, [
      { code: 'NETWORK_ERROR', message: 'Tidak dapat terhubung ke server.', field: null },
    ]);
  }

  // 401 -> session expired/invalid. Clear and bounce to login.
  if (response.status === 401) {
    clearSession();
    if (!window.location.hash.startsWith('#/login')) {
      window.location.hash = '#/login';
    }
  }

  let payload: ApiResponse<T> | null = null;
  const text = await response.text();
  if (text) {
    try {
      payload = JSON.parse(text) as ApiResponse<T>;
    } catch {
      payload = null;
    }
  }

  if (!response.ok || !payload?.success) {
    const errors = payload?.errors?.length
      ? payload.errors
      : [{ code: 'HTTP_ERROR', message: `Request gagal (${response.status}).`, field: null }];
    throw new ApiException(response.status, errors);
  }

  return payload;
}

export const apiClient = {
  get: <T>(path: string, query?: Query) => request<T>('GET', path, { query }),
  post: <T>(path: string, body?: unknown, opts?: RequestOptions) =>
    request<T>('POST', path, { ...opts, body }),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, { body }),
};

export type { Query };
