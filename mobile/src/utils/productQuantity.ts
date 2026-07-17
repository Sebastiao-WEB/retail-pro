export const UNIDADE_VENDA_KG = 'KG' as const;
export const UNIDADE_VENDA_UN = 'UN' as const;

export type SaleUnit = typeof UNIDADE_VENDA_KG | typeof UNIDADE_VENDA_UN;

export function normalizeSaleUnit(value?: string | null): SaleUnit {
  return String(value || '').toUpperCase() === UNIDADE_VENDA_KG ? UNIDADE_VENDA_KG : UNIDADE_VENDA_UN;
}

export function soldByWeight(product?: { unidadeVenda?: string | null }) {
  return normalizeSaleUnit(product?.unidadeVenda) === UNIDADE_VENDA_KG;
}

export function quantityStep(unit: SaleUnit | string) {
  return normalizeSaleUnit(unit) === UNIDADE_VENDA_KG ? 0.001 : 1;
}

export function minimumQuantity(unit: SaleUnit | string) {
  return normalizeSaleUnit(unit) === UNIDADE_VENDA_KG ? 0.001 : 1;
}

export function normalizeSaleQuantity(quantity: number, unit: SaleUnit | string): number | null {
  const value = Number(quantity);
  if (!Number.isFinite(value)) return null;

  if (normalizeSaleUnit(unit) === UNIDADE_VENDA_KG) {
    const kg = Math.round(value * 1000) / 1000;
    return kg > 0 ? kg : null;
  }

  const units = Math.floor(value);
  return units >= 1 ? units : null;
}

export function parseQuantityText(text: string, unit: SaleUnit | string): number | null {
  const cleaned = String(text ?? '')
    .trim()
    .replace(/\s/g, '')
    .replace(',', '.');
  if (cleaned === '') return null;
  const value = Number(cleaned);
  if (!Number.isFinite(value)) return null;
  return normalizeSaleQuantity(value, unit);
}

export function productControlsStock(product?: { controlaEstoque?: boolean | null }) {
  if (!product) return true;
  return product.controlaEstoque !== false;
}

export function formatStockDisplay(stock: number, unit: SaleUnit | string) {
  if (normalizeSaleUnit(unit) === UNIDADE_VENDA_KG) {
    const kg = Number(stock);
    if (!Number.isFinite(kg) || kg <= 0) return '0 g';
    if (kg < 1) return `${Math.round(kg * 1000)} g`;
    return `${kg.toLocaleString('pt-MZ', { maximumFractionDigits: 3 })} kg`;
  }
  return new Intl.NumberFormat('pt-MZ', { maximumFractionDigits: 0 }).format(Math.floor(Number(stock) || 0));
}
