import { Platform } from 'react-native';
import { getStoredApiBaseUrl } from '../services/serverConfig';
import { debugLog } from '../utils/debugLog';

export const apiConfig = {
  timeoutMs: 15000,
};

export async function getApiBaseUrl(): Promise<string> {
  const stored = await getStoredApiBaseUrl();
  if (stored) {
    return stored;
  }

  throw new Error(
    'Servidor não configurado. Indique o subdomínio ou URL do cliente antes de continuar.',
  );
}

export function describeApiTarget(baseUrl: string) {
  debugLog('API', `baseUrl=${baseUrl} platform=${Platform.OS}`);
}
