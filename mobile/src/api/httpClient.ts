import { apiConfig, getApiBaseUrl } from './config';
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

function firstValidationMessage(payload: Record<string, unknown> | null): string | null {
  if (!payload || typeof payload !== 'object') return null;
  const errors = payload.errors;
  if (!errors || typeof errors !== 'object' || Array.isArray(errors)) return null;

  for (const value of Object.values(errors as Record<string, unknown>)) {
    if (Array.isArray(value) && value.length > 0 && value[0] != null) {
      return String(value[0]);
    }
    if (typeof value === 'string' && value.trim()) {
      return value;
    }
  }
  return null;
}

let refreshPromise: Promise<boolean> | null = null;

async function tryRefreshToken() {
  const refreshToken = await getRefreshToken();
  if (!refreshToken) return false;

  if (!refreshPromise) {
    refreshPromise = (async () => {
      try {
        const baseUrl = await getApiBaseUrl();
        const response = await fetch(`${baseUrl}/auth/refresh`, {
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
  let baseUrl: string;
  try {
    baseUrl = await getApiBaseUrl();
  } catch (error) {
    throw new ApiError(
      error instanceof Error ? error.message : 'Servidor não configurado.',
      0,
    );
  }

  const token = await getAccessToken();
  const controller = new AbortController();
  const timeoutMs = context.timeoutMs ?? apiConfig.timeoutMs;
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  const method = options.method ?? 'GET';
  const started = Date.now();

  debugLog('HTTP', `${method} ${path} (timeout ${timeoutMs}ms)`);

  try {
    const response = await fetch(`${baseUrl}${path}`, {
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
      const validationMessage = firstValidationMessage(json);
      throw new ApiError(
        String(validationMessage ?? json?.message ?? 'Erro na API.'),
        response.status,
        json,
      );
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
      `Sem ligação ao servidor (${baseUrl}). Verifique a internet e a configuração do servidor no app.`,
      0,
    );
  } finally {
    clearTimeout(timeout);
  }
}
