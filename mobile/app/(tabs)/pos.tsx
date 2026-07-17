import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import type { Product } from '@/src/api/productsApi';
import { ApiError } from '@/src/api/httpClient';
import type { SaleDetail } from '@/src/api/salesApi';
import BarcodeScannerModal from '@/src/components/pos/BarcodeScannerModal';
import CheckoutModal from '@/src/components/pos/CheckoutModal';
import CloseShiftModal from '@/src/components/pos/CloseShiftModal';
import OpenShiftModal from '@/src/components/pos/OpenShiftModal';
import RequestReversalModal from '@/src/components/pos/RequestReversalModal';
import WeightQuantityModal, { productNeedsWeightModal } from '@/src/components/pos/WeightQuantityModal';
import SaleDetailModal from '@/src/components/SaleDetailModal';
import { useCartStore } from '@/src/store/cartStore';
import { useOfflineStore } from '@/src/store/offlineStore';
import { useProductStore } from '@/src/store/productStore';
import { useSaleStore } from '@/src/store/saleStore';
import { useSessionStore } from '@/src/store/sessionStore';
import { useShiftSalesStore } from '@/src/store/shiftSalesStore';
import { brand, formatMt } from '@/src/theme/brand';
import {
  formatStockDisplay,
  productControlsStock,
  soldByWeight,
} from '@/src/utils/productQuantity';
import { looksLikeBarcode } from '@/src/utils/productSearch';
import { debugLog, debugTimed } from '@/src/utils/debugLog';

type Section = 'venda' | 'caixa';

