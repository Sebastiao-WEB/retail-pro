import { ApiError } from '../../api/httpClient';

let networkOnline = true;

export function setNetworkOnline(value: boolean) {
  networkOnline = value;
}

export function isNetworkError(error: unknown) {
  if (!error) return true;
  if (error instanceof ApiError) {
    if (!error.status) return true;
    if (error.status >= 500) return true;
    return false;
  }
  return true;
}

export function isBusinessSaleError(error: unknown) {
  return error instanceof ApiError && error.status === 422;
}

export function isConnectivityMessage(message: string) {
  const text = String(message || '').toLowerCase();
  return (
    text.includes('timeout') ||
    text.includes('communication') ||
    text.includes('comunicação') ||
    text.includes('comunicacao') ||
    text.includes('failed to fetch') ||
    text.includes('network') ||
    text.includes('abort') ||
    text.includes('alcançar') ||
    text.includes('alcancar') ||
    text.includes('sem ligação ao servidor')
  );
}

export function networkAvailable() {
  return networkOnline;
}

function isApiBusinessError(error: unknown) {
  if (!(error instanceof ApiError)) return false;
  if (!error.status) return false;
  // 401/403/404/422 etc. — não é falha de rede, é auth/regra de negócio.
  return error.status >= 400 && error.status < 500 && error.status !== 408 && error.status !== 429;
}

export function canTryOfflineOperation(errorOrMessage?: unknown) {
  if (isApiBusinessError(errorOrMessage)) {
    return false;
  }
  if (!networkAvailable()) return true;
  if (typeof errorOrMessage === 'string') return isConnectivityMessage(errorOrMessage);
  return isNetworkError(errorOrMessage);
}

export function formatRequestError(error: unknown, fallback = 'Erro de ligação ao servidor.') {
  if (error instanceof ApiError) {
    if (error.status === 401) {
      return 'Sessão expirada. Termine sessão e volte a entrar.';
    }
    if (error.message) return error.message;
  }
  if (error instanceof Error && error.message) return error.message;
  return fallback;
}
