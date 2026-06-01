import { useCallback, useEffect, useState } from 'react';
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
import { brand, formatMt } from '@/src/theme/brand';

const periods = [
  { key: 'today', label: 'Hoje' },
  { key: '7d', label: '7 dias' },
  { key: '30d', label: '30 dias' },
  { key: 'month', label: 'Mês' },
] as const;

export default function DashboardScreen() {
  const [period, setPeriod] = useState<(typeof periods)[number]['key']>('7d');
  const [data, setData] = useState<DashboardSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setError('');
    try {
      const summary = await fetchDashboardSummary(period);
      setData(summary);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar dashboard.');
    } finally {
      setLoading(false);
    }
  }, [period]);

  useEffect(() => {
    setLoading(true);
    load();
  }, [load]);

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={brand.gold} />}
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

      {loading && !data ? (
        <ActivityIndicator color={brand.gold} style={{ marginTop: 40 }} />
      ) : null}

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
            <MetricCard label="Reversões pendentes" value={String(data.metrics.reversoesPendentes)} highlight={data.metrics.reversoesPendentes > 0} />
            <MetricCard label="Caixas activos" value={String(data.metrics.caixasAtivos)} />
          </View>

          <Text style={styles.sectionTitle}>Últimas vendas</Text>
          {data.ultimasVendas.length === 0 ? (
            <Text style={styles.empty}>Sem vendas no período.</Text>
          ) : (
            data.ultimasVendas.map((sale) => (
              <View key={sale.id} style={styles.saleRow}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.saleRef}>{sale.referencia}</Text>
                  <Text style={styles.saleMeta}>{sale.cliente} · {sale.caixa}</Text>
                </View>
                <Text style={styles.saleTotal}>{formatMt(sale.total)}</Text>
              </View>
            ))
          )}
        </>
      ) : null}
    </ScrollView>
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
  sectionTitle: { paddingHorizontal: 16, fontSize: 16, fontWeight: '700', color: brand.dark, marginBottom: 8 },
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
  empty: { paddingHorizontal: 16, color: brand.muted },
  error: { color: brand.danger, padding: 16 },
});
