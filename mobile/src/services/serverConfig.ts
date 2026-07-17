import { getDatabase } from './database/db';
import { debugLog } from '../utils/debugLog';

const SERVER_URL_KEY = 'retailpro:api_base_url';
export const DEFAULT_TENANT_DOMAIN = 'mozretailpos.com';

let cachedBaseUrl: string | null | undefined;

export function clearApiBaseUrlCache() {
  cachedBaseUrl = undefined;
}

export async function getStoredApiBaseUrl(): Promise<string | null> {
  if (cachedBaseUrl !== undefined) {
    return cachedBaseUrl;
  }

  try {
    const db = await getDatabase();
    const row = await db.getFirstAsync<{ value: string }>(
      'SELECT value FROM app_kv WHERE key = ?',
      SERVER_URL_KEY,
    );
    const value = String(row?.value || '').trim().replace(/\/$/, '') || null;
    cachedBaseUrl = value;
    return value;
  } catch {
    cachedBaseUrl = null;
    return null;
  }
}

/** Migra URL de .env (produção) uma vez, se ainda não houver configuração no dispositivo. */
export async function hydrateServerConfigFromEnv(): Promise<string | null> {
  const stored = await getStoredApiBaseUrl();
  if (stored) return stored;

  const fromEnv = String(process.env.EXPO_PUBLIC_API_URL || '').trim();
  if (
    fromEnv &&
    !fromEnv.includes('localhost') &&
    !fromEnv.includes('127.0.0.1') &&
    !fromEnv.includes('10.0.2.2')
  ) {
    try {
      return await saveApiBaseUrl(fromEnv);
    } catch {
      return null;
    }
  }

  return null;
}

export async function hasConfiguredServer(): Promise<boolean> {
  const url = await getStoredApiBaseUrl();
  return Boolean(url);
}

export async function saveApiBaseUrl(url: string): Promise<string> {
  const normalized = normalizeServerInput(url);
  const db = await getDatabase();
  await db.runAsync(
    `INSERT INTO app_kv (key, value) VALUES (?, ?)
     ON CONFLICT(key) DO UPDATE SET value = excluded.value`,
    SERVER_URL_KEY,
    normalized,
  );
  cachedBaseUrl = normalized;
  debugLog('API', `servidor configurado: ${normalized}`);
  return normalized;
}

export async function clearApiBaseUrl(): Promise<void> {
  const db = await getDatabase();
  await db.runAsync('DELETE FROM app_kv WHERE key = ?', SERVER_URL_KEY);
  cachedBaseUrl = null;
}

/**
 * Aceita:
 * - subdomínio: "padaria" → https://padaria.mozretailpos.com/api/v1
 * - domínio: "padaria.mozretailpos.com" → https://padaria.mozretailpos.com/api/v1
 * - URL: "https://padaria.mozretailpos.com" → .../api/v1
 * - URL completa: "https://padaria.mozretailpos.com/api/v1"
 */
export function normalizeServerInput(raw: string): string {
  let value = String(raw || '').trim();
  if (!value) {
    throw new Error('Indique o endereço do servidor ou o subdomínio do cliente.');
  }

  value = value.replace(/\s+/g, '');

  // Apenas subdomínio (ex.: padaria)
  if (/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i.test(value)) {
    return `https://${value.toLowerCase()}.${DEFAULT_TENANT_DOMAIN}/api/v1`;
  }

  if (!/^https?:\/\//i.test(value)) {
    value = `https://${value}`;
  }

  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new Error('Endereço inválido. Ex.: padaria ou https://padaria.mozretailpos.com');
  }

  if (!url.hostname.includes('.')) {
    throw new Error('Endereço inválido. Ex.: padaria ou https://padaria.mozretailpos.com');
  }

  let path = url.pathname.replace(/\/$/, '');
  if (!path || path === '/') {
    path = '/api/v1';
  } else if (!path.endsWith('/api/v1')) {
    path = `${path}/api/v1`.replace(/\/{2,}/g, '/');
  }

  return `${url.protocol}//${url.host}${path}`.replace(/\/$/, '');
}

export function displayServerLabel(baseUrl: string | null | undefined): string {
  if (!baseUrl) return 'Não configurado';
  try {
    const url = new URL(baseUrl);
    return url.host;
  } catch {
    return baseUrl;
  }
}

export function extractSubdomainHint(baseUrl: string | null | undefined): string {
  if (!baseUrl) return '';
  try {
    const host = new URL(baseUrl).hostname;
    const suffix = `.${DEFAULT_TENANT_DOMAIN}`;
    if (host.endsWith(suffix)) {
      return host.slice(0, -suffix.length);
    }
    return host;
  } catch {
    return '';
  }
}
