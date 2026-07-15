import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { ApiError } from '@/src/api/httpClient';
import { fetchProducts, type Product, type ProductsListResponse } from '@/src/api/productsApi';
import {
  fetchStockAvailability,
  fetchStockLocations,
  fetchStockMovements,
  type StockLocationOption,
  type StockMovement,
} from '@/src/api/stockApi';
import FullScreenLoader from '@/src/components/FullScreenLoader';
import StockActionModal, { type StockActionMode } from '@/src/components/StockActionModal';
import { useAuthStore } from '@/src/store/authStore';
import type { AuthUser } from '@/src/types/auth';
import { brand } from '@/src/theme/brand';

const PER_PAGE = 10;

type ViewMode = 'inventory' | 'movements';

function temPermissao(user: AuthUser | null, permission: string) {
  if (!user) return false;
  if (user.permissions?.includes(permission)) return true;
  const roles = user.roles ?? (user.role ? [user.role] : []);
  return roles.some((role) => role === 'ADMIN' || role === 'MANAGER');
}

function formatarStock(stock: number, unidade: Product['unidadeVenda']) {
  const valor = Number(stock || 0);
  return unidade === 'KG' ? `${valor} kg` : String(valor);
}

const tiposMovimento: Record<string, string> = {
  IN: 'Entrada',
  OUT: 'Saída',
  TRANSFER: 'Transferência',
  ADJUSTMENT: 'Ajuste',
  RETURN: 'Devolução',
};

function traduzirTipoMovimento(type?: string) {
  return tiposMovimento[type ?? ''] ?? type ?? '—';
}

