import { createSale, fetchSaleById } from '../../api/salesApi';
import { closeCashSession, openCashSession } from '../../api/cashSessionsApi';
import { ApiError } from '../../api/httpClient';
import {
  listPendingQueue,
  removePendingQueueItem,
  remapCashSessionInQueue,
  updatePendingQueueItem,
  type PendingQueueItem,
} from './pendingQueue';
import { isBusinessSaleError, isNetworkError, networkAvailable } from './networkError';
import { useSessionStore } from '../../store/sessionStore';

function sumSaleUnits(payload: Record<string, unknown>) {
  const items = Array.isArray(payload.itens) ? payload.itens : [];
  return items.reduce((acc, item) => acc + Number((item as { quantidade?: number }).quantidade || 0), 0);
}

async function syncPendingSale(payload: Record<string, unknown>) {
  const saleId = String(payload.id || '');
  if (saleId) {
    try {
      const existing = await fetchSaleById(saleId);
      if (sumSaleUnits(existing.data as unknown as Record<string, unknown>) >= sumSaleUnits(payload)) {
        return;
      }
      throw new ApiError('Venda já existe no servidor com quantidade diferente.', 409);
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 404) {
        throw error;
      }
    }
  }
  await createSale(payload as never);
}

async function syncPendingCashOpen(item: PendingQueueItem) {
  const payload = item.payload;
  const response = await openCashSession({
    register_id: String(payload.register_id || ''),
    opening_balance: Number(payload.opening_balance || 0),
    opened_at: String(payload.opened_at || new Date().toISOString()),
  });
  const serverId = response.data?.id;
  const localId = String(payload.localSessionId || '');
  if (serverId && localId && serverId !== localId) {
    await remapCashSessionInQueue(localId, serverId);
    const session = useSessionStore.getState();
    if (session.cashSessionId === localId) {
      useSessionStore.setState({ cashSessionId: serverId });
      await session.persist();
    }
  }
}

async function syncPendingCashClose(item: PendingQueueItem) {
  const payload = item.payload;
  const cashSessionId = String(payload.cashSessionId || payload.cash_session_id || '');
  if (!cashSessionId) {
    throw new ApiError('Sessão de caixa em falta na fila offline.', 422);
  }
  await closeCashSession(cashSessionId, {
    closing_balance: Number(payload.closing_balance ?? payload.closingBalance ?? 0),
    note: String(payload.note || ''),
    closed_at: String(payload.closed_at || payload.closedAt || new Date().toISOString()),
    report_snapshot: (payload.report_snapshot || payload.reportSnapshot) as Record<string, unknown> | undefined,
  });
}

export async function syncOfflineQueue() {
  if (!networkAvailable()) {
    const pending = await listPendingQueue();
    return { sent: 0, pending: pending.length, interrupted: true };
  }

  const queue = await listPendingQueue();
  let sent = 0;

  for (const item of queue) {
    try {
      if (item.tipo === 'cash_open') {
        await syncPendingCashOpen(item);
      } else if (item.tipo === 'sale') {
        await syncPendingSale(item.payload);
      } else if (item.tipo === 'cash_close') {
        await syncPendingCashClose(item);
      } else {
        await removePendingQueueItem(item.id);
        continue;
      }

      await removePendingQueueItem(item.id);
      sent += 1;
    } catch (error) {
      await updatePendingQueueItem(item.id, {
        tentativas: Number(item.tentativas || 0) + 1,
        ultimoErro: error instanceof Error ? error.message : String(error),
      });

      if (isBusinessSaleError(error)) {
        await removePendingQueueItem(item.id);
        continue;
      }

      if (isNetworkError(error)) {
        return { sent, pending: (await listPendingQueue()).length, interrupted: true, error };
      }

      throw error;
    }
  }

  return { sent, pending: (await listPendingQueue()).length, interrupted: false };
}
