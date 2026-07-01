// Mirrors the Laravel API response envelope (CLAUDE.md API Response Standard).

export interface ApiError {
  code: string;
  message: string;
  field: string | null;
}

export interface PaginationMeta {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface ApiMeta {
  request_id: string;
  timestamp: string;
  pagination?: PaginationMeta;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  meta: ApiMeta;
  errors: ApiError[];
}

/** Thrown by the API client for any non-2xx response. */
export class ApiException extends Error {
  status: number;
  errors: ApiError[];

  constructor(status: number, errors: ApiError[], message?: string) {
    super(message ?? errors[0]?.message ?? 'Request failed');
    this.name = 'ApiException';
    this.status = status;
    this.errors = errors;
  }
}
