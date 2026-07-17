import { create } from 'zustand';
import { fetchSalesList, type SaleDetail } from '../api/salesApi';
import { createReversalRequest, fetchReversalRequests, type ReversalRequest } from '../api/reversalsApi';
import {
  reversalBlockReason,
  roundMoney,
  saleBelongsToCurrentShift,
  saleBelongsToCurrentUser,
} from '../utils/saleShift';
import { useSessionStore } from './sessionStore';

export type ShiftMetrics = {
  totalVendido: number;
  totalTransacoes: number;
  ticketMedio: number;
  vendasDinheiro: number;
  vendasTransferencia: number;
  dinheiroEsperado: number;
  transferenciasEsperadas: number;
};

type ShiftSalesStore = {
  sales: SaleDetail[];
  reversals: ReversalRequest[];
  loading: boolean;
  load: () => Promise<void>;
  requestReversal: (saleId: string, reason?: string) => Promise<void>;
  getShiftSales: () => SaleDetail[];
  getMetrics: (openingBalance?: number) => ShiftMetrics;
  canRequestReversal: (sale: SaleDetail) => string;
};

function filterShiftSales(sales: SaleDetail[]) {
  const session = useSessionStore.getState();
  return sales.filter(
    (sale) => saleBelongsToCurrentShift(sale, session) && saleBelongsToCurrentUser(sale, session),
  );
}

export const useShiftSalesStore = create<ShiftSalesStore>((set, get) => ({
  sales: [],
  reversals: [],
  loading: false,

  load: async () => {
    const session = useSessionStore.getState();
    if (!session.shiftOpen || !session.cashSessionId) {
      set({ sales: [], reversals: [] });
      return;
    }

    set({ loading: true });
    try {
      const [salesResponse, reversalsResponse] = await Promise.all([
        fetchSalesList({ cashSessionId: session.cashSessionId, perPage: 50 }),
        fetchReversalRequests(1, 50),
      ]);
      set({
        sales: filterShiftSales(salesResponse.items),
        reversals: reversalsResponse.items,
      });
    } finally {
      set({ loading: false });
    }
  },

  requestReversal: async (saleId, reason = '') => {
    await createReversalRequest(saleId, reason);
    await get().load();
  },

  getShiftSales: () => filterShiftSales(get().sales),

  getMetrics: (openingBalance = 0) => {
    const vendas = get().getShiftSales();
    const totalVendido = roundMoney(vendas.reduce((acc, sale) => acc + Number(sale.total || 0), 0));
    const totalTransacoes = vendas.length;
    const ticketMedio = totalTransacoes > 0 ? roundMoney(totalVendido / totalTransacoes) : 0;
    const vendasDinheiro = roundMoney(
      vendas
        .filter((sale) => sale.metodoPagamento === 'Dinheiro')
        .reduce((acc, sale) => acc + Number(sale.total || 0), 0),
    );
    const vendasTransferencia = roundMoney(
      vendas
        .filter((sale) => sale.metodoPagamento === 'Transferência')
        .reduce((acc, sale) => acc + Number(sale.total || 0), 0),
    );
    const fundo = roundMoney(openingBalance);
    return {
      totalVendido,
      totalTransacoes,
      ticketMedio,
      vendasDinheiro,
      vendasTransferencia,
      dinheiroEsperado: roundMoney(fundo + vendasDinheiro),
      transferenciasEsperadas: vendasTransferencia,
    };
  },

  canRequestReversal: (sale) => reversalBlockReason(sale, get().reversals),
}));
