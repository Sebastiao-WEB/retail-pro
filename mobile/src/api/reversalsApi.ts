import { httpRequest } from './httpClient';

export type ReversalRequest = {
  id: string;
  saleId: string;
  requestedBy: string;
  approvedBy: string | null;
  status: string;
  reason: string | null;
  requestedAt: string | null;
  decidedAt: string | null;
};

export async function fetchReversalRequests() {
  const response = await httpRequest<{ data: ReversalRequest[] }>('/sale-reversal-requests');
  return response.data;
}

export async function updateReversalRequest(id: string, status: 'APPROVED' | 'REJECTED') {
  return httpRequest(`/sale-reversal-requests/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
}
