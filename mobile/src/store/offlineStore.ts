import { create } from 'zustand';
import NetInfo from '@react-native-community/netinfo';
import { countPendingQueue } from '../services/offline/pendingQueue';
import { syncOfflineQueue } from '../services/offline/offlineSync';
import { setNetworkOnline } from '../services/offline/networkError';

type OfflineStore = {
  online: boolean;
  pendingCount: number;
  syncing: boolean;
  init: () => () => void;
  refreshPendingCount: () => Promise<void>;
  syncPending: () => Promise<void>;
};

export const useOfflineStore = create<OfflineStore>((set, get) => ({
  online: true,
  pendingCount: 0,
  syncing: false,

  init: () => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      const online = Boolean(state.isConnected && state.isInternetReachable !== false);
      const wasOffline = !get().online;
      setNetworkOnline(online);
      set({ online });
      if (wasOffline && online) {
        void get().syncPending();
      }
    });

    void NetInfo.fetch().then((state) => {
      const online = Boolean(state.isConnected && state.isInternetReachable !== false);
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
