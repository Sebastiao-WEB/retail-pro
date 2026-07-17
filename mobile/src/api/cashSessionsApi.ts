import { httpRequest } from './httpClient';

export type CashSessionReportSnapshot = {
  utilizador?: string;
  caixa?: string;
  aberturaEm?: string;
  fechadoEm?: string;
  fundoInicial?: number;
  totalVendido?: number;
  totalTransacoes?: number;
  ticketMedio?: number;
  vendasDinheiro?: number;
  vendasTransferencia?: number;
  dinheiroEsperado?: number;
  dinheiroReal?: number;
  diferenca?: number;
  transferenciasEsperadas?: number;
  transferenciasReais?: number;
  diferencaTransferencias?: number;
  justificativaDiferenca?: string;
};

export type CashSession = {
  id: string;
  registerId: string;
  registerName: string | null;
  operatorName: string | null;
  status: 'OPEN' | 'CLOSED' | string;
  openingBalance: number;
  closingBalance: number | null;
  differenceAmount: number | null;
  openedAt: string | null;
  closedAt: string | null;
  createdAt: string | null;
  userId: string | null;
  note: string | null;
  reportSnapshot: CashSessionReportSnapshot | null;
};

export type CashSessionsListResponse = {
  items: CashSession[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    register_id: string;
  };
};

export async function fetchClosedCashSessions(
  page = 1,
  perPage = 10,
  search = '',
  registerId?: string,
) {
  const params = new URLSearchParams({
    status: 'CLOSED',
    page: String(page),
    per_page: String(perPage),
  });

  if (search.trim()) {
    params.set('search', search.trim());
  }
  if (registerId) {
    params.set('register_id', registerId);
  }

  const response = await httpRequest<{ data: CashSession[]; meta: CashSessionsListResponse['meta'] }>(
    `/cash-sessions?${params.toString()}`,
  );

  return {
    items: response.data,
    meta: response.meta,
  };
}

export async function fetchActiveCashSession(registerId: string) {
  const params = new URLSearchParams({ register_id: registerId });
  const response = await httpRequest<{ data: CashSession | null }>(
    `/cash-sessions/active?${params.toString()}`,
  );
  return response.data;
}

export async function openCashSession(payload: {
  register_id: string;
  opening_balance: number;
  opened_at?: string;
}) {
  return httpRequest<{ message: string; data: CashSession }>('/cash-sessions/open', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function closeCashSession(
  cashSessionId: string,
  payload: {
    closing_balance: number;
    note?: string;
    closed_at?: string;
    report_snapshot?: Record<string, unknown>;
  },
) {
  return httpRequest<{ message: string; data: CashSession }>(`/cash-sessions/${cashSessionId}/close`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}
