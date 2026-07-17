import { create } from 'zustand';
import {
  adminLogin,
  fetchMe,
  logout as apiLogout,
  posLogin,
  twoFactorChallenge,
  ApiError,
  type RegisterOption,
} from '../api/authApi';
import { clearAuthClient, getAuthClient, saveAuthClient } from '../services/authClientStorage';
import { getAccessToken } from '../services/tokenStorage';
import type { AuthUser } from '../types/auth';
import { useSessionStore } from './sessionStore';
import { useProductStore } from './productStore';

export type LoginMode = 'pos' | 'admin';

type AuthState = {
  user: AuthUser | null;
  client: 'admin' | 'pos' | null;
  loginMode: LoginMode;
  hydrated: boolean;
  loading: boolean;
  twoFactorToken: string | null;
  pendingClient: 'admin' | 'pos' | null;
  awaitingRegisterSelection: boolean;
  availableRegisters: RegisterOption[];
  hydrate: () => Promise<void>;
  setLoginMode: (mode: LoginMode) => void;
  clearRegisterSelection: () => void;
  login: (username: string, password: string, options?: { registerCode?: string }) => Promise<void>;
  confirmTwoFactor: (code: string, recovery?: boolean) => Promise<void>;
  cancelTwoFactor: () => void;
  logout: () => Promise<void>;
};

async function finishPosSession(user: AuthUser) {
  await useSessionStore.getState().initFromUser(user);
  const session = useSessionStore.getState();
  const filters = session.sourceLocationId
    ? { source_location_id: session.sourceLocationId }
    : ({} as Record<string, string>);
  try {
    await useProductStore.getState().loadCatalog(filters);
  } catch {
    // Catálogo offline pode estar disponível após tentativa de rede.
  }
}

async function applySession(user: AuthUser | null, client: 'admin' | 'pos') {
  await saveAuthClient(client);
  if (client === 'pos' && user) {
    await finishPosSession(user);
  }
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  client: null,
  loginMode: 'pos',
  hydrated: false,
  loading: false,
  twoFactorToken: null,
  pendingClient: null,
  awaitingRegisterSelection: false,
  availableRegisters: [],

  hydrate: async () => {
    try {
      const token = await getAccessToken();
      if (!token) {
        await clearAuthClient();
        set({ user: null, client: null, hydrated: true });
        return;
      }

      const [me, storedClient] = await Promise.all([fetchMe(), getAuthClient()]);
      // Preferir cliente persistido no login: evita /me antigo tratar admin com caixa como POS.
      const client = storedClient ?? me.client ?? 'admin';
      if (storedClient !== client) {
        await saveAuthClient(client);
      }

      set({ user: me.user, client, hydrated: true });
      if (client === 'pos' && me.user) {
        await finishPosSession(me.user);
      }
    } catch {
      await clearAuthClient().catch(() => undefined);
      set({ user: null, client: null, hydrated: true });
    }
  },

  setLoginMode: (mode) => {
    set({
      loginMode: mode,
      awaitingRegisterSelection: false,
      availableRegisters: [],
    });
  },

  clearRegisterSelection: () => {
    set({ awaitingRegisterSelection: false, availableRegisters: [] });
  },

  login: async (username, password, options = {}) => {
    const mode = get().loginMode;
    set({ loading: true, awaitingRegisterSelection: false, availableRegisters: [] });

    try {
      if (mode === 'pos') {
        const response = await posLogin(username, password, options.registerCode);

        if ('requires_register_selection' in response && response.requires_register_selection) {
          set({
            loading: false,
            awaitingRegisterSelection: true,
            availableRegisters: response.registers,
            pendingClient: 'pos',
          });
          return;
        }

        if ('requires_two_factor' in response && response.requires_two_factor && response.two_factor_token) {
          set({
            twoFactorToken: response.two_factor_token,
            pendingClient: 'pos',
            loading: false,
          });
          return;
        }

        const user = 'user' in response ? (response.user ?? null) : null;
        set({
          user,
          client: 'pos',
          twoFactorToken: null,
          pendingClient: null,
          loading: false,
        });
        await applySession(user, 'pos');
        return;
      }

      const response = await adminLogin(username, password);
      if ('requires_two_factor' in response && response.requires_two_factor && response.two_factor_token) {
        set({
          twoFactorToken: response.two_factor_token,
          pendingClient: 'admin',
          loading: false,
        });
        return;
      }
      const user = 'user' in response ? (response.user ?? null) : null;
      const client = response.client ?? 'admin';
      set({
        user,
        client,
        twoFactorToken: null,
        pendingClient: null,
        loading: false,
      });
      await applySession(user, client);
    } catch (error) {
      set({ loading: false, pendingClient: null });
      throw error;
    }
  },

  confirmTwoFactor: async (code, recovery = false) => {
    const token = get().twoFactorToken;
    if (!token) throw new ApiError('Sessão 2FA expirada.', 401);

    set({ loading: true });
    try {
      const response = await twoFactorChallenge(token, recovery ? undefined : code, recovery ? code : undefined);
      const client = response.client ?? get().pendingClient ?? 'admin';
      const user = response.user ?? null;
      set({
        user,
        client,
        twoFactorToken: null,
        pendingClient: null,
        loading: false,
      });
      await applySession(user, client);
    } catch (error) {
      set({ loading: false });
      throw error;
    }
  },

  cancelTwoFactor: () => set({ twoFactorToken: null, pendingClient: null }),

  logout: async () => {
    await apiLogout();
    await clearAuthClient().catch(() => undefined);
    await useSessionStore.getState().clear();
    set({
      user: null,
      client: null,
      twoFactorToken: null,
      pendingClient: null,
      awaitingRegisterSelection: false,
      availableRegisters: [],
    });
  },
}));
