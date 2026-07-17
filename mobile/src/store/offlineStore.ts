import { create } from 'zustand';
import NetInfo, { type NetInfoState } from '@react-native-community/netinfo';
import { apiConfig } from '../api/config';
import { countPendingQueue } from '../services/offline/pendingQueue';
import { syncOfflineQueue } from '../services/offline/offlineSync';
import { setNetworkOnline } from '../services/offline/networkError';
import { debugLog } from '../utils/debugLog';

type OfflineStore = {
  online: boolean;
  pendingCount: number;
  syncing: boolean;
  init: () => () => void;
  refreshPendingCount: () => Promise<void>;
  syncPending: () => Promise<void>;
};

/** Android Wi‑Fi costuma marcar isInternetReachable=false mesmo com internet. */
function resolveOnline(state: NetInfoState) {
  if (state.isConnected !== true) return false;
  if (state.isInternetReachable === true) return true;
  if (state.isInternetReachable === null || state.isInternetReachable === undefined) {
    return true;
  }
  // Ligado por Wi‑Fi/dados: tentar API mesmo se o reachability do SO falhar.
  return state.type === 'wifi' || state.type === 'cellular' || state.type === 'ethernet';
}

export const useOfflineStore = create<OfflineStore>((set, get) => ({
  online: true,
  pendingCount: 0,
  syncing: false,

  init: () => {
    NetInfo.configure({
      reachabilityUrl: `${apiConfig.baseUrl}/products?page=1&per_page=1`,
      reachabilityTest: async (response) => response.status < 500,
      reachabilityLongTimeout: 30 * 1000,
      reachabilityShortTimeout: 5 * 1000,
    });

    const unsubscribe = NetInfo.addEventListener((state) => {
      const online = resolveOnline(state);
      debugLog('Net', `estado: connected=${String(state.isConnected)} reachable=${String(state.isInternetReachable)} type=${state.type} → online=${online}`);
      const wasOffline = !get().online;
      setNetworkOnline(online);
      set({ online });
      if (wasOffline && online) {
        void get().syncPending();
      }
    });

    void NetInfo.fetch().then((state) => {
      const online = resolveOnline(state);
      setNetworkOnline(online);
      set({ online });
    });

    void get().refreshPendingCount();

    return unsubscribe;
  },

  refreshPendingCount: async () => {
    const pendingCount = await countPendingQueue();
    set({ pendingCount });
  },

  syncPending: async () => {
    if (get().syncing) return;
    set({ syncing: true });
    try {
      await syncOfflineQueue();
    } finally {
      await get().refreshPendingCount();
      set({ syncing: false });
    }
  },
}));
