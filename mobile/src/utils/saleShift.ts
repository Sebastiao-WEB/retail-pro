import type { SaleDetail } from '../api/salesApi';
import type { ReversalRequest } from '../api/reversalsApi';
import type { SessionState } from '../store/sessionStore';

const UUID_SALE_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function saleHasValidId(sale?: SaleDetail | null) {
  return UUID_SALE_REGEX.test(String(sale?.id || '').trim());
}

export function saleBelongsToCurrentUser(sale: SaleDetail, session: Pick<SessionState, 'operator' | 'userId'>) {
  if (session.userId && sale.userId) {
    return String(sale.userId) === String(session.userId);
  }
  if (session.operator && sale.operador) {
    return String(sale.operador).trim().toLowerCase() === String(session.operator).trim().toLowerCase();
  }
  return true;
}

export function saleBelongsToCurrentShift(
  sale: SaleDetail,
  session: Pick<SessionState, 'cashSessionId' | 'openedAt' | 'shiftOpen'>,
) {
  if (!session.shiftOpen) return false;

  if (session.cashSessionId && sale.cashSessionId) {
    return sale.cashSessionId === session.cashSessionId;
  }

  const openedAt = session.openedAt ? new Date(session.openedAt).getTime() : NaN;
  if (!Number.isFinite(openedAt)) return false;

  const saleAt = sale.data ? new Date(sale.data).getTime() : NaN;
  return Number.isFinite(saleAt) && saleAt >= openedAt;
}

export function reversalBlockReason(sale: SaleDetail | null, reversals: ReversalRequest[] = []) {
  if (!sale) return 'Venda não encontrada.';
  if (!saleHasValidId(sale)) return 'Venda ainda não sincronizada com o servidor.';
  if (String(sale.estado || '') === 'Revertida') return 'Venda já revertida.';
  const pending = reversals.some(
    (item) => item.saleId === sale.id && String(item.status || '').toUpperCase() === 'PENDING',
  );
  if (pending) return 'Já existe uma reversão pendente para esta venda.';
  return '';
}

export function roundMoney(value: number) {
  return Number(Number(value || 0).toFixed(2));
}
