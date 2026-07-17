import { useCallback, useEffect, useState } from 'react';
import { Redirect } from 'expo-router';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { fetchDashboardSummary, type DashboardSummary } from '@/src/api/dashboardApi';
import { ApiError } from '@/src/api/httpClient';
import type { SaleDetail } from '@/src/api/salesApi';
import FullScreenLoader from '@/src/components/FullScreenLoader';
import SaleDetailModal from '@/src/components/SaleDetailModal';
import { useAuthStore } from '@/src/store/authStore';
import { brand, formatMt } from '@/src/theme/brand';
import { homeForClient } from '@/src/utils/clientRoutes';

const periods = [
  { key: 'today', label: 'Hoje' },
  { key: '7d', label: '7 dias' },
  { key: '30d', label: '30 dias' },
  { key: 'month', label: 'Mês' },
] as const;

function formatarData(valor: string | null | undefined) {
  if (!valor) return '—';
  return new Date(valor).toLocaleString('pt-MZ', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function DashboardScreen() {
  const client = useAuthStore((state) => state.client);
  const [period, setPeriod] = useState<(typeof periods)[number]['key']>('7d');
  const [data, setData] = useState<DashboardSummary | null>(null);
  const [salesPage, setSalesPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [paginating, setPaginating] = useState(false);
  const [error, setError] = useState('');
  const [saleSelecionada, setSaleSelecionada] = useState<SaleDetail | null>(null);
  const [modalDetalhesAberto, setModalDetalhesAberto] = useState(false);

  const loadDashboard = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      const summary = await fetchDashboardSummary(period, salesPage);
      setData(summary);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar dashboard.');
    } finally {
      setLoading(false);
      setPaginating(false);
    }
  }, [period, salesPage]);

  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  function abrirDetalhes(sale: SaleDetail) {
    setSaleSelecionada(sale);
    setModalDetalhesAberto(true);
  }

  function fecharDetalhes() {
    setModalDetalhesAberto(false);
    setSaleSelecionada(null);
  }

  function irParaPagina(pagina: number) {
    if (loading || paginating || pagina === salesPage) return;
    setPaginating(true);
    setSalesPage(pagina);
  }

  const salesData = data?.recentSales ?? null;
  const totalPaginas = salesData?.meta.last_page ?? 1;

  if (client === 'pos') {
    return <Redirect href={homeForClient(client)} />;
  }

  return (
    <>
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={loadDashboard} tintColor={brand.gold} />}
      >
        <View style={styles.periodRow}>
          {periods.map((item) => (
            <Pressable
              key={item.key}
              style={[styles.periodChip, period === item.key && styles.periodChipActive]}
              onPress={() => {
                setPeriod(item.key);
                setLoading(true);
              }}
            >
              <Text style={[styles.periodText, period === item.key && styles.periodTextActive]}>{item.label}</Text>
            </Pressable>
          ))}
        </View>

        {loading && !data ? <ActivityIndicator color={brand.gold} style={{ marginTop: 40 }} /> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}

        {data ? (
          <>
            <View style={styles.heroCard}>
              <Text style={styles.heroLabel}>Vendas no período</Text>
              <Text style={styles.heroValue}>{formatMt(data.metrics.totalVendasPeriodo)}</Text>
            </View>

            <View style={styles.grid}>
              <MetricCard label="Produtos activos" value={String(data.metrics.totalProdutos)} />
              <MetricCard label="Clientes activos" value={String(data.metrics.totalClientes)} />
              <MetricCard label="Recargas (mês)" value={String(data.metrics.recargasMes)} />
              <MetricCard
                label="Reversões pendentes"
                value={String(data.metrics.reversoesPendentes)}
                highlight={data.metrics.reversoesPendentes > 0}
              />
              <MetricCard label="Caixas activos" value={String(data.metrics.caixasAtivos)} />
            </View>
          </>
        ) : null}

        <View style={styles.salesHeader}>
          <Text style={styles.sectionTitle}>Últimas vendas</Text>
          {salesData ? (
            <Text style={styles.sectionMeta}>
              {salesData.meta.total} no total · 5 por página
            </Text>
          ) : null}
        </View>

        {loading && !salesData ? <ActivityIndicator color={brand.gold} style={{ marginTop: 16 }} /> : null}

        {!loading && salesData?.items.length === 0 ? (
          <Text style={styles.empty}>Sem vendas registadas.</Text>
        ) : null}

        {salesData?.items.map((sale) => (
          <Pressable key={sale.id} style={styles.saleRow} onPress={() => abrirDetalhes(sale)}>
            <View style={{ flex: 1 }}>
              <Text style={styles.saleRef}>{sale.referencia || sale.id}</Text>
              <Text style={styles.saleMeta}>
                {formatarData(sale.data || sale.createdAt)} · {sale.cliente} · {sale.caixa}
              </Text>
            </View>
            <Text style={styles.saleTotal}>{formatMt(sale.total)}</Text>
          </Pressable>
        ))}

        {salesData && salesData.meta.total > 0 ? (
          <View style={styles.pagination}>
            <Pressable
              style={[styles.pageButton, salesPage <= 1 && styles.pageButtonDisabled]}
              disabled={salesPage <= 1 || loading || paginating}
              onPress={() => irParaPagina(Math.max(1, salesPage - 1))}
            >
              <Text style={styles.pageButtonText}>Anterior</Text>
            </Pressable>
            <Text style={styles.pageInfo}>
              Página {salesPage} de {totalPaginas}
            </Text>
            <Pressable
              style={[styles.pageButton, salesPage >= totalPaginas && styles.pageButtonDisabled]}
              disabled={salesPage >= totalPaginas || loading || paginating}
              onPress={() => irParaPagina(Math.min(totalPaginas, salesPage + 1))}
            >
              <Text style={styles.pageButtonText}>Seguinte</Text>
            </Pressable>
          </View>
        ) : null}
      </ScrollView>

      <SaleDetailModal visible={modalDetalhesAberto} sale={saleSelecionada} onClose={fecharDetalhes} />
      <FullScreenLoader visible={paginating} />
    </>
  );
}

