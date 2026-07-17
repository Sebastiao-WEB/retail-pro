import { create } from 'zustand';
import type { Product } from '../api/productsApi';
import { fetchAllProducts, fetchProductByBarcode } from '../api/productsApi';
import { saveCatalogOffline, loadCatalogOffline, hasCatalogOffline } from '../services/offline/catalogCache';
import { canTryOfflineOperation } from '../services/offline/networkError';
import { mapProductForSale } from '../utils/taxItem';
import { normalizeSaleQuantity, normalizeSaleUnit, productControlsStock } from '../utils/productQuantity';
import { resolveProductByBarcode, searchProductsInCatalog } from '../utils/productSearch';

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
    try {
      const products = (await fetchAllProducts(filters)).map(normalizeProduct);
      set({
        products,
        searchResults: products.slice(0, 40),
        loaded: true,
        usingLocalStock: false,
        barcodeIndex: rebuildBarcodeIndex(products),
        catalogFilters: filters,
      });
      await saveCatalogOffline(filters, products);
    } catch (error) {
      const cached = await loadCatalogOffline(filters);
      if (!cached?.products?.length) {
        if (canTryOfflineOperation(error)) {
          throw new Error('Sem catálogo offline. Ligue-se à internet para carregar produtos.');
        }
        throw error;
      }
      const products = cached.products.map(normalizeProduct);
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
