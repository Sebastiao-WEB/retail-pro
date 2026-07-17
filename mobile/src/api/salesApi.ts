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
  itens: SaleItem[];
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

export type SalesPeriod = 'today' | '7d' | '30d' | 'month';

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

export async function fetchSales(page = 1, perPage = 10, period: SalesPeriod = '7d') {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
    period,
  });
  const response = await httpRequest<{ data: SaleDetail[]; meta: SalesListResponse['meta'] }>(
    `/sales?${params.toString()}`,
  );

  return {
    items: response.data,
    meta: response.meta,
  };
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
