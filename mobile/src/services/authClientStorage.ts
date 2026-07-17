import * as SecureStore from 'expo-secure-store';

const CLIENT_KEY = 'retailpro.auth-client';

export type StoredAuthClient = 'admin' | 'pos';

export async function saveAuthClient(client: StoredAuthClient) {
  await SecureStore.setItemAsync(CLIENT_KEY, client);
}

export async function getAuthClient(): Promise<StoredAuthClient | null> {
  const value = await SecureStore.getItemAsync(CLIENT_KEY);
  if (value === 'admin' || value === 'pos') return value;
  return null;
}

export async function clearAuthClient() {
  await SecureStore.deleteItemAsync(CLIENT_KEY);
}