function MetricCard({ label, value, highlight = false }: { label: string; value: string; highlight?: boolean }) {
  return (
    <View style={[styles.metricCard, highlight && styles.metricHighlight]}>
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={styles.metricValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  periodRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, padding: 16 },
  periodChip: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: brand.white,
  },
  periodChipActive: { backgroundColor: brand.dark, borderColor: brand.dark },
  periodText: { color: brand.muted, fontWeight: '600', fontSize: 12 },
  periodTextActive: { color: brand.white },
  heroCard: {
    marginHorizontal: 16,
    backgroundColor: brand.dark,
    borderRadius: 16,
    padding: 20,
  },
  heroLabel: { color: '#CBD5E1', fontSize: 13, fontWeight: '600' },
  heroValue: { color: brand.gold, fontSize: 32, fontWeight: '800', marginTop: 8 },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    padding: 16,
  },
  metricCard: {
    width: '47%',
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: brand.border,
  },
  metricHighlight: { borderColor: brand.gold, backgroundColor: '#FFFBEB' },
  metricLabel: { color: brand.muted, fontSize: 12, fontWeight: '600' },
  metricValue: { color: brand.dark, fontSize: 22, fontWeight: '800', marginTop: 6 },
  salesHeader: {
    paddingHorizontal: 16,
    marginBottom: 8,
    gap: 2,
  },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: brand.dark },
  sectionMeta: { fontSize: 12, color: brand.muted },
  saleRow: {
    marginHorizontal: 16,
    marginBottom: 8,
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: brand.border,
  },
  saleRef: { fontWeight: '700', color: brand.dark },
  saleMeta: { color: brand.muted, fontSize: 12, marginTop: 2 },
  saleTotal: { fontWeight: '700', color: brand.dark },
  pagination: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 24,
  },
  pageButton: {
    backgroundColor: brand.white,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  pageButtonDisabled: {
    opacity: 0.45,
  },
  pageButtonText: {
    color: brand.dark,
    fontWeight: '700',
    fontSize: 13,
  },
  pageInfo: {
    color: brand.muted,
    fontSize: 13,
    fontWeight: '600',
  },
  empty: { paddingHorizontal: 16, color: brand.muted },
  error: { color: brand.danger, padding: 16 },
});
