export const brand = {
  gold: '#D8B65A',
  dark: '#0F172A',
  darkSoft: '#1E293B',
  success: '#059669',
  danger: '#DC2626',
  muted: '#64748B',
  border: '#E2E8F0',
  background: '#F8FAFC',
  white: '#FFFFFF',
};

export function formatMt(value: number) {
  return `${new Intl.NumberFormat('pt-MZ', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)} MT`;
}
