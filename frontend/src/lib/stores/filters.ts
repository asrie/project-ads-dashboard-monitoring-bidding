import { writable } from 'svelte/store';
import type { Device } from '../types/models';

export interface GlobalFilters {
  domain_id: string | null;
  date_from: string | null;
  date_to: string | null;
  device: Device | null;
}

function defaultRange(): { date_from: string; date_to: string } {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - 29);
  const fmt = (d: Date) => d.toISOString().slice(0, 10);
  return { date_from: fmt(from), date_to: fmt(to) };
}

const { date_from, date_to } = defaultRange();

export const filters = writable<GlobalFilters>({
  domain_id: null,
  date_from,
  date_to,
  device: null,
});

/** Convert the global filters into a plain query object for the API client. */
export function toQuery(f: GlobalFilters): Record<string, string | null> {
  return {
    domain_id: f.domain_id,
    date_from: f.date_from,
    date_to: f.date_to,
    device: f.device,
  };
}
