import { apiConfig } from './config';
import { clearTokens, getAccessToken, getRefreshToken, saveTokens } from '../services/tokenStorage';
import { debugLog } from '../utils/debugLog';

export class ApiError extends Error {
  status: number;
  payload: Record<string, unknown> | null;

  constructor(message: string, status = 0, payload: Record<string, unknown> | null = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

let refreshPromise: Promise<boolean> | null = null;

async function tryRefreshToken() {
  const refreshToken = await getRefreshToken();
  if (!refreshToken) return false;

  if (!refreshPromise) {
    refreshPromise = (async () => {
      try {
        const response = await fetch(`${apiConfig.baseUrl}/auth/refresh`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({ refresh_token: refreshToken }),
        });
        const json = await response.json().catch(() => null);
        if (!response.ok || !json?.access_token) return false;
        await saveTokens(json.access_token, json.refresh_token ?? refreshToken);
        return true;
      } catch {
        return false;
      } finally {
        refreshPromise = null;
      }
    })();
  }

  return refreshPromise;
}

export async function httpRequest<T = unknown>(
  path: string,
  options: RequestInit = {},
  context: { refreshTried?: boolean; timeoutMs?: number } = {},
): Promise<T> {
  const token = await getAccessToken();
  const controller = new AbortController();
  const timeoutMs = context.timeoutMs ?? apiConfig.timeoutMs;
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  const method = options.method ?? 'GET';
  const started = Date.now();

  debugLog('HTTP', `${method} ${path} (timeout ${timeoutMs}ms)`);

  try {
    const response = await fetch(`${apiConfig.baseUrl}${path}`, {
      ...options,
      signal: controller.signal,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers ?? {}),
      },
    });

    const text = await response.text();
    let json: Record<string, unknown> | null = null;

    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      throw new ApiError('Resposta inválida do servidor.', response.status);
    }

    if (response.status === 401 && !context.refreshTried) {
      const refreshed = await tryRefreshToken();
      if (refreshed) {
        return httpRequest(path, options, { refreshTried: true });
      }
      await clearTokens();
    }

    if (!response.ok) {
      debugLog('HTTP', `${method} ${path} → ${response.status} (${Date.now() - started}ms)`);
      throw new ApiError(String(json?.message ?? 'Erro na API.'), response.status, json);
    }

    debugLog('HTTP', `${method} ${path} → ${response.status} (${Date.now() - started}ms)`);
    return json as T;
  } catch (error) {
    if (error instanceof ApiError) {
      if (error.status) {
        debugLog('HTTP', `${method} ${path} falhou: ${error.status} ${error.message}`);
      } else {
        debugLog('HTTP', `${method} ${path} falhou: ${error.message}`);
      }
      throw error;
    }
    if (error instanceof Error && error.name === 'AbortError') {
      throw new ApiError('Tempo de ligação ao servidor esgotado.', 0);
    }
    throw new ApiError(
      `Sem ligação ao servidor (${apiConfig.baseUrl}). Verifique internet/Wi‑Fi e a URL em mobile/.env.`,
      0,
    );
  } finally {
    clearTimeout(timeout);
  }
}