function formatarData(valor: string | null | undefined) {
  if (!valor) return '—';
  return new Date(valor).toLocaleString('pt-MZ', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function PosScreen() {
  const sourceLocationId = useSessionStore((state) => state.sourceLocationId);
  const assignedRegister = useSessionStore((state) => state.assignedRegister);
  const registerCode = useSessionStore((state) => state.registerCode);
  const operator = useSessionStore((state) => state.operator);
  const openingBalance = useSessionStore((state) => state.openingBalance);
  const openedAt = useSessionStore((state) => state.openedAt);
  const shiftOpen = useSessionStore((state) => state.shiftOpen);

  const productStore = useProductStore();
  const cart = useCartStore();
  const offline = useOfflineStore();
  const registerSale = useSaleStore((state) => state.registerSale);
  const shiftSales = useShiftSalesStore();

  const [section, setSection] = useState<Section>('venda');
  const [search, setSearch] = useState('');
  const [booting, setBooting] = useState(true);
  const [openShiftVisible, setOpenShiftVisible] = useState(false);
  const [closeShiftVisible, setCloseShiftVisible] = useState(false);
  const [checkoutVisible, setCheckoutVisible] = useState(false);
  const [scannerVisible, setScannerVisible] = useState(false);
  const [weightProduct, setWeightProduct] = useState<Product | null>(null);
  const [reversalSale, setReversalSale] = useState<SaleDetail | null>(null);
  const [detailSale, setDetailSale] = useState<SaleDetail | null>(null);
  const [processing, setProcessing] = useState(false);
  const [bootError, setBootError] = useState('');
  const [bootStep, setBootStep] = useState('A iniciar…');

  const catalogFilters = useMemo(
    () =>
      sourceLocationId
        ? { source_location_id: sourceLocationId }
        : ({} as Record<string, string>),
    [sourceLocationId],
  );

  const metrics = useMemo(
    () => shiftSales.getMetrics(openingBalance),
    [shiftSales.sales, openingBalance, shiftSales.getMetrics],
  );

  const shiftSalesList = shiftSales.getShiftSales();
  const bootingRef = useRef(false);

  const bootstrap = useCallback(async () => {
    if (bootingRef.current) {
      debugLog('POS', 'bootstrap ignorado (já em execução)');
      return;
    }
    bootingRef.current = true;
    debugLog('POS', `bootstrap início filters=${JSON.stringify(catalogFilters)}`);
    setBooting(true);
    setBootError('');
    try {
      setBootStep('A carregar sessão…');
      const sessionState = useSessionStore.getState();
      if (!sessionState.hydrated) {
        await debugTimed('POS', 'session.hydrate', () => sessionState.hydrate());
      }

      setBootStep('A carregar catálogo de produtos…');
      await debugTimed('POS', 'productStore.loadCatalog', () =>
        useProductStore.getState().loadCatalog(catalogFilters),
      );

      setBootStep('A sincronizar turno de caixa…');
      await debugTimed('POS', 'session.syncRemoteShift', () =>
        useSessionStore.getState().syncRemoteShift(),
      );

      if (!useSessionStore.getState().shiftOpen) {
        debugLog('POS', 'turno fechado → modal abertura');
        setOpenShiftVisible(true);
      } else {
        setBootStep('A carregar vendas do turno…');
        await debugTimed('POS', 'shiftSales.load', () => useShiftSalesStore.getState().load());
      }
      debugLog('POS', 'bootstrap concluído com sucesso');
    } catch (error) {
      const message =
        error instanceof ApiError
          ? error.message
          : error instanceof Error
            ? error.message
            : 'Não foi possível preparar o terminal.';
      debugLog('POS', `bootstrap falhou: ${message}`);
      setBootError(message);
    } finally {
      bootingRef.current = false;
      setBooting(false);
      setBootStep('');
    }
  }, [catalogFilters]);

  useEffect(() => {
    debugLog('POS', 'ecrã montado');
    const unsubscribe = useOfflineStore.getState().init();
    void bootstrap();
    return () => {
      debugLog('POS', 'ecrã desmontado');
      unsubscribe();
    };
  }, [bootstrap]);

  useEffect(() => {
    const timer = setTimeout(() => {
      productStore.search(search);
    }, 250);
    return () => clearTimeout(timer);
  }, [search, productStore]);

  async function handleOpenShift(openingBalance: number) {
    setProcessing(true);
    const result = await useSessionStore.getState().openShift(openingBalance);
    setProcessing(false);
    if (!result.ok) {
      Alert.alert('Abertura de caixa', result.error || 'Não foi possível abrir o turno.');
      return;
    }
    setOpenShiftVisible(false);
    await shiftSales.load();
    if (result.offline) {
      Alert.alert('Modo offline', 'Turno aberto localmente. Será sincronizado quando houver rede.');
    }
  }

  async function handleCloseShift(payload: {
    closingBalance: number;
    note: string;
    reportSnapshot: Record<string, unknown>;
  }) {
    setProcessing(true);
    const result = await useSessionStore.getState().closeShift({
      closingBalance: payload.closingBalance,
      note: payload.note,
      reportSnapshot: payload.reportSnapshot,
    });
    setProcessing(false);
    if (!result.ok) {
      Alert.alert('Fecho de caixa', result.error || 'Não foi possível fechar o turno.');
      return;
    }
    setCloseShiftVisible(false);
    shiftSales.load();
    setOpenShiftVisible(true);
    Alert.alert('Turno fechado', 'O caixa foi fechado com sucesso.');
  }

  function tryAddProduct(product: Product) {
    if (!shiftOpen) {
      Alert.alert('Caixa fechado', 'Abra o turno antes de registar vendas.');
      setOpenShiftVisible(true);
      return;
    }
    if (productControlsStock(product) && Number(product.stock || 0) <= 0) {
      Alert.alert('Sem stock', `${product.nome} não está disponível.`);
      return;
    }
    if (productNeedsWeightModal(product)) {
      setWeightProduct(product);
      return;
    }
    cart.addProduct(product, 1);
  }

  async function handleBarcode(code: string) {
    const product = await productStore.resolveBarcode(code);
    if (!product) {
      Alert.alert('Produto não encontrado', `Nenhum produto para o código ${code}.`);
      return;
    }
    tryAddProduct(product);
  }

  async function resolveBarcodeForScanner(code: string) {
    return productStore.resolveBarcode(code);
  }

  function handleScannerConfirm(product: Product) {
    tryAddProduct(product);
  }

  async function handleSearchSubmit() {
    const term = search.trim();
    if (!term) return;
    if (looksLikeBarcode(term)) {
      await handleBarcode(term);
      setSearch('');
      return;
    }
    const results = productStore.search(term);
    if (results.length === 1) {
      tryAddProduct(results[0]);
      setSearch('');
    }
  }

  async function handleCheckout(payload: { cliente: string; valorPago: number; troco: number }) {
    if (!shiftOpen) return;
    setProcessing(true);
    try {
      const itens = cart.items.map((item) => ({
        produtoId: item.produtoId,
        nome: item.nome,
        quantidade: item.quantidade,
        precoVenda: item.precoVenda,
        precoSemIva: item.precoSemIva,
        ivaPercentual: item.ivaPercentual,
        valorIvaUnitario: item.valorIvaUnitario,
        subtotal: item.subtotal,
      }));
      const result = await registerSale({
        cliente: payload.cliente,
        itens,
        subtotal: cart.subtotal,
        descontoAplicado: cart.discountAmount,
        total: cart.total,
        metodoPagamento: cart.paymentMethod,
        valorPago: payload.valorPago,
        troco: payload.troco,
      });
      cart.clear();
      setCheckoutVisible(false);
      await shiftSales.load();
      void offline.syncPending();
      Alert.alert(
        result.mode === 'offline' ? 'Venda guardada offline' : 'Venda registada',
        result.mode === 'offline'
          ? 'A venda ficou na fila e será sincronizada automaticamente.'
          : `Referência ${result.referencia}`,
      );
    } catch (error) {
      Alert.alert(
        'Erro na venda',
        error instanceof ApiError ? error.message : 'Não foi possível registar a venda.',
      );
    } finally {
      setProcessing(false);
    }
  }

  async function handleRequestReversal(reason: string) {
    if (!reversalSale) return;
    setProcessing(true);
    try {
      await shiftSales.requestReversal(reversalSale.id, reason);
      setReversalSale(null);
      Alert.alert('Pedido enviado', 'A reversão foi solicitada e aguarda aprovação.');
    } catch (error) {
      Alert.alert(
        'Erro',
        error instanceof ApiError ? error.message : 'Não foi possível solicitar a reversão.',
      );
    } finally {
      setProcessing(false);
    }
  }

  function renderProduct({ item }: { item: Product }) {
    const price = Number((item as Product & { precoVendaComIva?: number }).precoVendaComIva ?? item.precoVenda);
    const stockLabel = productControlsStock(item)
      ? formatStockDisplay(Number(item.stock || 0), item.unidadeVenda)
      : 'Sob pedido';

    return (
      <Pressable style={styles.productCard} onPress={() => tryAddProduct(item)}>
        <View style={styles.productHeader}>
          <Text style={styles.productName} numberOfLines={2}>
            {item.nome}
          </Text>
          <Text style={styles.productPrice}>{formatMt(price)}</Text>
        </View>
        <Text style={styles.productMeta}>
          {item.codigoBarras || 'Sem código'} · Stock: {stockLabel}
          {soldByWeight(item) ? ' · KG' : ''}
        </Text>
      </Pressable>
    );
  }

  if (booting) {
    return (
      <View style={styles.loader}>
        <ActivityIndicator size="large" color={brand.gold} />
        <Text style={styles.loaderText}>A preparar terminal de vendas…</Text>
        {bootStep ? <Text style={styles.loaderStep}>{bootStep}</Text> : null}
      </View>
    );
  }

  if (bootError) {
    return (
      <View style={styles.loader}>
        <Text style={styles.errorTitle}>Erro ao carregar POS</Text>
        <Text style={styles.errorText}>{bootError}</Text>
        <Text style={styles.errorHint}>
          Confirme que o telemóvel tem internet (Wi‑Fi ou dados) e que o ficheiro mobile/.env aponta para
          o servidor correcto. Reinicie o Expo após alterar o .env.
        </Text>
        <Pressable style={styles.retryBtn} onPress={() => void bootstrap()}>
          <Text style={styles.retryBtnText}>Tentar novamente</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.statusBar}>
        <View style={styles.statusLeft}>
          <View style={[styles.dot, offline.online ? styles.dotOnline : styles.dotOffline]} />
          <Text style={styles.statusText}>
            {offline.online ? 'Online' : 'Offline'}
            {offline.pendingCount > 0 ? ` · ${offline.pendingCount} pendente(s)` : ''}
          </Text>
        </View>
        <Text style={styles.statusText}>
          {assignedRegister || registerCode || 'Caixa'} · {operator || '—'}
        </Text>
      </View>

      <View style={styles.sectionRow}>
        <Pressable
          style={[styles.sectionChip, section === 'venda' && styles.sectionChipActive]}
          onPress={() => setSection('venda')}
        >
          <Text style={[styles.sectionText, section === 'venda' && styles.sectionTextActive]}>Venda</Text>
        </Pressable>
        <Pressable
          style={[styles.sectionChip, section === 'caixa' && styles.sectionChipActive]}
          onPress={() => setSection('caixa')}
        >
          <Text style={[styles.sectionText, section === 'caixa' && styles.sectionTextActive]}>Caixa</Text>
        </Pressable>
      </View>

      {section === 'venda' ? (
        <>
          <View style={styles.searchRow}>
            <TextInput
              value={search}
              onChangeText={setSearch}
              onSubmitEditing={handleSearchSubmit}
              placeholder="Pesquisar produto ou código de barras"
              style={styles.searchInput}
              returnKeyType="search"
            />
            <Pressable style={styles.scanBtn} onPress={() => setScannerVisible(true)}>
              <Text style={styles.scanBtnText}>Scan</Text>
            </Pressable>
          </View>

          {productStore.usingLocalStock ? (
            <Text style={styles.cacheHint}>Catálogo em cache offline</Text>
          ) : null}

          <FlatList
            data={productStore.searchResults}
            keyExtractor={(item) => item.id}
            renderItem={renderProduct}
            contentContainerStyle={styles.listContent}
            ListEmptyComponent={<Text style={styles.empty}>Nenhum produto encontrado.</Text>}
          />

          <View style={styles.cartBar}>
            <View>
              <Text style={styles.cartLabel}>
                {cart.itemCount} item(ns) · {cart.items.length} linha(s)
              </Text>
              <Text style={styles.cartTotal}>{formatMt(cart.total)}</Text>
            </View>
            <View style={styles.cartActions}>
              <Pressable
                style={[styles.cartBtn, styles.cartBtnMuted]}
                onPress={() => cart.clear()}
                disabled={cart.items.length === 0}
              >
                <Text style={styles.cartBtnMutedText}>Limpar</Text>
              </Pressable>
              <Pressable
                style={[styles.cartBtn, cart.items.length === 0 && styles.cartBtnDisabled]}
                onPress={() => setCheckoutVisible(true)}
                disabled={cart.items.length === 0 || !shiftOpen}
              >
                <Text style={styles.cartBtnText}>Finalizar</Text>
              </Pressable>
            </View>
          </View>
        </>
      ) : (
        <View style={styles.cashPanel}>
          {!shiftOpen ? (
            <View style={styles.closedBox}>
              <Text style={styles.closedTitle}>Turno fechado</Text>
              <Text style={styles.closedText}>Abra o caixa para consultar métricas e vendas do turno.</Text>
              <Pressable style={styles.primaryBtn} onPress={() => setOpenShiftVisible(true)}>
                <Text style={styles.primaryBtnText}>Abrir caixa</Text>
              </Pressable>
            </View>
          ) : (
            <>
              <View style={styles.metricsGrid}>
                <View style={styles.metricCard}>
                  <Text style={styles.metricLabel}>Total vendido</Text>
                  <Text style={styles.metricValue}>{formatMt(metrics.totalVendido)}</Text>
                </View>
                <View style={styles.metricCard}>
                  <Text style={styles.metricLabel}>Transacções</Text>
                  <Text style={styles.metricValue}>{metrics.totalTransacoes}</Text>
                </View>
                <View style={styles.metricCard}>
                  <Text style={styles.metricLabel}>Dinheiro esperado</Text>
                  <Text style={styles.metricValue}>{formatMt(metrics.dinheiroEsperado)}</Text>
                </View>
                <View style={styles.metricCard}>
                  <Text style={styles.metricLabel}>Transferências</Text>
                  <Text style={styles.metricValue}>{formatMt(metrics.transferenciasEsperadas)}</Text>
                </View>
              </View>

              <Text style={styles.shiftTitle}>Vendas do turno ({shiftSalesList.length})</Text>
              <FlatList
                data={shiftSalesList}
                keyExtractor={(item) => item.id}
                renderItem={({ item }) => (
                  <View style={styles.saleCard}>
                    <Pressable onPress={() => setDetailSale(item)}>
                      <Text style={styles.saleRef}>{item.referencia}</Text>
                      <Text style={styles.saleMeta}>
                        {formatarData(item.data)} · {item.metodoPagamento} · {item.estado}
                      </Text>
                      <Text style={styles.saleTotal}>{formatMt(item.total)}</Text>
                    </Pressable>
                    <Pressable
                      style={styles.reversalBtn}
                      onPress={() => setReversalSale(item)}
                      disabled={!!shiftSales.canRequestReversal(item)}
                    >
                      <Text style={styles.reversalBtnText}>
                        {shiftSales.canRequestReversal(item) ? 'Reversão indisponível' : 'Solicitar reversão'}
                      </Text>
                    </Pressable>
                  </View>
                )}
                ListEmptyComponent={<Text style={styles.empty}>Sem vendas neste turno.</Text>}
                contentContainerStyle={styles.listContent}
              />

              <Pressable style={styles.closeBtn} onPress={() => setCloseShiftVisible(true)}>
                <Text style={styles.closeBtnText}>Fechar caixa</Text>
              </Pressable>
            </>
          )}
        </View>
      )}

      <OpenShiftModal
        visible={openShiftVisible}
        loading={processing}
        onConfirm={handleOpenShift}
      />

      <CloseShiftModal
        visible={closeShiftVisible}
        loading={processing}
        metrics={metrics}
        operatorName={operator || ''}
        registerName={assignedRegister || registerCode}
        openedAt={openedAt}
        onConfirm={handleCloseShift}
        onClose={() => setCloseShiftVisible(false)}
      />

      <CheckoutModal
        visible={checkoutVisible}
        loading={processing}
        onConfirm={handleCheckout}
        onClose={() => setCheckoutVisible(false)}
      />

      <BarcodeScannerModal
        visible={scannerVisible}
        onClose={() => setScannerVisible(false)}
        resolveBarcode={resolveBarcodeForScanner}
        onConfirmAdd={handleScannerConfirm}
      />

      <WeightQuantityModal
        visible={!!weightProduct}
        product={weightProduct}
        onConfirm={(quantity) => {
          if (weightProduct) cart.addProduct(weightProduct, quantity);
          setWeightProduct(null);
        }}
        onClose={() => setWeightProduct(null)}
      />

      <RequestReversalModal
        visible={!!reversalSale}
        sale={reversalSale}
        blockReason={reversalSale ? shiftSales.canRequestReversal(reversalSale) : ''}
        loading={processing}
        onConfirm={handleRequestReversal}
        onClose={() => setReversalSale(null)}
      />

      <SaleDetailModal visible={!!detailSale} sale={detailSale} onClose={() => setDetailSale(null)} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  loader: { flex: 1, justifyContent: 'center', alignItems: 'center', gap: 12, padding: 24 },
  loaderText: { color: brand.muted },
  loaderStep: { color: brand.dark, fontWeight: '600', fontSize: 13 },
  errorTitle: { fontSize: 18, fontWeight: '700', color: brand.dark, textAlign: 'center' },
  errorText: { color: brand.danger, textAlign: 'center', lineHeight: 20 },
  errorHint: { color: brand.muted, textAlign: 'center', fontSize: 13, lineHeight: 18 },
  retryBtn: {
    marginTop: 8,
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingHorizontal: 20,
    paddingVertical: 12,
  },
  retryBtnText: { fontWeight: '700', color: brand.dark },
  statusBar: {
    backgroundColor: brand.dark,
    paddingHorizontal: 16,
    paddingVertical: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
  },
  statusLeft: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  dot: { width: 8, height: 8, borderRadius: 4 },
  dotOnline: { backgroundColor: brand.success },
  dotOffline: { backgroundColor: brand.danger },
  statusText: { color: '#fff', fontSize: 12, fontWeight: '600' },
  sectionRow: { flexDirection: 'row', gap: 8, padding: 12, paddingBottom: 8 },
  sectionChip: {
    flex: 1,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: brand.border,
    backgroundColor: brand.white,
    paddingVertical: 10,
    alignItems: 'center',
  },
  sectionChipActive: { backgroundColor: brand.dark, borderColor: brand.dark },
  sectionText: { fontWeight: '700', color: brand.muted },
  sectionTextActive: { color: brand.white },
  searchRow: { flexDirection: 'row', gap: 8, paddingHorizontal: 12, paddingBottom: 8 },
  searchInput: {
    flex: 1,
    backgroundColor: brand.white,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 15,
  },
  scanBtn: {
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingHorizontal: 14,
    justifyContent: 'center',
  },
  scanBtnText: { fontWeight: '700', color: brand.dark },
  cacheHint: { paddingHorizontal: 16, color: brand.muted, fontSize: 12, marginBottom: 4 },
  listContent: { paddingHorizontal: 12, paddingBottom: 120 },
  productCard: {
    backgroundColor: brand.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: brand.border,
    padding: 14,
    marginBottom: 10,
  },
  productHeader: { flexDirection: 'row', justifyContent: 'space-between', gap: 8 },
  productName: { flex: 1, fontWeight: '700', color: brand.dark },
  productPrice: { fontWeight: '700', color: brand.dark },
  productMeta: { color: brand.muted, fontSize: 12, marginTop: 4 },
  empty: { color: brand.muted, textAlign: 'center', marginTop: 24 },
  cartBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: brand.white,
    borderTopWidth: 1,
    borderTopColor: brand.border,
    padding: 14,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
  },
  cartLabel: { color: brand.muted, fontSize: 12 },
  cartTotal: { fontSize: 22, fontWeight: '800', color: brand.dark },
  cartActions: { flexDirection: 'row', gap: 8 },
  cartBtn: { backgroundColor: brand.gold, borderRadius: 10, paddingHorizontal: 16, paddingVertical: 12 },
  cartBtnMuted: { backgroundColor: brand.background, borderWidth: 1, borderColor: brand.border },
  cartBtnMutedText: { fontWeight: '700', color: brand.muted },
  cartBtnText: { fontWeight: '700', color: brand.dark },
  cartBtnDisabled: { opacity: 0.45 },
  cashPanel: { flex: 1, paddingHorizontal: 12 },
  closedBox: {
    marginTop: 24,
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 20,
    gap: 10,
    borderWidth: 1,
    borderColor: brand.border,
  },
  closedTitle: { fontSize: 18, fontWeight: '700', color: brand.dark },
  closedText: { color: brand.muted },
  primaryBtn: {
    marginTop: 8,
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  primaryBtnText: { fontWeight: '700', color: brand.dark },
  metricsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 },
  metricCard: {
    width: '48%',
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 12,
    borderWidth: 1,
    borderColor: brand.border,
  },
  metricLabel: { color: brand.muted, fontSize: 12 },
  metricValue: { fontWeight: '800', color: brand.dark, marginTop: 4 },
  shiftTitle: { fontWeight: '700', color: brand.dark, marginBottom: 8 },
  saleCard: {
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: brand.border,
    gap: 8,
  },
  saleRef: { fontWeight: '700', color: brand.dark },
  saleMeta: { color: brand.muted, fontSize: 12 },
  saleTotal: { fontWeight: '700', color: brand.dark, marginTop: 2 },
  reversalBtn: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  reversalBtnText: { fontSize: 12, fontWeight: '700', color: brand.dark },
  closeBtn: {
    backgroundColor: brand.dark,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    marginVertical: 12,
  },
  closeBtnText: { color: brand.white, fontWeight: '700' },
});
