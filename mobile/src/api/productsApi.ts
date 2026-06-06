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
  isActive: boolean;
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

export async function updateProduct(id: string, payload: ProductUpdatePayload) {
  return httpRequest<{ message: string; data: { id: string } }>(`/products/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}
