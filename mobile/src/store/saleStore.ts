import { create } from 'zustand';
import { createSale, type CreateSalePayload } from '../api/salesApi';
import { enqueuePendingSale } from '../services/offline/pendingQueue';
import { canTryOfflineOperation, isBusinessSaleError } from '../services/offline/networkError';
import { generateUuid } from '../utils/generateId';
import { useSessionStore } from './sessionStore';
import { useProductStore } from './productStore';
import { useOfflineStore } from './offlineStore';

function buildReference() {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  const seq = String(now.getTime() % 100000).padStart(5, '0');
  return `VD-${y}${m}${d}-${seq}`;
}

type SaleStore = {
  registerSale: (input: {
    cliente: string;
    itens: CreateSalePayload['itens'];
    subtotal: number;
    descontoAplicado: number;
    total: number;
    metodoPagamento: string;
    valorPago: number;
    troco: number;
  }) => Promise<{ mode: 'online' | 'offline'; saleId: string; referencia: string }>;
};

export const useSaleStore = create<SaleStore>(() => ({
  registerSale: async (input) => {
    const session = useSessionStore.getState();
    const saleId = generateUuid();
    const referencia = buildReference();
    const payload: CreateSalePayload = {
      id: saleId,
      cliente: input.cliente,
      caixa: session.assignedRegister || session.registerCode,
      operador: session.operator || undefined,
      register_id: session.registerId || undefined,
      registerId: session.registerId || undefined,
      source_location_id: session.sourceLocationId || undefined,
      sourceLocationId: session.sourceLocationId || undefined,
      cash_session_id: session.cashSessionId || undefined,
      cashSessionId: session.cashSessionId || undefined,
      metodoPagamento: input.metodoPagamento,
      subtotal: input.subtotal,
      descontoAplicado: input.descontoAplicado,
      total: input.total,
      valorPago: input.valorPago,
      troco: input.troco,
      data: new Date().toISOString(),
      itens: input.itens,
      stockVersions: {},
    };

    useProductStore.getState().applySale(input.itens);

    try {
      await createSale(payload);
      void useOfflineStore.getState().refreshPendingCount();
      return { mode: 'online', saleId, referencia };
    } catch (error) {
      if (isBusinessSaleError(error)) {
        useProductStore.getState().restoreSaleStock(input.itens);
        throw error;
      }
      if (!canTryOfflineOperation(error)) {
        useProductStore.getState().restoreSaleStock(input.itens);
        throw error;
      }
      await enqueuePendingSale(payload as unknown as Record<string, unknown>);
      await useOfflineStore.getState().refreshPendingCount();
      return { mode: 'offline', saleId, referencia };
    }
  },
}));
