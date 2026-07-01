const numberFmt = new Intl.NumberFormat('en-US');
const currencyFmt = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  maximumFractionDigits: 2,
});

export function num(value: number | null | undefined): string {
  if (value === null || value === undefined) return '–';
  return numberFmt.format(value);
}

export function currency(value: number | null | undefined): string {
  if (value === null || value === undefined) return '–';
  return currencyFmt.format(value);
}

export function percent(value: number | null | undefined, digits = 1): string {
  if (value === null || value === undefined) return '–';
  return `${value.toFixed(digits)}%`;
}

export function ms(value: number | null | undefined): string {
  if (value === null || value === undefined) return '–';
  return `${numberFmt.format(Math.round(value))} ms`;
}

export function bytes(value: number | null | undefined): string {
  if (value === null || value === undefined) return '–';
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / (1024 * 1024)).toFixed(2)} MB`;
}

export function dateTime(value: string | null | undefined): string {
  if (!value) return '–';
  const d = new Date(value);
  return d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

export function timeAgo(value: string | null | undefined): string {
  if (!value) return '–';
  const diff = Date.now() - new Date(value).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'baru saja';
  if (mins < 60) return `${mins} menit lalu`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours} jam lalu`;
  const days = Math.floor(hours / 24);
  return `${days} hari lalu`;
}
