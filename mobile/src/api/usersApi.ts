import { httpRequest } from './httpClient';

export type UserRole = 'ADMIN' | 'MANAGER' | 'CASHIER';

export type RegisterOption = {
  id: string;
  code: string;
  name: string;
  isActive: boolean;
};

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

export type AppUser = {
  id: string;
  name: string;
  username: string;
  email: string;
  role: UserRole | string;
  isActive: boolean;
  registerId: string | null;
  registerIds: string[];
  registers: Array<{ id: string; code: string; name: string }>;
  sourceLocationId: string | null;
  caixaAtribuido?: string | null;
};

export type UsersListResponse = {
  items: AppUser[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type UserSavePayload = {
  name: string;
  username: string;
  email: string;
  password?: string;
  role: UserRole;
  isActive: boolean;
  registerIds: string[];
  sourceLocationId: string | null;
};

export async function fetchUsers(page = 1, perPage = 10, search = '') {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });
  if (search.trim()) {
    params.set('search', search.trim());
  }

  const response = await httpRequest<{ data: AppUser[]; meta: UsersListResponse['meta'] }>(
    `/users?${params.toString()}`,
  );

  return {
    items: response.data,
    meta: response.meta,
  };
}

export async function createUser(payload: UserSavePayload) {
  return httpRequest<{ message: string; data: { id: string } }>('/users', {
    method: 'POST',
    body: JSON.stringify({
      ...payload,
      registerId: payload.registerIds[0] ?? null,
    }),
  });
}

export async function updateUser(id: string, payload: UserSavePayload) {
  const body: Record<string, unknown> = {
    name: payload.name,
    username: payload.username,
    email: payload.email,
    role: payload.role,
    isActive: payload.isActive,
    registerIds: payload.registerIds,
    sourceLocationId: payload.sourceLocationId,
  };

  if (payload.password?.trim()) {
    body.password = payload.password.trim();
  }

  return httpRequest<{ message: string; data: { id: string } }>(`/users/${id}`, {
    method: 'PUT',
    body: JSON.stringify(body),
  });
}

export async function fetchRegisters() {
  const response = await httpRequest<{ data: RegisterOption[] }>('/registers');
  return response.data.filter((item) => item.isActive);
}

export async function fetchStockLocations() {
  const response = await httpRequest<{ data: StockLocationOption[] }>('/stock-locations');
  return response.data.filter((item) => item.isActive);
}
