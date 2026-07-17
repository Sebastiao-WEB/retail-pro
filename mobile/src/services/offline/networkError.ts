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
    text.includes('alcancar')
  );
}

export function networkAvailable() {
  return networkOnline;
}

export function canTryOfflineOperation(errorOrMessage?: unknown) {
  if (!networkAvailable()) return true;
  if (typeof errorOrMessage === 'string') return isConnectivityMessage(errorOrMessage);
  return isNetworkError(errorOrMessage);
}
