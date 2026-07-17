import type { IvaTipo } from '../api/productsApi';

export type CartProduct = {
  precoVenda?: number;
  precoVendaComIva?: number;
  ivaTipo?: IvaTipo | string;
  ivaValor?: number;
  ivaPercentual?: number;
  valorIvaUnitario?: number;
};

function normalizeTaxType(value?: string | null): 'isento' | 'monetario' | 'percentual' {
  const text = String(value || '').toLowerCase();
  if (text === 'monetario' || text === 'monetário') return 'monetario';
  if (text === 'isento') return 'isento';
  return 'percentual';
}

export function extractSalePrices(priceTag: number, taxType: string, taxValue: number) {
  const salePriceWithTax = Math.max(0, Number(priceTag || 0));
  const normalizedType = normalizeTaxType(taxType);

  if (normalizedType === 'isento' || salePriceWithTax <= 0) {
    return {
      priceWithoutTax: salePriceWithTax,
      unitTaxValue: 0,
      salePriceWithTax,
      taxPercent: 0,
    };
  }

  if (normalizedType === 'monetario') {
    const unitTaxValue = Math.max(0, Number(taxValue || 0));
    const priceWithoutTax = Math.max(0, Number((salePriceWithTax - unitTaxValue).toFixed(2)));
    const taxPercent =
      priceWithoutTax > 0 ? Number(((unitTaxValue / priceWithoutTax) * 100).toFixed(2)) : 0;
    return { priceWithoutTax, unitTaxValue, salePriceWithTax, taxPercent };
  }

  const rate = Math.max(0, Math.min(100, Number(taxValue || 0)));
  const priceWithoutTax =
    rate > 0 ? Number((salePriceWithTax / (1 + rate / 100)).toFixed(2)) : salePriceWithTax;
  const unitTaxValue = Number((salePriceWithTax - priceWithoutTax).toFixed(2));
  return { priceWithoutTax, unitTaxValue, salePriceWithTax, taxPercent: rate };
}

export function resolveTaxPercentDisplay(item: {
  ivaTipo?: string;
  ivaPercentual?: number;
  valorIvaUnitario?: number;
  precoSemIva?: number;
  precoVenda?: number;
}) {
  const taxType = normalizeTaxType(item.ivaTipo);
  if (taxType === 'isento') return 0;
  if (taxType === 'percentual') return Number(item.ivaPercentual || 0);
  const net = Number(item.precoSemIva || 0);
  const tax = Number(item.valorIvaUnitario || 0);
  if (net > 0 && tax > 0) return Number(((tax / net) * 100).toFixed(2));
  return 0;
}

export function mapProductForSale(product: CartProduct & { precoVenda: number; ivaTipo: IvaTipo; ivaValor?: number; ivaPercentual?: number }) {
  const taxType = normalizeTaxType(product.ivaTipo);
  const taxInput = taxType === 'percentual' ? product.ivaPercentual ?? product.ivaValor ?? 0 : product.ivaValor ?? 0;
  const prices = extractSalePrices(product.precoVenda, taxType, Number(taxInput || 0));
  return {
    ...product,
    precoVenda: prices.priceWithoutTax,
    precoVendaComIva: prices.salePriceWithTax,
    valorIvaUnitario: prices.unitTaxValue,
    ivaPercentual: prices.taxPercent,
  };
}
