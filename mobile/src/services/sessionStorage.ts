import AsyncStorage from '@react-native-async-storage/async-storage';
import type { SessionState } from '../store/sessionStore';

const SESSION_KEY = 'retailpro:sessao';

export async function loadSessionState(): Promise<Partial<SessionState> | null> {
  try {
    const raw = await AsyncStorage.getItem(SESSION_KEY);
    if (!raw) return null;
    return JSON.parse(raw) as Partial<SessionState>;
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
  await AsyncStorage.setItem(SESSION_KEY, JSON.stringify(payload));
}

export async function clearSessionState() {
  await AsyncStorage.removeItem(SESSION_KEY);
}
