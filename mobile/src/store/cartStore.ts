import { create } from 'zustand';
import type { Product, IvaTipo } from '../api/productsApi';
import {
  minimumQuantity,
  normalizeSaleQuantity,
  normalizeSaleUnit,
  quantityStep,
} from '../utils/productQuantity';
import { resolveTaxPercentDisplay } from '../utils/taxItem';

export type CartItem = {
  produtoId: string;
  nome: string;
  unidadeVenda: 'UN' | 'KG';
  precoVenda: number;
  precoSemIva: number;
  ivaTipo: IvaTipo | string;
  ivaPercentual: number;
  valorIvaUnitario: number;
  quantidade: number;
  subtotal: number;
  ordemAdicao: number;
};

type CartStore = {
  items: CartItem[];
  sequence: number;
  paymentMethod: 'Dinheiro' | 'Transferência';
  discountType: 'valor' | 'percentual';
  discountValue: number;
  subtotal: number;
  discountAmount: number;
  total: number;
  itemCount: number;
  addProduct: (product: Product, quantity?: number) => void;
  removeProduct: (productId: string) => void;
  increaseQuantity: (productId: string) => void;
  decreaseQuantity: (productId: string) => void;
  setQuantity: (productId: string, quantity: number) => void;
  setPaymentMethod: (method: 'Dinheiro' | 'Transferência') => void;
  setDiscount: (type: 'valor' | 'percentual', value: number) => void;
  clear: () => void;
  recalculate: () => void;
};

function calcSubtotal(quantity: number, unitPrice: number) {
  return Number((quantity * unitPrice).toFixed(2));
}

function recalcTotals(state: Pick<CartStore, 'items' | 'discountType' | 'discountValue'>) {
  const subtotal = state.items.reduce((acc, item) => acc + item.subtotal, 0);
  const discountBase = Number(state.discountValue || 0);
  let discountAmount = 0;
  if (discountBase > 0 && subtotal > 0) {
    discountAmount =
      state.discountType === 'percentual'
        ? subtotal * (Math.max(0, Math.min(100, discountBase)) / 100)
        : Math.max(0, Math.min(subtotal, discountBase));
  }
  const total = subtotal - discountAmount;
  const itemCount = state.items.reduce((acc, item) => acc + item.quantidade, 0);
  return {
    subtotal,
    discountAmount,
    total,
    itemCount,
  };
}

export const useCartStore = create<CartStore>((set, get) => ({
  items: [],
  sequence: 0,
  paymentMethod: 'Dinheiro',
  discountType: 'valor',
  discountValue: 0,
  subtotal: 0,
  discountAmount: 0,
  total: 0,
  itemCount: 0,

  recalculate: () => {
    const totals = recalcTotals(get());
    set(totals);
  },

  addProduct: (product, quantity = 1) => {
    const unit = normalizeSaleUnit(product.unidadeVenda);
    const unitPrice = Number(product.precoVendaComIva ?? product.precoVenda ?? 0);
    const netPrice = Number(product.precoVenda || 0);
    const unitTax = Number(product.valorIvaUnitario ?? Number((unitPrice - netPrice).toFixed(2)));
    const taxPercent = resolveTaxPercentDisplay({
      ivaTipo: product.ivaTipo,
      ivaPercentual: product.ivaPercentual,
      valorIvaUnitario: unitTax,
      precoSemIva: netPrice,
      precoVenda: unitPrice,
    });
    const normalizedQty = normalizeSaleQuantity(quantity, unit) ?? minimumQuantity(unit);
    const existing = get().items.find((item) => item.produtoId === product.id);
    let items = [...get().items];
    let sequence = get().sequence;

    if (existing) {
      const nextQty = normalizeSaleQuantity(existing.quantidade + normalizedQty, unit);
      if (!nextQty) return;
      existing.quantidade = nextQty;
      existing.subtotal = calcSubtotal(nextQty, existing.precoVenda);
      existing.ordemAdicao = ++sequence;
      items = [existing, ...items.filter((item) => item.produtoId !== product.id)];
    } else {
      items.unshift({
        produtoId: product.id,
        nome: product.nome,
        unidadeVenda: unit,
        precoVenda: unitPrice,
        precoSemIva: netPrice,
        ivaTipo: product.ivaTipo,
        ivaPercentual: taxPercent,
        valorIvaUnitario: unitTax,
        quantidade: normalizedQty,
        subtotal: calcSubtotal(normalizedQty, unitPrice),
        ordemAdicao: ++sequence,
      });
    }

    const nextState = { items, sequence, ...recalcTotals({ ...get(), items }) };
    set(nextState);
  },

  removeProduct: (productId) => {
    const items = get().items.filter((item) => item.produtoId !== productId);
    set({ items, ...recalcTotals({ ...get(), items }) });
  },

  increaseQuantity: (productId) => {
    const item = get().items.find((entry) => entry.produtoId === productId);
    if (!item) return;
    const step = quantityStep(item.unidadeVenda);
    const next = normalizeSaleQuantity(item.quantidade + step, item.unidadeVenda);
    if (!next) return;
    item.quantidade = next;
    item.subtotal = calcSubtotal(next, item.precoVenda);
    const items = [...get().items];
    set({ items, ...recalcTotals({ ...get(), items }) });
  },

  decreaseQuantity: (productId) => {
    const item = get().items.find((entry) => entry.produtoId === productId);
    if (!item) return;
    const step = quantityStep(item.unidadeVenda);
    const min = minimumQuantity(item.unidadeVenda);
    if (item.quantidade - step < min) {
      get().removeProduct(productId);
      return;
    }
    const next = normalizeSaleQuantity(item.quantidade - step, item.unidadeVenda);
    if (!next) {
      get().removeProduct(productId);
      return;
    }
    item.quantidade = next;
    item.subtotal = calcSubtotal(next, item.precoVenda);
    const items = [...get().items];
    set({ items, ...recalcTotals({ ...get(), items }) });
  },

  setQuantity: (productId, quantity) => {
    const item = get().items.find((entry) => entry.produtoId === productId);
    if (!item) return;
    const next = normalizeSaleQuantity(quantity, item.unidadeVenda);
    if (!next) {
      get().removeProduct(productId);
      return;
    }
    item.quantidade = next;
    item.subtotal = calcSubtotal(next, item.precoVenda);
    const items = [...get().items];
    set({ items, ...recalcTotals({ ...get(), items }) });
  },

  setPaymentMethod: (method) => set({ paymentMethod: method }),

  setDiscount: (type, value) => {
    const discountType = type === 'percentual' ? 'percentual' : 'valor';
    const discountValue = Number.isFinite(Number(value)) ? Number(value) : 0;
    set({ discountType, discountValue, ...recalcTotals({ ...get(), discountType, discountValue }) });
  },

  clear: () =>
    set({
      items: [],
      sequence: 0,
      paymentMethod: 'Dinheiro',
      discountType: 'valor',
      discountValue: 0,
      subtotal: 0,
      discountAmount: 0,
      total: 0,
      itemCount: 0,
    }),
}));
