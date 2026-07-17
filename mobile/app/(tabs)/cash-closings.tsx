import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Redirect } from 'expo-router';
import { ApiError } from '@/src/api/httpClient';
import {
  fetchClosedCashSessions,
  type CashSession,
  type CashSessionsListResponse,
} from '@/src/api/cashSessionsApi';
import CashSessionDetailModal from '@/src/components/CashSessionDetailModal';
import FullScreenLoader from '@/src/components/FullScreenLoader';
import { useAuthStore } from '@/src/store/authStore';
import { brand, formatMt } from '@/src/theme/brand';
import { homeForClient } from '@/src/utils/clientRoutes';

const PER_PAGE = 10;

function formatarData(valor: string | null | undefined) {
  if (!valor) return '—';
  const data = new Date(valor);
  if (Number.isNaN(data.getTime())) return '—';
  return data.toLocaleString('pt-MZ', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function totalVendido(session: CashSession) {
  return Number(session.reportSnapshot?.totalVendido ?? 0);
}

function diferenca(session: CashSession) {
  const snapshot = session.reportSnapshot?.diferenca;
  if (snapshot !== undefined && snapshot !== null) return Number(snapshot);
  return Number(session.differenceAmount ?? 0);
}

export default function CashClosingsScreen() {
  const { user, client } = useAuthStore();
  const [data, setData] = useState<CashSessionsListResponse | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [registerId, setRegisterId] = useState<string | undefined>(undefined);
  const [loading, setLoading] = useState(true);
  const [paginating, setPaginating] = useState(false);
  const [error, setError] = useState('');
  const [sessaoSelecionada, setSessaoSelecionada] = useState<CashSession | null>(null);
  const [modalAberto, setModalAberto] = useState(false);

  const registers = user?.registers ?? [];

  useEffect(() => {
    if (registerId || registers.length === 0) return;
    setRegisterId(registers[0]?.id);
  }, [registerId, registers]);

  useEffect(() => {
    const timer = setTimeout(() => {
      const trimmed = search.trim();
      if (trimmed === searchQuery) return;
      setSearchQuery(trimmed);
      setPage(1);
      setPaginating(true);
    }, 300);

    return () => clearTimeout(timer);
  }, [search, searchQuery]);

  const load = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      const response = await fetchClosedCashSessions(page, PER_PAGE, searchQuery, registerId);
      setData(response);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar fechos de caixa.');
    } finally {
      setLoading(false);
      setPaginating(false);
    }
  }, [page, searchQuery, registerId]);

  useEffect(() => {
    load();
  }, [load]);

  function abrirDetalhes(sessao: CashSession) {
    setSessaoSelecionada(sessao);
    setModalAberto(true);
  }

  function fecharDetalhes() {
    setModalAberto(false);
    setSessaoSelecionada(null);
  }

  function irParaPagina(pagina: number) {
    if (loading || paginating || pagina === page) return;
    setPaginating(true);
    setPage(pagina);
  }

  const items = data?.items ?? [];
  const totalPaginas = data?.meta.last_page ?? 1;

  if (client === 'pos') {
    return <Redirect href={homeForClient(client)} />;
  }

  return (
    <>
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={brand.gold} />}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.header}>
          <Text style={styles.section}>
            {data ? `${data.meta.total} fecho(s)` : 'Histórico de fechos'}
          </Text>
          <Text style={styles.sectionMeta}>{PER_PAGE} por página</Text>
        </View>

        {registers.length > 1 ? (
          <View style={styles.registerSection}>
            <Text style={styles.sectionMeta}>Caixa</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
              {registers.map((item) => (
                <Pressable
                  key={item.id}
                  style={[styles.chip, registerId === item.id && styles.chipActive]}
                  onPress={() => {
                    if (item.id === registerId) return;
                    setRegisterId(item.id);
                    setPage(1);
                    setPaginating(true);
                  }}
                >
                  <Text style={[styles.chipText, registerId === item.id && styles.chipTextActive]}>
                    {item.name}
                  </Text>
                </Pressable>
              ))}
            </ScrollView>
          </View>
        ) : null}

        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder="Pesquisar por operador, caixa ou nota"
          autoCapitalize="none"
          autoCorrect={false}
          style={styles.searchInput}
        />

        {loading && !data ? <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} /> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}

        {!loading && items.length === 0 ? (
          <Text style={styles.empty}>Nenhum fecho de caixa encontrado.</Text>
        ) : null}

        {items.map((sessao) => {
          const diff = diferenca(sessao);

          return (
            <Pressable key={sessao.id} style={styles.card} onPress={() => abrirDetalhes(sessao)}>
              <View style={styles.cardHeader}>
                <Text style={styles.title} numberOfLines={2}>
                  {sessao.registerName || 'Caixa'}
                </Text>
                <Text style={[styles.diffBadge, diff === 0 ? styles.diffOk : styles.diffWarn]}>
                  {formatMt(diff)}
                </Text>
              </View>
              <Text style={styles.meta}>
                {sessao.operatorName || '—'} · {formatarData(sessao.closedAt)}
              </Text>
              <Text style={styles.meta}>Abertura: {formatarData(sessao.openedAt)}</Text>
              <View style={styles.row}>
                <Text style={styles.soldLabel}>Vendido: {formatMt(totalVendido(sessao))}</Text>
                <Text style={styles.transactions}>
                  {sessao.reportSnapshot?.totalTransacoes ?? '—'} trans.
                </Text>
              </View>
              <Text style={styles.tapHint}>Toque para ver detalhes</Text>
            </Pressable>
          );
        })}

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

      <CashSessionDetailModal
        visible={modalAberto}
        session={sessaoSelecionada}
        onClose={fecharDetalhes}
      />
      <FullScreenLoader visible={paginating} />
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  header: { paddingHorizontal: 16, paddingTop: 16, paddingBottom: 8, gap: 2 },
  section: { fontWeight: '700', color: brand.dark },
  sectionMeta: { fontSize: 12, color: brand.muted },
  registerSection: { paddingHorizontal: 16, paddingBottom: 8, gap: 6 },
  chipRow: { flexDirection: 'row', gap: 8, paddingRight: 16 },
  chip: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: brand.white,
  },
  chipActive: { backgroundColor: brand.dark, borderColor: brand.dark },
  chipText: { color: brand.muted, fontWeight: '600', fontSize: 12 },
  chipTextActive: { color: brand.white },
  searchInput: {
    marginHorizontal: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 15,
    backgroundColor: brand.white,
  },
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
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 8,
  },
  title: { flex: 1, fontWeight: '700', color: brand.dark },
  diffBadge: {
    fontSize: 11,
    fontWeight: '700',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
    color: brand.white,
  },
  diffOk: { backgroundColor: '#16a34a' },
  diffWarn: { backgroundColor: '#b45309' },
  meta: { color: brand.muted, fontSize: 12 },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 2 },
  soldLabel: { color: brand.dark, fontSize: 12, fontWeight: '700' },
  transactions: { color: brand.muted, fontSize: 12, fontWeight: '600' },
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
