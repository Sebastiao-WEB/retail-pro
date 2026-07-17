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
import { ApiError } from '@/src/api/httpClient';
import { fetchSales, type SaleDetail, type SalesListResponse, type SalesPeriod } from '@/src/api/salesApi';
import FullScreenLoader from '@/src/components/FullScreenLoader';
import SaleDetailModal from '@/src/components/SaleDetailModal';
import { useAuthStore } from '@/src/store/authStore';
import { brand, formatMt } from '@/src/theme/brand';

const PER_PAGE = 10;

const periods = [
  { key: 'today', label: 'Hoje' },
  { key: '7d', label: '7 dias' },
  { key: '30d', label: '30 dias' },
  { key: 'month', label: 'Mês' },
] as const satisfies ReadonlyArray<{ key: SalesPeriod; label: string }>;

function formatarData(valor: string | null | undefined) {
  if (!valor) return '—';
  return new Date(valor).toLocaleString('pt-MZ', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function SalesScreen() {
  const { client } = useAuthStore();
  const modoPos = client === 'pos';
  const [period, setPeriod] = useState<SalesPeriod>('7d');
  const [data, setData] = useState<SalesListResponse | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [paginating, setPaginating] = useState(false);
  const [error, setError] = useState('');
  const [saleSelecionada, setSaleSelecionada] = useState<SaleDetail | null>(null);
  const [modalDetalhesAberto, setModalDetalhesAberto] = useState(false);

  const load = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      const response = await fetchSales(page, PER_PAGE, period);
      setData(response);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar vendas.');
    } finally {
      setLoading(false);
      setPaginating(false);
    }
  }, [page, period]);

  useEffect(() => {
    load();
  }, [load]);

  function abrirDetalhes(sale: SaleDetail) {
    setSaleSelecionada(sale);
    setModalDetalhesAberto(true);
  }

  function fecharDetalhes() {
    setModalDetalhesAberto(false);
    setSaleSelecionada(null);
  }

  function irParaPagina(pagina: number) {
    if (loading || paginating || pagina === page) return;
    setPaginating(true);
    setPage(pagina);
  }

  function alterarPeriodo(novoPeriodo: SalesPeriod) {
    if (novoPeriodo === period) return;
    setPeriod(novoPeriodo);
    setPage(1);
    setPaginating(true);
  }

  const items = data?.items ?? [];
  const totalPaginas = data?.meta.last_page ?? 1;

  return (
    <>
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={brand.gold} />}
      >
        <View style={styles.periodRow}>
          {periods.map((item) => (
            <Pressable
              key={item.key}
              style={[styles.periodChip, period === item.key && styles.periodChipActive]}
              onPress={() => alterarPeriodo(item.key)}
            >
              <Text style={[styles.periodText, period === item.key && styles.periodTextActive]}>
                {item.label}
              </Text>
            </Pressable>
          ))}
        </View>

        <View style={styles.header}>
          <Text style={styles.section}>
            {data ? `${data.meta.total} venda(s)` : 'Vendas'}
          </Text>
          {modoPos ? (
            <Text style={styles.sectionMeta}>Apenas as suas vendas neste caixa</Text>
          ) : null}
          {data ? (
            <Text style={styles.sectionMeta}>
              Mais recentes primeiro · {PER_PAGE} por página
            </Text>
          ) : null}
        </View>

        {loading && !data ? <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} /> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}

        {!loading && items.length === 0 ? (
          <Text style={styles.empty}>Nenhuma venda registada.</Text>
        ) : null}

        {items.map((sale) => (
          <Pressable key={sale.id} style={styles.card} onPress={() => abrirDetalhes(sale)}>
            <View style={styles.cardHeader}>
              <Text style={styles.ref}>{sale.referencia || sale.id}</Text>
              <Text style={styles.estado}>{sale.estado}</Text>
            </View>
            <Text style={styles.meta}>
              {formatarData(sale.data || sale.createdAt)} · {sale.cliente} · {sale.metodoPagamento}
            </Text>
            <View style={styles.row}>
              <Text style={styles.caixa}>{sale.caixa || '—'}</Text>
              <Text style={styles.total}>{formatMt(sale.total)}</Text>
            </View>
            <Text style={styles.tapHint}>Toque para ver detalhes</Text>
          </Pressable>
        ))}

        {data && data.meta.total > 0 ? (
          <View style={styles.pagination}>
            <Pressable
              style={[styles.pageButton, page <= 1 && styles.pageButtonDisabled]}
              disabled={page <= 1 || loading || paginating}
              onPress={() => irParaPagina(Math.max(1, page - 1))}
            >
              <Text style={styles.pageButtonText}>Anterior</Text>
            </Pressable>
            <Text style={styles.pageInfo}>
              Página {page} de {totalPaginas}
            </Text>
            <Pressable
              style={[styles.pageButton, page >= totalPaginas && styles.pageButtonDisabled]}
              disabled={page >= totalPaginas || loading || paginating}
              onPress={() => irParaPagina(Math.min(totalPaginas, page + 1))}
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

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  periodRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, padding: 16, paddingBottom: 8 },
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
  header: { paddingHorizontal: 16, paddingTop: 8, paddingBottom: 8, gap: 2 },
  section: { fontWeight: '700', color: brand.dark },
  sectionMeta: { fontSize: 12, color: brand.muted },
  empty: { paddingHorizontal: 16, color: brand.muted },
  error: { color: brand.danger, padding: 16 },
  card: {
    marginHorizontal: 16,
    marginBottom: 12,
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: brand.border,
    gap: 6,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  ref: { flex: 1, fontWeight: '700', color: brand.dark },
  estado: { fontSize: 12, fontWeight: '700', color: brand.muted },
  meta: { color: brand.muted, fontSize: 12 },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 2 },
  caixa: { color: brand.muted, fontSize: 12, fontWeight: '600' },
  total: { fontWeight: '700', color: brand.dark },
  tapHint: { color: brand.muted, fontSize: 11, marginTop: 4 },
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
  pageButtonDisabled: { opacity: 0.45 },
  pageButtonText: { color: brand.dark, fontWeight: '700', fontSize: 13 },
  pageInfo: { color: brand.muted, fontSize: 13, fontWeight: '600' },
});
