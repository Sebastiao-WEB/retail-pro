import type { SessionState } from '../store/sessionStore';
import { getDatabase } from './database/db';

const SESSION_KEY = 'retailpro:sessao';

export async function loadSessionState(): Promise<Partial<SessionState> | null> {
  try {
    const db = await getDatabase();
    const row = await db.getFirstAsync<{ value: string }>(
      'SELECT value FROM app_kv WHERE key = ?',
      SESSION_KEY,
    );
    if (!row?.value) return null;
    return JSON.parse(row.value) as Partial<SessionState>;
  } catch {
    return null;
  }
}

export async function saveSessionState(state: SessionState) {
  const payload = {
    operator: state.operator,
    userId: state.userId,
    role: state.role,
    assignedRegister: state.assignedRegister,
    registerId: state.registerId,
    registerCode: state.registerCode,
    sourceLocationId: state.sourceLocationId,
    sourceLocationCode: state.sourceLocationCode,
    sourceLocationName: state.sourceLocationName,
    cashSessionId: state.cashSessionId,
    shiftOpen: state.shiftOpen,
    openingBalance: state.openingBalance,
    openedAt: state.openedAt,
  };

  const db = await getDatabase();
  await db.runAsync(
    `INSERT INTO app_kv (key, value) VALUES (?, ?)
     ON CONFLICT(key) DO UPDATE SET value = excluded.value`,
    SESSION_KEY,
    JSON.stringify(payload),
  );
}

export async function clearSessionState() {
  const db = await getDatabase();
  await db.runAsync('DELETE FROM app_kv WHERE key = ?', SESSION_KEY);
}
