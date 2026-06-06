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
import { ApiError } from '@/src/api/httpClient';
import {
  fetchProducts,
  type Product,
  type ProductsListResponse,
} from '@/src/api/productsApi';
import FullScreenLoader from '@/src/components/FullScreenLoader';
import ProductEditModal from '@/src/components/ProductEditModal';
import { brand, formatMt } from '@/src/theme/brand';

const PER_PAGE = 10;
const SEARCH_DEBOUNCE_MS = 3000;

function traduzirUnidade(unidade: Product['unidadeVenda']) {
  return unidade === 'KG' ? 'Por peso (kg)' : 'Por unidade';
}

function formatarStock(stock: number, unidade: Product['unidadeVenda']) {
  const valor = Number(stock || 0);
  return unidade === 'KG' ? `${valor} kg` : String(valor);
}

export default function ProductsScreen() {
  const [data, setData] = useState<ProductsListResponse | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [paginating, setPaginating] = useState(false);
  const [error, setError] = useState('');
  const [produtoSelecionado, setProdutoSelecionado] = useState<Product | null>(null);
  const [modalAberto, setModalAberto] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      const trimmed = search.trim();
      if (trimmed === searchQuery) return;
      setSearchQuery(trimmed);
      setPage(1);
      setPaginating(true);
    }, SEARCH_DEBOUNCE_MS);

    return () => clearTimeout(timer);
  }, [search, searchQuery]);

  const load = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      const response = await fetchProducts(page, PER_PAGE, searchQuery);
      setData(response);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar produtos.');
    } finally {
      setLoading(false);
      setPaginating(false);
    }
  }, [page, searchQuery]);

  useEffect(() => {
    load();
  }, [load]);

  function abrirEdicao(produto: Product) {
    setProdutoSelecionado(produto);
    setModalAberto(true);
  }

  function fecharEdicao() {
    setModalAberto(false);
    setProdutoSelecionado(null);
  }

  function irParaPagina(pagina: number) {
    if (loading || paginating || pagina === page) return;
    setPaginating(true);
    setPage(pagina);
  }

  const items = data?.items ?? [];
  const totalPaginas = data?.meta.last_page ?? 1;

  return (
    <>
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={brand.gold} />}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.header}>
          <Text style={styles.section}>
            {data ? `${data.meta.total} produto(s)` : 'Produtos'}
          </Text>
          <Text style={styles.sectionMeta}>{PER_PAGE} por página</Text>
        </View>

        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder="Pesquisar por nome, código ou categoria"
          autoCapitalize="none"
          autoCorrect={false}
          style={styles.searchInput}
        />

        {loading && !data ? <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} /> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}

        {!loading && items.length === 0 ? (
          <Text style={styles.empty}>Nenhum produto encontrado.</Text>
        ) : null}

        {items.map((produto) => (
          <Pressable key={produto.id} style={styles.card} onPress={() => abrirEdicao(produto)}>
            <View style={styles.cardHeader}>
              <Text style={styles.title} numberOfLines={2}>
                {produto.nome}
              </Text>
              <Text style={styles.unitBadge}>{produto.unidadeVenda}</Text>
            </View>
            <Text style={styles.meta}>
              {produto.codigoBarras || 'Sem código'} · {produto.categoria || 'Sem categoria'}
            </Text>
            <Text style={styles.meta}>{traduzirUnidade(produto.unidadeVenda)}</Text>
            <View style={styles.row}>
              <Text style={styles.stock}>Stock: {formatarStock(produto.stock, produto.unidadeVenda)}</Text>
              <Text style={styles.price}>{formatMt(produto.precoVenda)}</Text>
            </View>
            <Text style={styles.tapHint}>Toque para editar</Text>
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

      <ProductEditModal
        visible={modalAberto}
        product={produtoSelecionado}
        onClose={fecharEdicao}
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
  header: { paddingHorizontal: 16, paddingTop: 16, paddingBottom: 8, gap: 2 },
  section: { fontWeight: '700', color: brand.dark },
  sectionMeta: { fontSize: 12, color: brand.muted },
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
  meta: { color: brand.muted, fontSize: 12 },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 2 },
  stock: { color: brand.muted, fontSize: 12, fontWeight: '600' },
  price: { fontWeight: '700', color: brand.dark },
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
