import { httpRequest } from './httpClient';

export type StockLocationOption = {
  id: string;
  registerId: string | null;
  registerName?: string | null;
  code: string;
  name: string;
  type: string;
  isSaleable: boolean;
  isActive: boolean;
};

export type StockAvailabilityEntry = {
  quantity: number;
  version: string | null;
};

export type StockMovement = {
  id: string;
  productId: string;
  productName: string | null;
  fromLocationId: string | null;
  toLocationId: string | null;
  type: 'IN' | 'OUT' | 'TRANSFER' | 'ADJUSTMENT' | 'RETURN' | string;
  quantity: number;
  unitCost: number | null;
  referenceType: string | null;
  referenceId: string | null;
  note: string | null;
  performedBy: string | null;
  createdAt: string | null;
};

export type StockTransfer = {
  id: string;
  fromLocationId: string;
  toLocationId: string;
  requestedBy: string;
  status: string;
  note: string | null;
  requestedAt: string | null;
  completedAt: string | null;
  items: Array<{
    productId: string;
    productName: string;
    quantityRequested: number;
    quantitySent: number | null;
    quantityReceived: number | null;
  }>;
};

export type StockReloadPayload = {
  product_id: string;
  quantity: number;
  unit_cost: number;
  supplier?: string;
  note?: string;
  to_location_id: string;
};

export type StockAdjustPayload = {
  product_id: string;
  location_id: string;
  delta: number;
  note?: string;
  unit_cost?: number;
};

export type StockTransferPayload = {
  from_location_id: string;
  to_location_id: string;
  product_id: string;
  quantity: number;
  note?: string;
};

export async function fetchStockLocations() {
  const response = await httpRequest<{ data: StockLocationOption[] }>('/stock-locations');
  return response.data.filter((item) => item.isActive);
}

export async function fetchStockBalance(locationId: string, productId: string) {
  const params = new URLSearchParams({
    location_id: locationId,
    product_id: productId,
  });
  const response = await httpRequest<{ data: { quantity: number } }>(`/stock/balance?${params.toString()}`);
  return response.data.quantity;
}

export async function fetchStockAvailability(locationId: string, productIds: string[]) {
  if (productIds.length === 0) {
    return {} as Record<string, StockAvailabilityEntry>;
  }

  const params = new URLSearchParams({
    location_id: locationId,
    product_ids: productIds.join(','),
  });
  const response = await httpRequest<{ data: Record<string, StockAvailabilityEntry> }>(
    `/stock/availability?${params.toString()}`,
  );
  return response.data;
}

export async function reloadStock(payload: StockReloadPayload) {
  return httpRequest<{
    message: string;
    data: {
      product_id: string;
      location_id: string;
      novo_stock_local: number;
      novo_stock_global: number;
    };
  }>('/stock/reload', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function adjustStock(payload: StockAdjustPayload) {
  return httpRequest<{
    message: string;
    data: { movement_id: string; novo_stock_local: number };
  }>('/stock/adjust', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function fetchStockMovements(filters?: {
  product_id?: string;
  location_id?: string;
  type?: string;
}) {
  const params = new URLSearchParams();
  if (filters?.product_id) params.set('product_id', filters.product_id);
  if (filters?.location_id) params.set('location_id', filters.location_id);
  if (filters?.type) params.set('type', filters.type);

  const query = params.toString();
  const response = await httpRequest<{ data: StockMovement[] }>(
    `/stock/movements${query ? `?${query}` : ''}`,
  );
  return response.data;
}

export async function fetchStockTransfers() {
  const response = await httpRequest<{ data: StockTransfer[] }>('/stock/transfers');
  return response.data;
}

export async function createStockTransfer(payload: StockTransferPayload) {
  return httpRequest<{ message: string; data: { id: string; status: string } }>('/stock/transfers', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}
