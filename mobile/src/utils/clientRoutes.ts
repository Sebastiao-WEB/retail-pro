export type AppClient = 'admin' | 'pos';

/** Ecrãs exclusivos de gestão (admin/gerente). */
export const ADMIN_ONLY_SCREENS = new Set([
  'index',
  'products',
  'stock',
  'users',
  'cash-closings',
]);

/** Ecrãs exclusivos do operador de caixa. */
export const POS_ONLY_SCREENS = new Set(['pos']);

export function homeForClient(client: AppClient | null | undefined) {
  // Expo Router: o ecrã index das tabs é "/" (não "/(tabs)/index").
  return client === 'pos' ? '/(tabs)/pos' : '/';
}

export function isScreenAllowedForClient(
  client: AppClient | null | undefined,
  screen: string | undefined,
) {
  if (!client || !screen) return true;
  if (client === 'admin' && POS_ONLY_SCREENS.has(screen)) return false;
  if (client === 'pos' && ADMIN_ONLY_SCREENS.has(screen)) return false;
  return true;
}