function formatarData(iso?: string | null) {
  if (!iso) return '—';
  const data = new Date(iso);
  if (Number.isNaN(data.getTime())) return '—';
  return data.toLocaleString('pt-MZ', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function StockScreen() {
  const { user } = useAuthStore();
  const podeRecarregar = temPermissao(user, 'stock.reload');
  const podeTransferir = temPermissao(user, 'stock.transfers.manage');
  const podeVerMovimentos = temPermissao(user, 'stock.movements.view');

  const [viewMode, setViewMode] = useState<ViewMode>('inventory');
  const [locations, setLocations] = useState<StockLocationOption[]>([]);
  const [locationId, setLocationId] = useState<string | null>(null);
  const [data, setData] = useState<ProductsListResponse | null>(null);
  const [stockLocal, setStockLocal] = useState<Record<string, number>>({});
  const [movements, setMovements] = useState<StockMovement[]>([]);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [paginating, setPaginating] = useState(false);
  const [error, setError] = useState('');
  const [produtoSelecionado, setProdutoSelecionado] = useState<Product | null>(null);
  const [modalAberto, setModalAberto] = useState(false);
  const [acaoModal, setAcaoModal] = useState<StockActionMode>('reload');

  const locationMap = useMemo(
    () => Object.fromEntries(locations.map((item) => [item.id, item.name])),
    [locations],
  );

  useEffect(() => {
    fetchStockLocations()
      .then((items) => {
        setLocations(items);
        setLocationId((actual) => {
          if (actual) return actual;
          const preferida = items.find((item) => item.isSaleable) ?? items[0];
          return preferida?.id ?? null;
        });
      })
      .catch(() => setLocations([]));
  }, []);

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

  const loadInventory = useCallback(async () => {
    if (!locationId) return;

    setError('');
    setLoading(true);
    try {
      const response = await fetchProducts(page, PER_PAGE, searchQuery);
      setData(response);

      const ids = response.items.map((item) => item.id);
      const availability = await fetchStockAvailability(locationId, ids);
      const mapa: Record<string, number> = {};
      for (const id of ids) {
        mapa[id] = availability[id]?.quantity ?? 0;
      }
      setStockLocal(mapa);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar inventário.');
    } finally {
      setLoading(false);
      setPaginating(false);
    }
  }, [page, searchQuery, locationId]);

  const loadMovements = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      const items = await fetchStockMovements(
        locationId ? { location_id: locationId } : undefined,
      );
      setMovements(items);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar movimentos.');
    } finally {
      setLoading(false);
      setPaginating(false);
    }
  }, [locationId]);

  const load = useCallback(async () => {
    if (viewMode === 'inventory') {
      await loadInventory();
    } else {
      await loadMovements();
    }
  }, [viewMode, loadInventory, loadMovements]);

  useEffect(() => {
    load();
  }, [load]);

  function abrirAcao(produto: Product, mode: StockActionMode) {
    setProdutoSelecionado(produto);
    setAcaoModal(mode);
    setModalAberto(true);
  }

  function mostrarAcoes(produto: Product) {
    const opcoes: Array<{ label: string; mode: StockActionMode }> = [];

    if (podeRecarregar) {
      opcoes.push({ label: 'Recarregar', mode: 'reload' });
      opcoes.push({ label: 'Ajustar', mode: 'adjust' });
    }
    if (podeTransferir) {
      opcoes.push({ label: 'Transferir', mode: 'transfer' });
    }

    if (opcoes.length === 0) {
      Alert.alert('Sem permissão', 'Não tem permissão para operações de stock.');
      return;
    }

    Alert.alert(produto.nome, 'Escolha a operação', [
      ...opcoes.map((item) => ({
        text: item.label,
        onPress: () => abrirAcao(produto, item.mode),
      })),
      { text: 'Cancelar', style: 'cancel' },
    ]);
  }

  function fecharModal() {
    setModalAberto(false);
    setProdutoSelecionado(null);
  }

  function irParaPagina(pagina: number) {
    if (loading || paginating || pagina === page) return;
    setPaginating(true);
    setPage(pagina);
  }

  function mudarVista(mode: ViewMode) {
    if (mode === viewMode) return;
    setViewMode(mode);
    setPage(1);
    setPaginating(true);
  }

  const items = data?.items ?? [];
  const totalPaginas = data?.meta.last_page ?? 1;

  const movimentosFiltrados = movements.filter((item) => {
    if (!searchQuery) return true;
    const termo = searchQuery.toLowerCase();
    return (
      (item.productName ?? '').toLowerCase().includes(termo) ||
      (item.note ?? '').toLowerCase().includes(termo)
    );
  });

  return (
    <>
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={brand.gold} />}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.header}>
          <Text style={styles.section}>Stock</Text>
          <View style={styles.viewToggle}>
            <Pressable
              style={[styles.viewButton, viewMode === 'inventory' && styles.viewButtonActive]}
              onPress={() => mudarVista('inventory')}
            >
              <Text style={[styles.viewButtonText, viewMode === 'inventory' && styles.viewButtonTextActive]}>
                Inventário
              </Text>
            </Pressable>
            {podeVerMovimentos ? (
              <Pressable
                style={[styles.viewButton, viewMode === 'movements' && styles.viewButtonActive]}
                onPress={() => mudarVista('movements')}
              >
                <Text style={[styles.viewButtonText, viewMode === 'movements' && styles.viewButtonTextActive]}>
                  Movimentos
                </Text>
              </Pressable>
            ) : null}
          </View>
        </View>

        {locations.length > 0 ? (
          <View style={styles.locationSection}>
            <Text style={styles.sectionMeta}>Localização</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
              {locations.map((item) => (
                <Pressable
                  key={item.id}
                  style={[styles.chip, locationId === item.id && styles.chipActive]}
                  onPress={() => {
                    if (item.id === locationId) return;
                    setLocationId(item.id);
                    setPaginating(true);
                  }}
                >
                  <Text style={[styles.chipText, locationId === item.id && styles.chipTextActive]}>
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
          placeholder={
            viewMode === 'inventory'
              ? 'Pesquisar produto por nome ou código'
              : 'Filtrar movimentos por produto ou nota'
          }
          autoCapitalize="none"
          autoCorrect={false}
          style={styles.searchInput}
        />

        {viewMode === 'inventory' ? (
          <>
            <View style={styles.listHeader}>
              <Text style={styles.sectionMeta}>
                {data ? `${data.meta.total} produto(s)` : 'Produtos'}
              </Text>
              <Text style={styles.sectionMeta}>{PER_PAGE} por página</Text>
            </View>

            {loading && !data ? <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} /> : null}
            {error ? <Text style={styles.error}>{error}</Text> : null}

            {!loading && items.length === 0 ? (
              <Text style={styles.empty}>Nenhum produto encontrado.</Text>
            ) : null}

            {items.map((produto) => (
              <Pressable key={produto.id} style={styles.card} onPress={() => mostrarAcoes(produto)}>
                <View style={styles.cardHeader}>
                  <Text style={styles.title} numberOfLines={2}>
                    {produto.nome}
                  </Text>
                  <Text style={styles.unitBadge}>{produto.unidadeVenda}</Text>
                </View>
                <Text style={styles.meta}>
                  {produto.codigoBarras || 'Sem código'} · {produto.categoria || 'Sem categoria'}
                </Text>
                <View style={styles.row}>
                  <Text style={styles.stockLabel}>
                    Local: {formatarStock(stockLocal[produto.id] ?? 0, produto.unidadeVenda)}
                  </Text>
                  <Text style={styles.stockGlobal}>
                    Global: {formatarStock(produto.stock, produto.unidadeVenda)}
                  </Text>
                </View>
                <Text style={styles.tapHint}>Toque para operar stock</Text>
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
          </>
        ) : (
          <>
            <View style={styles.listHeader}>
              <Text style={styles.sectionMeta}>{movimentosFiltrados.length} movimento(s)</Text>
            </View>

            {loading && movements.length === 0 ? (
              <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} />
            ) : null}
            {error ? <Text style={styles.error}>{error}</Text> : null}

            {!loading && movimentosFiltrados.length === 0 ? (
              <Text style={styles.empty}>Nenhum movimento encontrado.</Text>
            ) : null}

            {movimentosFiltrados.map((movimento) => (
              <View key={movimento.id} style={styles.card}>
                <View style={styles.cardHeader}>
                  <Text style={styles.title} numberOfLines={2}>
                    {movimento.productName || 'Produto'}
                  </Text>
                  <Text style={styles.typeBadge}>{traduzirTipoMovimento(movimento.type)}</Text>
                </View>
                <Text style={styles.meta}>Quantidade: {movimento.quantity}</Text>
                <Text style={styles.meta}>
                  {movimento.fromLocationId
                    ? `De: ${locationMap[movimento.fromLocationId] ?? '—'}`
                    : 'Entrada externa'}
                  {movimento.toLocationId
                    ? ` · Para: ${locationMap[movimento.toLocationId] ?? '—'}`
                    : ''}
                </Text>
                {movimento.note ? <Text style={styles.meta}>{movimento.note}</Text> : null}
                <Text style={styles.dateText}>{formatarData(movimento.createdAt)}</Text>
              </View>
            ))}
          </>
        )}
      </ScrollView>

      <StockActionModal
        visible={modalAberto}
        mode={acaoModal}
        product={produtoSelecionado}
        defaultLocationId={locationId}
        onClose={fecharModal}
        onSaved={() => {
          load();
        }}
      />
      <FullScreenLoader visible={paginating} />
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  header: { paddingHorizontal: 16, paddingTop: 16, paddingBottom: 8, gap: 10 },
  section: { fontWeight: '700', color: brand.dark, fontSize: 16 },
  viewToggle: { flexDirection: 'row', gap: 8 },
  viewButton: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 8,
    backgroundColor: brand.white,
  },
  viewButtonActive: { backgroundColor: brand.dark, borderColor: brand.dark },
  viewButtonText: { color: brand.muted, fontWeight: '700', fontSize: 12 },
  viewButtonTextActive: { color: brand.white },
  locationSection: { paddingHorizontal: 16, paddingBottom: 8, gap: 6 },
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
  listHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingBottom: 8,
  },
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
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 8,
  },
  title: { flex: 1, fontWeight: '700', color: brand.dark },
  unitBadge: {
    fontSize: 11,
    fontWeight: '700',
    color: brand.gold,
    backgroundColor: brand.dark,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
  },
  typeBadge: {
    fontSize: 11,
    fontWeight: '700',
    color: brand.white,
    backgroundColor: brand.dark,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
  },
  meta: { color: brand.muted, fontSize: 12 },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 2 },
  stockLabel: { color: brand.dark, fontSize: 12, fontWeight: '700' },
  stockGlobal: { color: brand.muted, fontSize: 12, fontWeight: '600' },
  tapHint: { color: brand.muted, fontSize: 11, marginTop: 4 },
  dateText: { color: brand.muted, fontSize: 11, marginTop: 2 },
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
