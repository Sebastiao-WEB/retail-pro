import AsyncStorage from '@react-native-async-storage/async-storage';
import { generateUuid } from '../../utils/generateId';

const QUEUE_KEY = 'retailpro:offline:fila';

export type PendingQueueType = 'cash_open' | 'sale' | 'cash_close';

export type PendingQueueItem = {
  id: string;
  tipo: PendingQueueType;
  criadoEm: string;
  tentativas: number;
  ultimoErro: string;
  payload: Record<string, unknown>;
};

async function readQueue(): Promise<PendingQueueItem[]> {
  try {
    const raw = await AsyncStorage.getItem(QUEUE_KEY);
    const list = raw ? JSON.parse(raw) : [];
    return Array.isArray(list) ? list : [];
  } catch {
    return [];
  }
}

async function writeQueue(list: PendingQueueItem[]) {
  await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(list));
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
  const queue = await readQueue();
  return queue.length;
}

export async function addPendingQueueItem(item: Partial<PendingQueueItem> & { tipo: PendingQueueType; payload: Record<string, unknown> }) {
  const queue = await readQueue();
  const record: PendingQueueItem = {
    id: item.id || generateUuid(),
    tipo: item.tipo,
    criadoEm: item.criadoEm || new Date().toISOString(),
    tentativas: Number(item.tentativas || 0),
    ultimoErro: String(item.ultimoErro || ''),
    payload: item.payload,
  };
  queue.push(record);
  await writeQueue(queue);
  return record;
}

export async function removePendingQueueItem(id: string) {
  const queue = await readQueue();
  await writeQueue(queue.filter((item) => item.id !== id));
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
