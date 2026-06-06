export type SaleItem = {
  produtoId?: string | null;
  nome: string;
  quantidade: number;
  precoVenda: number;
  precoSemIva?: number;
  ivaPercentual?: number;
  valorIvaUnitario?: number;
  subtotal: number;
};

export type SaleDetail = {
  id: string;
  referencia: string;
  cliente: string;
  caixa: string;
  operador?: string | null;
  metodoPagamento: string;
  total: number;
  estado: string;
  data: string | null;
  createdAt?: string | null;
  subtotal: number;
  descontoAplicado: number;
  valorPago?: number;
  troco?: number;
  itens: SaleItem[];
};

export type RecentSalesResponse = {
  items: SaleDetail[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export function calcularIvaTotalItem(item: SaleItem) {
  const quantidade = Number(item.quantidade || 0);
  const valorIvaUnitario = Number(item.valorIvaUnitario || 0);
  return Number((valorIvaUnitario * quantidade).toFixed(2));
}
