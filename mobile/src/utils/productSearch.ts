import type { Product } from '../api/productsApi';

export function normalizeBarcode(value?: string | null) {
  return String(value || '').replace(/\s+/g, '').trim();
}

export function looksLikeBarcode(value: string) {
  return /^\d{8,14}$/.test(normalizeBarcode(value));
}

export function searchProductsInCatalog(products: Product[], term: string, limit = 40) {
  const query = String(term || '').trim().toLowerCase();
  if (!query) return products.slice(0, limit);

  const scored = products
    .map((product) => {
      const nome = String(product.nome || '').toLowerCase();
      const codigo = String(product.codigoBarras || '').toLowerCase();
      const categoria = String(product.categoria || '').toLowerCase();

      let score = 0;
      if (codigo === query) score += 100;
      if (nome === query) score += 90;
      if (codigo.startsWith(query)) score += 70;
      if (nome.startsWith(query)) score += 60;
      if (codigo.includes(query)) score += 40;
      if (nome.includes(query)) score += 30;
      if (categoria.includes(query)) score += 10;

      return { product, score };
    })
    .filter((item) => item.score > 0)
    .sort((a, b) => b.score - a.score || a.product.nome.localeCompare(b.product.nome));

  return scored.slice(0, limit).map((item) => item.product);
}

export function resolveProductByBarcode(products: Product[], barcode: string) {
  const normalized = normalizeBarcode(barcode);
  if (!normalized) return null;
  return products.find((product) => normalizeBarcode(product.codigoBarras) === normalized) ?? null;
}
