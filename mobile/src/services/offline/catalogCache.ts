import AsyncStorage from '@react-native-async-storage/async-storage';
import type { Product } from '../../api/productsApi';

const PREFIX = 'retailpro:offline:catalog:';

function catalogKey(filters: { source_location_id?: string; location_id?: string } = {}) {
  const location = filters.source_location_id || filters.location_id || 'global';
  return `${PREFIX}${location}`;
}

export async function saveCatalogOffline(
  filters: { source_location_id?: string; location_id?: string },
  products: Product[],
) {
  if (!Array.isArray(products) || !products.length) return;
  await AsyncStorage.setItem(
    catalogKey(filters),
    JSON.stringify({
      updatedAt: new Date().toISOString(),
      products,
    }),
  );
}

export async function loadCatalogOffline(filters: { source_location_id?: string; location_id?: string } = {}) {
  try {
    const raw = await AsyncStorage.getItem(catalogKey(filters));
    if (!raw) return null;
    const data = JSON.parse(raw) as { products?: Product[] };
    if (!Array.isArray(data?.products)) return null;
    return data;
  } catch {
    return null;
  }
}

export async function hasCatalogOffline(filters: { source_location_id?: string; location_id?: string } = {}) {
  const data = await loadCatalogOffline(filters);
  return Array.isArray(data?.products) && data.products.length > 0;
}

export async function clearCatalogsOffline() {
  const keys = await AsyncStorage.getAllKeys();
  const catalogKeys = keys.filter((key) => key.startsWith(PREFIX));
  if (catalogKeys.length) {
    await AsyncStorage.multiRemove(catalogKeys);
  }
}
