import type { Product } from '../../api/productsApi';
import { getDatabase } from '../database/db';

function catalogLocationKey(filters: { source_location_id?: string; location_id?: string } = {}) {
  return filters.source_location_id || filters.location_id || 'global';
}

export async function saveCatalogOffline(
  filters: { source_location_id?: string; location_id?: string },
  products: Product[],
) {
  if (!Array.isArray(products) || !products.length) return;

  const db = await getDatabase();
  const locationKey = catalogLocationKey(filters);
  const updatedAt = new Date().toISOString();

  await db.runAsync(
    `INSERT INTO offline_catalogs (location_key, updated_at, products_json)
     VALUES (?, ?, ?)
     ON CONFLICT(location_key) DO UPDATE SET
       updated_at = excluded.updated_at,
       products_json = excluded.products_json`,
    locationKey,
    updatedAt,
    JSON.stringify(products),
  );
}

export async function loadCatalogOffline(filters: { source_location_id?: string; location_id?: string } = {}) {
  const primary = await readCatalogEntry(catalogLocationKey(filters));
  if (primary) return primary;

  if (filters.source_location_id || filters.location_id) {
    return readCatalogEntry('global');
  }

  return null;
}

async function readCatalogEntry(locationKey: string) {
  try {
    const db = await getDatabase();
    const row = await db.getFirstAsync<{ updated_at: string; products_json: string }>(
      'SELECT updated_at, products_json FROM offline_catalogs WHERE location_key = ?',
      locationKey,
    );
    if (!row?.products_json) return null;

    const products = JSON.parse(row.products_json) as Product[];
    if (!Array.isArray(products) || !products.length) return null;

    return {
      updatedAt: row.updated_at,
      products,
    };
  } catch {
    return null;
  }
}

export async function hasCatalogOffline(filters: { source_location_id?: string; location_id?: string } = {}) {
  const data = await loadCatalogOffline(filters);
  return Array.isArray(data?.products) && data.products.length > 0;
}

export async function clearCatalogsOffline() {
  const db = await getDatabase();
  await db.runAsync('DELETE FROM offline_catalogs');
}
