import { apiConfig } from './config';
import { clearTokens, getAccessToken, getRefreshToken, saveTokens } from '../services/tokenStorage';

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
  context: { refreshTried?: boolean } = {},
): Promise<T> {
  const token = await getAccessToken();
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), apiConfig.timeoutMs);

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
      throw new ApiError(String(json?.message ?? 'Erro na API.'), response.status, json);
    }

    return json as T;
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }
    if (error instanceof Error && error.name === 'AbortError') {
      throw new ApiError('Tempo de ligação ao servidor esgotado.', 0);
    }
    throw new ApiError(
      `Sem ligação ao servidor (${apiConfig.baseUrl}). Confirme que o backend está activo com php artisan serve --host=0.0.0.0 --port=8000.`,
      0,
    );
  } finally {
    clearTimeout(timeout);
  }
}
