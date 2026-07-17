export function generateUuid(): string {
  if (typeof globalThis.crypto?.randomUUID === 'function') {
    return globalThis.crypto.randomUUID();
  }
  return `local-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
}
