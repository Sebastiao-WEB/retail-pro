import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {
  fetchReversalRequests,
  updateReversalRequest,
  type ReversalRequest,
  type ReversalRequestsResponse,
} from '@/src/api/reversalsApi';
import { ApiError } from '@/src/api/httpClient';
import SaleDetailModal from '@/src/components/SaleDetailModal';
import { brand, formatMt } from '@/src/theme/brand';

const PER_PAGE = 10;

function formatarData(valor: string | null | undefined) {
  if (!valor) return '—';
  return new Date(valor).toLocaleString('pt-MZ', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function traduzirEstado(status: string) {
  if (status === 'PENDING') return 'Pendente';
  if (status === 'APPROVED') return 'Aprovada';
  if (status === 'REJECTED') return 'Rejeitada';
  return status;
}

function corEstado(status: string) {
  if (status === 'PENDING') return brand.gold;
  if (status === 'APPROVED') return brand.success;
  if (status === 'REJECTED') return brand.danger;
  return brand.muted;
}

export default function ReversalsScreen() {
  const [data, setData] = useState<ReversalRequestsResponse | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [reversaoSelecionada, setReversaoSelecionada] = useState<ReversalRequest | null>(null);
  const [modalDetalhesAberto, setModalDetalhesAberto] = useState(false);

  const load = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      const response = await fetchReversalRequests(page, PER_PAGE);
      setData(response);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar reversões.');
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    load();
  }, [load]);

  function confirmarDecisao(item: ReversalRequest, status: 'APPROVED' | 'REJECTED') {
    const referencia = item.sale?.referencia || item.saleId.slice(0, 8);
    const aprovar = status === 'APPROVED';

    Alert.alert(
      aprovar ? 'Confirmar aprovação' : 'Confirmar rejeição',
      aprovar
        ? `Deseja aprovar a reversão da venda ${referencia}?`
        : `Deseja rejeitar a reversão da venda ${referencia}?`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: aprovar ? 'Aprovar' : 'Rejeitar',
          style: aprovar ? 'default' : 'destructive',
          onPress: () => executarDecisao(item, status),
        },
      ],
    );
  }

  async function executarDecisao(item: ReversalRequest, status: 'APPROVED' | 'REJECTED') {
    if (submitting) return;

    setSubmitting(true);
    try {
      await updateReversalRequest(item.id, status);
      await load();
    } catch (err) {
      Alert.alert('Erro', err instanceof ApiError ? err.message : 'Não foi possível actualizar.');
    } finally {
      setSubmitting(false);
    }
  }

  function abrirDetalhes(item: ReversalRequest) {
    if (!item.sale) return;
    setReversaoSelecionada(item);
    setModalDetalhesAberto(true);
  }

  function fecharDetalhes() {
    setModalDetalhesAberto(false);
    setReversaoSelecionada(null);
  }

  const items = data?.items ?? [];
  const totalPaginas = data?.meta.last_page ?? 1;
  const pendentes = items.filter((item) => item.status === 'PENDING').length;

  return (
    <>
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={brand.gold} />}
      >
        <View style={styles.header}>
          <Text style={styles.section}>
            {data ? `${data.meta.total} reversão(ões)` : 'Reversões'}
          </Text>
          {data ? (
            <Text style={styles.sectionMeta}>
              {pendentes} pendente(s) nesta página · {PER_PAGE} por página
            </Text>
          ) : null}
        </View>

        {loading && !data ? <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} /> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}

        {!loading && items.length === 0 ? (
          <Text style={styles.empty}>Nenhuma reversão registada.</Text>
        ) : null}

        {items.map((item) => (
          <View key={item.id} style={styles.card}>
            <Pressable onPress={() => abrirDetalhes(item)} disabled={!item.sale}>
              <View style={styles.cardHeader}>
                <Text style={styles.title}>
                  {item.sale?.referencia || `Venda ${item.saleId.slice(0, 8)}…`}
                </Text>
                <Text style={[styles.badge, { color: corEstado(item.status) }]}>
                  {traduzirEstado(item.status)}
                </Text>
              </View>
              <Text style={styles.meta}>
                {formatarData(item.requestedAt)} · {item.requestedBy}
              </Text>
              {item.sale ? (
                <Text style={styles.total}>{formatMt(item.sale.total)}</Text>
              ) : null}
              {item.reason ? (
                <Text style={styles.reasonPreview} numberOfLines={2}>
                  {item.reason}
                </Text>
              ) : null}
              {item.sale ? <Text style={styles.tapHint}>Toque para ver detalhes</Text> : null}
            </Pressable>

            {item.status === 'PENDING' ? (
              <View style={styles.actions}>
                <Pressable
                  style={[styles.btn, styles.reject, submitting && styles.btnDisabled]}
                  disabled={submitting}
                  onPress={() => confirmarDecisao(item, 'REJECTED')}
                >
                  <Text style={styles.btnText}>Rejeitar</Text>
                </Pressable>
                <Pressable
                  style={[styles.btn, styles.approve, submitting && styles.btnDisabled]}
                  disabled={submitting}
                  onPress={() => confirmarDecisao(item, 'APPROVED')}
                >
                  <Text style={styles.btnText}>Aprovar</Text>
                </Pressable>
              </View>
            ) : null}
          </View>
        ))}

        {data && data.meta.total > 0 ? (
          <View style={styles.pagination}>
            <Pressable
              style={[styles.pageButton, page <= 1 && styles.pageButtonDisabled]}
              disabled={page <= 1 || loading}
              onPress={() => setPage((pagina) => Math.max(1, pagina - 1))}
            >
              <Text style={styles.pageButtonText}>Anterior</Text>
            </Pressable>
            <Text style={styles.pageInfo}>
              Página {page} de {totalPaginas}
            </Text>
            <Pressable
              style={[styles.pageButton, page >= totalPaginas && styles.pageButtonDisabled]}
              disabled={page >= totalPaginas || loading}
              onPress={() => setPage((pagina) => Math.min(totalPaginas, pagina + 1))}
            >
              <Text style={styles.pageButtonText}>Seguinte</Text>
            </Pressable>
          </View>
        ) : null}
      </ScrollView>

      <SaleDetailModal
        visible={modalDetalhesAberto}
        sale={reversaoSelecionada?.sale ?? null}
        reversal={reversaoSelecionada}
        onClose={fecharDetalhes}
      />

      <Modal visible={submitting} transparent animationType="fade" onRequestClose={() => {}}>
        <View style={styles.loaderOverlay}>
          <ActivityIndicator size="large" color={brand.gold} />
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  header: { paddingHorizontal: 16, paddingTop: 16, paddingBottom: 8, gap: 2 },
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
  title: { flex: 1, fontWeight: '700', color: brand.dark },
  badge: { fontSize: 12, fontWeight: '700' },
  meta: { color: brand.muted, fontSize: 12 },
  total: { fontWeight: '700', color: brand.dark, marginTop: 2 },
  reasonPreview: { color: brand.dark, marginTop: 4, fontSize: 13 },
  tapHint: { color: brand.muted, fontSize: 11, marginTop: 6 },
  actions: { flexDirection: 'row', gap: 8, marginTop: 12 },
  btn: { flex: 1, borderRadius: 8, paddingVertical: 10, alignItems: 'center' },
  approve: { backgroundColor: brand.success },
  reject: { backgroundColor: brand.danger },
  btnText: { color: brand.white, fontWeight: '700' },
  btnDisabled: { opacity: 0.6 },
  loaderOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    justifyContent: 'center',
    alignItems: 'center',
  },
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
