import { create } from 'zustand';
import { adminLogin, fetchMe, logout as apiLogout, twoFactorChallenge, ApiError } from '../api/authApi';
import { getAccessToken } from '../services/tokenStorage';
import type { AuthUser } from '../types/auth';

type AuthState = {
  user: AuthUser | null;
  client: 'admin' | 'pos' | null;
  hydrated: boolean;
  loading: boolean;
  twoFactorToken: string | null;
  hydrate: () => Promise<void>;
  login: (username: string, password: string) => Promise<void>;
  confirmTwoFactor: (code: string, recovery?: boolean) => Promise<void>;
  cancelTwoFactor: () => void;
  logout: () => Promise<void>;
};

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  client: null,
  hydrated: false,
  loading: false,
  twoFactorToken: null,

  hydrate: async () => {
    try {
      const token = await getAccessToken();
      if (!token) {
        set({ user: null, client: null, hydrated: true });
        return;
      }
      const me = await fetchMe();
      set({ user: me.user, client: me.client, hydrated: true });
    } catch {
      set({ user: null, client: null, hydrated: true });
    }
  },

  login: async (username, password) => {
    set({ loading: true });
    try {
      const response = await adminLogin(username, password);
      if ('requires_two_factor' in response && response.requires_two_factor && response.two_factor_token) {
        set({ twoFactorToken: response.two_factor_token, loading: false });
        return;
      }
      set({
        user: 'user' in response ? (response.user ?? null) : null,
        client: response.client ?? 'admin',
        twoFactorToken: null,
        loading: false,
      });
    } catch (error) {
      set({ loading: false });
      throw error;
    }
  },

  confirmTwoFactor: async (code, recovery = false) => {
    const token = get().twoFactorToken;
    if (!token) throw new ApiError('Sessão 2FA expirada.', 401);

    set({ loading: true });
    try {
      const response = await twoFactorChallenge(token, recovery ? undefined : code, recovery ? code : undefined);
      set({
        user: response.user ?? null,
        client: response.client ?? 'admin',
        twoFactorToken: null,
        loading: false,
      });
    } catch (error) {
      set({ loading: false });
      throw error;
    }
  },

  cancelTwoFactor: () => set({ twoFactorToken: null }),

  logout: async () => {
    await apiLogout();
    set({ user: null, client: null, twoFactorToken: null });
  },
}));
