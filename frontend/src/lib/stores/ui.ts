import { writable } from 'svelte/store';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

export interface Toast {
  id: number;
  type: ToastType;
  message: string;
}

export const toasts = writable<Toast[]>([]);

let counter = 0;

export function pushToast(message: string, type: ToastType = 'info', timeout = 4000): void {
  const id = ++counter;
  toasts.update((list) => [...list, { id, type, message }]);
  if (timeout > 0) {
    setTimeout(() => dismissToast(id), timeout);
  }
}

export function dismissToast(id: number): void {
  toasts.update((list) => list.filter((t) => t.id !== id));
}
