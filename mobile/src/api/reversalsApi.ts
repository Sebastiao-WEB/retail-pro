import { httpRequest } from './httpClient';
import type { SaleDetail } from './salesApi';

export type ReversalRequest = {
  id: string;
  saleId: string;
  sale: SaleDetail | null;
  requestedBy: string;
  approvedBy: string | null;
  status: string;
  reason: string | null;
  requestedAt: string | null;
  decidedAt: string | null;
};

export type ReversalRequestsResponse = {
  items: ReversalRequest[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function fetchReversalRequests(page = 1, perPage = 10) {
  const response = await httpRequest<{ data: ReversalRequestsResponse }>(
    `/sale-reversal-requests?page=${page}&per_page=${perPage}`,
  );
  return response.data;
}

export async function updateReversalRequest(id: string, status: 'APPROVED' | 'REJECTED') {
  return httpRequest(`/sale-reversal-requests/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
}
