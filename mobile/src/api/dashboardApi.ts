import { httpRequest } from './httpClient';
import type { RecentSalesResponse } from './salesApi';

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
  recentSales: RecentSalesResponse;
};

export async function fetchDashboardSummary(period = '7d', salesPage = 1, salesPerPage = 5) {
  const params = new URLSearchParams({
    period,
    sales_page: String(salesPage),
    sales_per_page: String(salesPerPage),
  });
  const response = await httpRequest<{ data: DashboardSummary }>(`/dashboard/summary?${params.toString()}`);
  return response.data;
}
