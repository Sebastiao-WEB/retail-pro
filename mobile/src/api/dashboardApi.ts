import { httpRequest } from './httpClient';

export type DashboardSummary = {
  period: string;
  registerId: string | null;
  metrics: {
    totalVendasPeriodo: number;
    totalProdutos: number;
    totalClientes: number;
    recargasMes: number;
    reversoesPendentes: number;
    caixasAtivos: number;
  };
  charts: {
    vendasPorDia: { labels: string[]; values: number[] };
    metodosPagamento: Array<{ metodo: string; quantidade: number; valor: number }>;
  };
  ultimasVendas: Array<{
    id: string;
    referencia: string;
    cliente: string;
    caixa: string;
    metodoPagamento: string;
    total: number;
    estado: string;
    data: string | null;
  }>;
};

export async function fetchDashboardSummary(period = '7d') {
  const response = await httpRequest<{ data: DashboardSummary }>(`/dashboard/summary?period=${period}`);
  return response.data;
}
