import { create } from 'zustand';
import type { Product } from '../api/productsApi';
import { fetchAllProducts, fetchProductByBarcode } from '../api/productsApi';
import { saveCatalogOffline, loadCatalogOffline, hasCatalogOffline } from '../services/offline/catalogCache';
import { canTryOfflineOperation, formatRequestError } from '../services/offline/networkError';
import { mapProductForSale } from '../utils/taxItem';
import { normalizeSaleQuantity, normalizeSaleUnit, productControlsStock } from '../utils/productQuantity';
import { resolveProductByBarcode, searchProductsInCatalog } from '../utils/productSearch';
import { debugLog } from '../utils/debugLog';

type ProductStore = {
  products: Product[];
  searchResults: Product[];
  loaded: boolean;
  usingLocalStock: boolean;
  barcodeIndex: Record<string, Product>;
  catalogFilters: Record<string, string>;
  loadCatalog: (filters?: Record<string, string>) => Promise<void>;
  search: (term: string) => Product[];
  resolveBarcode: (barcode: string) => Promise<Product | null>;
  applySale: (items: Array<{ produtoId?: string | null; quantidade: number }>) => void;
  restoreSaleStock: (items: Array<{ produtoId?: string | null; quantidade: number }>) => void;
  canSellOffline: () => Promise<boolean>;
};

function rebuildBarcodeIndex(products: Product[]) {
  const index: Record<string, Product> = {};
  for (const product of products) {
    const code = String(product.codigoBarras || '').replace(/\s+/g, '');
    if (code) index[code] = product;
  }
  return index;
}

function normalizeProduct(product: Product): Product {
  const mapped = mapProductForSale(product);
  return {
    ...product,
    ...mapped,
    controlaEstoque: product.controlaEstoque !== false,
  };
}

export const useProductStore = create<ProductStore>((set, get) => ({
  products: [],
  searchResults: [],
  loaded: false,
  usingLocalStock: false,
  barcodeIndex: {},
  catalogFilters: {},

  loadCatalog: async (filters = {}) => {
    debugLog('Catalog', `loadCatalog início filters=${JSON.stringify(filters)}`);
    try {
      const products = (await fetchAllProducts(filters)).map(normalizeProduct);
      debugLog('Catalog', `loadCatalog remoto: ${products.length} produtos`);
      set({
        products,
        searchResults: products.slice(0, 40),
        loaded: true,
        usingLocalStock: false,
        barcodeIndex: rebuildBarcodeIndex(products),
        catalogFilters: filters,
      });
      try {
        await saveCatalogOffline(filters, products);
      } catch (storageError) {
        debugLog(
          'Catalog',
          `cache local não gravado (vendas online OK): ${storageError instanceof Error ? storageError.message : String(storageError)}`,
        );
      }
    } catch (error) {
      debugLog('Catalog', 'loadCatalog remoto falhou, a tentar cache…');
      let cached = null;
      try {
        cached = await loadCatalogOffline(filters);
      } catch (storageError) {
        debugLog(
          'Catalog',
          `cache local indisponível: ${storageError instanceof Error ? storageError.message : String(storageError)}`,
        );
      }
      if (!cached?.products?.length) {
        if (canTryOfflineOperation(error)) {
          throw new Error(
            `Não foi possível carregar o catálogo. ${formatRequestError(error, 'Verifique a ligação à internet e tente novamente.')}`,
          );
        }
        throw error;
      }
      const products = cached.products.map(normalizeProduct);
      debugLog('Catalog', `loadCatalog cache: ${products.length} produtos`);
      set({
        products,
        searchResults: products.slice(0, 40),
        loaded: true,
        usingLocalStock: true,
        barcodeIndex: rebuildBarcodeIndex(products),
        catalogFilters: filters,
      });
    }
  },

  search: (term) => {
    const results = searchProductsInCatalog(get().products, term);
    set({ searchResults: results });
    return results;
  },

  resolveBarcode: async (barcode) => {
    const local = resolveProductByBarcode(get().products, barcode);
    if (local) return local;

    try {
      const remote = await fetchProductByBarcode(barcode, get().catalogFilters);
      if (!remote) return null;
      const normalized = normalizeProduct(remote);
      const products = [...get().products];
      const index = products.findIndex((item) => item.id === normalized.id);
      if (index >= 0) products[index] = normalized;
      else products.unshift(normalized);
      set({
        products,
        barcodeIndex: rebuildBarcodeIndex(products),
      });
      await saveCatalogOffline(get().catalogFilters, products);
      return normalized;
    } catch {
      return null;
    }
  },

  applySale: (items) => {
    const products = [...get().products];
    for (const item of items) {
      if (!item.produtoId) continue;
      const product = products.find((entry) => entry.id === item.produtoId);
      if (!product || !productControlsStock(product)) continue;
      const quantity = normalizeSaleQuantity(item.quantidade, product.unidadeVenda) ?? 0;
      if (quantity <= 0) continue;
      const nextStock = Math.max(0, Number(product.stock || 0) - quantity);
      product.stock =
        normalizeSaleUnit(product.unidadeVenda) === 'KG'
          ? Math.round(nextStock * 1000) / 1000
          : nextStock;
    }
    set({ products, barcodeIndex: rebuildBarcodeIndex(products) });
    void saveCatalogOffline(get().catalogFilters, products);
  },

  restoreSaleStock: (items) => {
    const products = [...get().products];
    for (const item of items) {
      if (!item.produtoId) continue;
      const product = products.find((entry) => entry.id === item.produtoId);
      if (!product || !productControlsStock(product)) continue;
      product.stock = Number(product.stock || 0) + Number(item.quantidade || 0);
    }
    set({ products, barcodeIndex: rebuildBarcodeIndex(products) });
    void saveCatalogOffline(get().catalogFilters, products);
  },

  canSellOffline: async () => hasCatalogOffline(get().catalogFilters),
}));
