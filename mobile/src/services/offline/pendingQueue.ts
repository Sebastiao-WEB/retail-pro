import { getDatabase } from '../database/db';
import { generateUuid } from '../../utils/generateId';

export type PendingQueueType = 'cash_open' | 'sale' | 'cash_close';

export type PendingQueueItem = {
  id: string;
  tipo: PendingQueueType;
  criadoEm: string;
  tentativas: number;
  ultimoErro: string;
  payload: Record<string, unknown>;
};

type QueueRow = {
  id: string;
  tipo: string;
  criado_em: string;
  tentativas: number;
  ultimo_erro: string;
  payload_json: string;
};

function rowToItem(row: QueueRow): PendingQueueItem {
  return {
    id: row.id,
    tipo: row.tipo as PendingQueueType,
    criadoEm: row.criado_em,
    tentativas: row.tentativas,
    ultimoErro: row.ultimo_erro,
    payload: JSON.parse(row.payload_json) as Record<string, unknown>,
  };
}

async function readQueue(): Promise<PendingQueueItem[]> {
  try {
    const db = await getDatabase();
    const rows = await db.getAllAsync<QueueRow>(
      'SELECT id, tipo, criado_em, tentativas, ultimo_erro, payload_json FROM pending_queue',
    );
    return rows.map(rowToItem);
  } catch {
    return [];
  }
}

async function writeQueue(list: PendingQueueItem[]) {
  const db = await getDatabase();
  await db.withTransactionAsync(async () => {
    await db.runAsync('DELETE FROM pending_queue');
    for (const item of list) {
      await db.runAsync(
        `INSERT INTO pending_queue (id, tipo, criado_em, tentativas, ultimo_erro, payload_json)
         VALUES (?, ?, ?, ?, ?, ?)`,
        item.id,
        item.tipo,
        item.criadoEm,
        item.tentativas,
        item.ultimoErro,
        JSON.stringify(item.payload),
      );
    }
  });
}

export async function listPendingQueue() {
  const order: Record<PendingQueueType, number> = { cash_open: 0, sale: 1, cash_close: 2 };
  const queue = await readQueue();
  return queue.sort((a, b) => {
    const pa = order[a.tipo] ?? 9;
    const pb = order[b.tipo] ?? 9;
    if (pa !== pb) return pa - pb;
    return String(a.criadoEm || '').localeCompare(String(b.criadoEm || ''));
  });
}

export async function countPendingQueue() {
  const db = await getDatabase();
  const row = await db.getFirstAsync<{ total: number }>('SELECT COUNT(*) AS total FROM pending_queue');
  return Number(row?.total || 0);
}

export async function addPendingQueueItem(item: Partial<PendingQueueItem> & { tipo: PendingQueueType; payload: Record<string, unknown> }) {
  const record: PendingQueueItem = {
    id: item.id || generateUuid(),
    tipo: item.tipo,
    criadoEm: item.criadoEm || new Date().toISOString(),
    tentativas: Number(item.tentativas || 0),
    ultimoErro: String(item.ultimoErro || ''),
    payload: item.payload,
  };

  const db = await getDatabase();
  await db.runAsync(
    `INSERT INTO pending_queue (id, tipo, criado_em, tentativas, ultimo_erro, payload_json)
     VALUES (?, ?, ?, ?, ?, ?)`,
    record.id,
    record.tipo,
    record.criadoEm,
    record.tentativas,
    record.ultimoErro,
    JSON.stringify(record.payload),
  );
  return record;
}

export async function removePendingQueueItem(id: string) {
  const db = await getDatabase();
  await db.runAsync('DELETE FROM pending_queue WHERE id = ?', id);
}

export async function updatePendingQueueItem(id: string, patch: Partial<PendingQueueItem>) {
  const queue = await readQueue();
  const index = queue.findIndex((item) => item.id === id);
  if (index === -1) return null;

  queue[index] = { ...queue[index], ...patch };
  await writeQueue(queue);
  return queue[index];
}

export async function remapCashSessionInQueue(oldId: string, newId: string) {
  if (!oldId || !newId || oldId === newId) return;
  const queue = await readQueue();
  const updated = queue.map((item) => {
    const payload = { ...item.payload };
    if (payload.cash_session_id === oldId) payload.cash_session_id = newId;
    if (payload.cashSessionId === oldId) payload.cashSessionId = newId;
    if (item.tipo === 'cash_close' && payload.cashSessionId === oldId) payload.cashSessionId = newId;
    if (item.tipo === 'cash_open' && payload.localSessionId === oldId) payload.localSessionId = newId;
    return { ...item, payload };
  });
  await writeQueue(updated);
}

export async function enqueuePendingSale(payload: Record<string, unknown>) {
  return addPendingQueueItem({ tipo: 'sale', payload });
}

export async function enqueuePendingCashOpen(payload: Record<string, unknown>) {
  return addPendingQueueItem({ tipo: 'cash_open', payload });
}

export async function enqueuePendingCashClose(payload: Record<string, unknown>) {
  return addPendingQueueItem({ tipo: 'cash_close', payload });
}
