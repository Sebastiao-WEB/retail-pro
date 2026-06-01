import Constants from 'expo-constants';
import { Platform } from 'react-native';

const extra = Constants.expoConfig?.extra ?? {};

function devServerHost(): string | null {
  const hostUri =
    Constants.expoConfig?.hostUri ??
    (Constants as { expoGoConfig?: { debuggerHost?: string } }).expoGoConfig?.debuggerHost ??
    null;

  if (!hostUri) return null;

  return hostUri.split(':')[0] ?? null;
}

function resolveBaseUrl(): string {
  const configured = String(
    process.env.EXPO_PUBLIC_API_URL ?? extra.apiUrl ?? '',
  ).replace(/\/$/, '');

  const usesLocalhost =
    !configured ||
    configured.includes('localhost') ||
    configured.includes('127.0.0.1');

  if (!usesLocalhost) {
    return configured;
  }

  // Emulador Android: localhost do PC = 10.0.2.2
  if (Platform.OS === 'android') {
    return 'http://10.0.2.2:8000/api/v1';
  }

  // Simulador iOS / web
  if (Platform.OS === 'ios' || Platform.OS === 'web') {
    return 'http://localhost:8000/api/v1';
  }

  const devHost = devServerHost();
  if (devHost) {
    return `http://${devHost}:8000/api/v1`;
  }

  return configured || 'http://localhost:8000/api/v1';
}

export const apiConfig = {
  baseUrl: resolveBaseUrl(),
  timeoutMs: 15000,
};
