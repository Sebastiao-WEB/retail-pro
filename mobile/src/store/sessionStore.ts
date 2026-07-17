import { create } from 'zustand';
import { clearSessionState, loadSessionState, saveSessionState } from '../services/sessionStorage';
import { enqueuePendingCashClose, enqueuePendingCashOpen } from '../services/offline/pendingQueue';
import { canTryOfflineOperation } from '../services/offline/networkError';
import { hasCatalogOffline } from '../services/offline/catalogCache';
import { closeCashSession, fetchActiveCashSession, openCashSession } from '../api/cashSessionsApi';
import { generateUuid } from '../utils/generateId';
import type { AuthUser } from '../types/auth';

export type SessionState = {
  hydrated: boolean;
  operator: string | null;
  userId: string | null;
  role: string;
  assignedRegister: string;
  registerId: string | null;
  registerCode: string;
  sourceLocationId: string | null;
  sourceLocationCode: string;
  sourceLocationName: string;
  cashSessionId: string | null;
  shiftOpen: boolean;
  openingBalance: number;
  openedAt: string;
};

type SessionStore = SessionState & {
  hydrate: () => Promise<void>;
  initFromUser: (user: AuthUser) => Promise<void>;
  clear: () => Promise<void>;
  openShift: (openingBalance: number) => Promise<{ ok: boolean; offline?: boolean; error?: string }>;
  closeShift: (payload: {
    closingBalance: number;
    note?: string;
    reportSnapshot?: Record<string, unknown>;
  }) => Promise<{ ok: boolean; offline?: boolean; error?: string }>;
  syncRemoteShift: () => Promise<void>;
  persist: () => Promise<void>;
};

function normalizeUuid(value?: string | null) {
  const text = String(value || '').trim();
  return text || null;
}

export const useSessionStore = create<SessionStore>((set, get) => ({
  hydrated: false,
  operator: null,
  userId: null,
  role: 'CASHIER',
  assignedRegister: '',
  registerId: null,
  registerCode: '',
  sourceLocationId: null,
  sourceLocationCode: '',
  sourceLocationName: '',
  cashSessionId: null,
  shiftOpen: false,
  openingBalance: 0,
  openedAt: '',

  hydrate: async () => {
    const saved = await loadSessionState();
    if (saved) {
      set({ ...saved, hydrated: true });
      return;
    }
    set({ hydrated: true });
  },

  initFromUser: async (user) => {
    const register = user.register;
    const source = user.source_location || register?.source_location || null;
    set({
      operator: user.name || user.username || null,
      userId: user.id,
      role: user.role || user.roles?.[0] || 'CASHIER',
      assignedRegister: user.caixa_atribuido || register?.name || '',
      registerId: normalizeUuid(register?.id || user.registers?.[0]?.id || null),
      registerCode: register?.code || user.registers?.[0]?.code || '',
      sourceLocationId: normalizeUuid(source?.id || null),
      sourceLocationCode: source?.code || '',
      sourceLocationName: source?.name || '',
      cashSessionId: null,
      shiftOpen: false,
      openingBalance: 0,
      openedAt: '',
      hydrated: true,
    });
    await get().persist();
    await get().syncRemoteShift();
  },

  clear: async () => {
    set({
      operator: null,
      userId: null,
      role: 'CASHIER',
      assignedRegister: '',
      registerId: null,
      registerCode: '',
      sourceLocationId: null,
      sourceLocationCode: '',
      sourceLocationName: '',
      cashSessionId: null,
      shiftOpen: false,
      openingBalance: 0,
      openedAt: '',
    });
    await clearSessionState();
  },

  persist: async () => {
    const state = get();
    await saveSessionState(state);
  },

  openShift: async (openingBalance) => {
    const state = get();
    if (!state.registerId) {
      return { ok: false, error: 'Caixa não configurado para este utilizador.' };
    }

    const openedAt = new Date().toISOString();
    try {
      const response = await openCashSession({
        register_id: state.registerId,
        opening_balance: openingBalance,
        opened_at: openedAt,
      });
      set({
        shiftOpen: true,
        openingBalance,
        openedAt,
        cashSessionId: response.data.id,
      });
      await get().persist();
      return { ok: true };
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Falha ao abrir caixa.';
      const canOffline = canTryOfflineOperation(error) && (await hasCatalogOffline({}));
      if (!canOffline) {
        return { ok: false, error: message };
      }

      const localSessionId = generateUuid();
      await enqueuePendingCashOpen({
        register_id: state.registerId,
        opening_balance: openingBalance,
        opened_at: openedAt,
        localSessionId,
      });
      set({
        shiftOpen: true,
        openingBalance,
        openedAt,
        cashSessionId: localSessionId,
      });
      await get().persist();
      return { ok: true, offline: true, error: message };
    }
  },

  closeShift: async ({ closingBalance, note = '', reportSnapshot }) => {
    const state = get();
    if (!state.cashSessionId) {
      return { ok: false, error: 'Sessão de caixa não encontrada.' };
    }

    const closedAt = new Date().toISOString();
    try {
      await closeCashSession(state.cashSessionId, {
        closing_balance: closingBalance,
        note,
        closed_at: closedAt,
        report_snapshot: reportSnapshot,
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Falha ao fechar caixa.';
      if (!canTryOfflineOperation(error)) {
        return { ok: false, error: message };
      }
      await enqueuePendingCashClose({
        cashSessionId: state.cashSessionId,
        closing_balance: closingBalance,
        note,
        closed_at: closedAt,
        report_snapshot: reportSnapshot,
      });
    }

    set({
      shiftOpen: false,
      cashSessionId: null,
      openingBalance: 0,
      openedAt: '',
    });
    await get().persist();
    return { ok: true };
  },

  syncRemoteShift: async () => {
    const state = get();
    if (!state.registerId) return;
    try {
      const active = await fetchActiveCashSession(state.registerId);
      if (!active) {
        if (!state.shiftOpen) return;
        set({ shiftOpen: false, cashSessionId: null, openingBalance: 0, openedAt: '' });
        await get().persist();
        return;
      }
      set({
        shiftOpen: true,
        cashSessionId: active.id,
        openingBalance: Number(active.openingBalance || 0),
        openedAt: active.openedAt || state.openedAt,
      });
      await get().persist();
    } catch {
      // Mantém estado local quando offline.
    }
  },
}));
