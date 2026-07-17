const PREFIX = '[RetailPOS]';

export function debugLog(scope: string, message: string, extra?: unknown) {
  const stamp = new Date().toISOString().slice(11, 23);
  if (extra !== undefined) {
    console.log(`${PREFIX} ${stamp} [${scope}] ${message}`, extra);
    return;
  }
  console.log(`${PREFIX} ${stamp} [${scope}] ${message}`);
}

export async function debugTimed<T>(scope: string, label: string, task: () => Promise<T>): Promise<T> {
  const started = Date.now();
  debugLog(scope, `→ ${label}`);
  try {
    const result = await task();
    debugLog(scope, `✓ ${label} (${Date.now() - started}ms)`);
    return result;
  } catch (error) {
    const detail = error instanceof Error ? error.message : String(error);
    debugLog(scope, `✗ ${label} (${Date.now() - started}ms): ${detail}`);
    throw error;
  }
}
