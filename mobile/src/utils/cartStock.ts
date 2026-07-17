import type { Product } from '../api/productsApi';
import type { CartItem } from '../store/cartStore';
import {
  formatStockDisplay,
  minimumQuantity,
  normalizeSaleQuantity,
  productControlsStock,
} from './productQuantity';

export type StockCheckResult =
  | { ok: true; quantity: number }
  | { ok: false; reason: 'no_stock' | 'insufficient' | 'invalid'; available?: number; maxQuantity?: number };

export function getCartQuantity(items: CartItem[], productId: string) {
  return items.find((item) => item.produtoId === productId)?.quantidade || 0;
}

export function getAvailableStock(product: Product, cartQuantity: number) {
  if (!productControlsStock(product)) return null;
  return Math.max(0, Number(product.stock || 0) - cartQuantity);
}

export function canAddProductToCart(
  product: Product,
  quantityToAdd: number,
  currentCartQuantity: number,
): StockCheckResult {
  const normalized =
    normalizeSaleQuantity(quantityToAdd, product.unidadeVenda) ??
    minimumQuantity(product.unidadeVenda);

  if (!normalized || normalized <= 0) {
    return { ok: false, reason: 'invalid' };
  }

  if (!productControlsStock(product)) {
    return { ok: true, quantity: normalized };
  }

  const stock = Number(product.stock || 0);
  if (stock <= 0) {
    return { ok: false, reason: 'no_stock', available: 0 };
  }

  if (currentCartQuantity + normalized > stock + 0.0001) {
    const available = Math.max(0, stock - currentCartQuantity);
    const maxQuantity = normalizeSaleQuantity(available, product.unidadeVenda) ?? undefined;
    return { ok: false, reason: 'insufficient', available, maxQuantity };
  }

  return { ok: true, quantity: normalized };
}

export function canSetCartQuantity(product: Product, quantity: number): StockCheckResult {
  const normalized = normalizeSaleQuantity(quantity, product.unidadeVenda);
  if (!normalized) {
    return { ok: false, reason: 'invalid' };
  }

  if (!productControlsStock(product)) {
    return { ok: true, quantity: normalized };
  }

  const stock = Number(product.stock || 0);
  if (stock <= 0) {
    return { ok: false, reason: 'no_stock', available: 0, maxQuantity: 0 };
  }

  if (normalized > stock + 0.0001) {
    const maxQuantity = normalizeSaleQuantity(stock, product.unidadeVenda) ?? minimumQuantity(product.unidadeVenda);
    return { ok: false, reason: 'insufficient', available: stock, maxQuantity };
  }

  return { ok: true, quantity: normalized };
}

export function buildStockErrorMessage(product: Product, result: Extract<StockCheckResult, { ok: false }>) {
  if (result.reason === 'no_stock') {
    return `${product.nome} não está disponível.`;
  }

  if (result.reason === 'insufficient') {
    const availableText = formatStockDisplay(result.available ?? 0, product.unidadeVenda);
    return `Stock insuficiente para ${product.nome}. Disponível: ${availableText}.`;
  }

  return 'Quantidade inválida.';
}

export function validateCartStock(items: CartItem[], products: Product[]) {
  for (const item of items) {
    const product = products.find((entry) => entry.id === item.produtoId);
    if (!product || !productControlsStock(product)) continue;

    const check = canSetCartQuantity(product, item.quantidade);
    if (!check.ok) {
      return { ok: false as const, error: buildStockErrorMessage(product, check) };
    }
  }

  return { ok: true as const };
}
