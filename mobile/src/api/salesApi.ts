import { httpRequest } from './httpClient';

export type SaleItem = {
  produtoId?: string | null;
  nome: string;
  quantidade: number;
  precoVenda: number;
  precoSemIva?: number;
  ivaPercentual?: number;
  valorIvaUnitario?: number;
  subtotal: number;
};

export type SaleDetail = {
  id: string;
  referencia: string;
  cliente: string;
  caixa: string;
  operador?: string | null;
  metodoPagamento: string;
  total: number;
  estado: string;
  data: string | null;
  createdAt?: string | null;
  subtotal: number;
  descontoAplicado: number;
  valorPago?: number;
  troco?: number;
  userId?: string | null;
  registerId?: string | null;
  cashSessionId?: string | null;
  itens: SaleItem[];
};

export type SalesPeriod = 'today' | '7d' | '30d' | 'month';

export type FetchSalesOptions = {
  page?: number;
  perPage?: number;
  period?: SalesPeriod;
  cashSessionId?: string;
  registerId?: string;
};

export type SalesListResponse = {
  items: SaleDetail[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    register_id?: string;
  };
};

export type RecentSalesResponse = {
  items: SaleDetail[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function fetchSalesList(options: FetchSalesOptions = {}) {
  const params = new URLSearchParams({
    page: String(options.page ?? 1),
    per_page: String(options.perPage ?? 10),
  });
  if (options.period) params.set('period', options.period);
  if (options.cashSessionId) params.set('cash_session_id', options.cashSessionId);
  if (options.registerId) params.set('register_id', options.registerId);

  const response = await httpRequest<{ data: SaleDetail[]; meta: SalesListResponse['meta'] }>(
    `/sales?${params.toString()}`,
  );

  return {
    items: response.data,
    meta: response.meta,
  };
}

export async function fetchSales(page = 1, perPage = 10, period: SalesPeriod = '7d') {
  return fetchSalesList({ page, perPage, period });
}

export type CreateSalePayload = {
  id: string;
  cliente: string;
  caixa?: string;
  operador?: string;
  register_id?: string;
  registerId?: string;
  source_location_id?: string;
  sourceLocationId?: string;
  cash_session_id?: string;
  cashSessionId?: string;
  metodoPagamento: string;
  subtotal: number;
  descontoAplicado?: number;
  total: number;
  valorPago?: number;
  troco?: number;
  data?: string;
  itens: SaleItem[];
  stockVersions?: Record<string, string>;
};

export async function createSale(payload: CreateSalePayload) {
  return httpRequest<{ message: string; data: SaleDetail & { status?: string } }>('/sales', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function fetchSaleById(id: string) {
  return httpRequest<{ data: SaleDetail }>(`/sales/${id}`);
}

export function calcularIvaTotalItem(item: SaleItem) {
  const quantidade = Number(item.quantidade || 0);
  const valorIvaUnitario = Number(item.valorIvaUnitario || 0);
  return Number((valorIvaUnitario * quantidade).toFixed(2));
}
