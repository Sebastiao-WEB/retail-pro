import { httpRequest } from './httpClient';

export type SaleUnit = 'UN' | 'KG';

export type IvaTipo = 'ISENTO' | 'PERCENTUAL' | 'MONETARIO';

export type Product = {
  id: string;
  nome: string;
  codigoBarras: string | null;
  categoria: string | null;
  unidadeVenda: SaleUnit;
  precoCompra: number;
  precoVenda: number;
  ivaTipo: IvaTipo;
  ivaValor: number;
  ivaPercentual: number;
  stock: number;
  controlaEstoque: boolean;
  isActive: boolean;
  precoVendaComIva?: number;
  valorIvaUnitario?: number;
};

export type ProductsListResponse = {
  items: Product[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type ProductUpdatePayload = {
  nome: string;
  codigoBarras: string | null;
  categoria: string | null;
  unidadeVenda: SaleUnit;
  precoCompra: number;
  precoVenda: number;
  ivaTipo: IvaTipo;
  ivaValor: number;
  ivaPercentual: number;
  is_active: boolean;
  controlaEstoque: boolean;
};

export async function fetchProducts(page = 1, perPage = 10, search = '') {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });
  if (search.trim()) {
    params.set('search', search.trim());
  }

  const response = await httpRequest<{ data: Product[]; meta: ProductsListResponse['meta'] }>(
    `/products?${params.toString()}`,
  );

  return {
    items: response.data,
    meta: response.meta,
  };
}

export async function fetchAllProducts(filters: { source_location_id?: string; search?: string } = {}) {
  const perPage = 50;
  let page = 1;
  let lastPage = 1;
  const all: Product[] = [];

  do {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
    });
    if (filters.search?.trim()) params.set('search', filters.search.trim());
    if (filters.source_location_id) params.set('source_location_id', filters.source_location_id);

    const response = await httpRequest<{ data: Product[]; meta: ProductsListResponse['meta'] }>(
      `/products?${params.toString()}`,
    );
    all.push(...response.data);
    lastPage = response.meta?.last_page || 1;
    page += 1;
  } while (page <= lastPage);

  return all;
}

export async function fetchProductByBarcode(
  barcode: string,
  filters: { source_location_id?: string } = {},
) {
  const params = new URLSearchParams({ barcode: barcode.trim() });
  if (filters.source_location_id) params.set('source_location_id', filters.source_location_id);
  const response = await httpRequest<{ data: Product[] }>(`/products?${params.toString()}`);
  return response.data?.[0] ?? null;
}

export async function updateProduct(id: string, payload: ProductUpdatePayload) {
  return httpRequest<{ message: string; data: { id: string } }>(`/products/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}
