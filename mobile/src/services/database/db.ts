import AsyncStorage from '@react-native-async-storage/async-storage';
import { openDatabaseAsync, type SQLiteDatabase } from 'expo-sqlite';
import { debugLog } from '../../utils/debugLog';

const DB_NAME = 'retailpro.db';
const MIGRATION_KEY = 'async_storage_migrated';

const CATALOG_PREFIX = 'retailpro:offline:catalog:';
const QUEUE_KEY = 'retailpro:offline:fila';
const SESSION_KEY = 'retailpro:sessao';

let dbPromise: Promise<SQLiteDatabase> | null = null;

const SCHEMA_SQL = `
  PRAGMA journal_mode = WAL;

  CREATE TABLE IF NOT EXISTS schema_meta (
    key TEXT PRIMARY KEY NOT NULL,
    value TEXT NOT NULL
  );

  CREATE TABLE IF NOT EXISTS offline_catalogs (
    location_key TEXT PRIMARY KEY NOT NULL,
    updated_at TEXT NOT NULL,
    products_json TEXT NOT NULL
  );

  CREATE TABLE IF NOT EXISTS pending_queue (
    id TEXT PRIMARY KEY NOT NULL,
    tipo TEXT NOT NULL,
    criado_em TEXT NOT NULL,
    tentativas INTEGER NOT NULL DEFAULT 0,
    ultimo_erro TEXT NOT NULL DEFAULT '',
    payload_json TEXT NOT NULL
  );

  CREATE TABLE IF NOT EXISTS app_kv (
    key TEXT PRIMARY KEY NOT NULL,
    value TEXT NOT NULL
  );
`;

export async function initDatabase(): Promise<SQLiteDatabase> {
  return getDatabase();
}

export async function getDatabase(): Promise<SQLiteDatabase> {
  if (!dbPromise) {
    dbPromise = openAndMigrate();
  }
  return dbPromise;
}

async function openAndMigrate(): Promise<SQLiteDatabase> {
  const db = await openDatabaseAsync(DB_NAME);
  await db.execAsync(SCHEMA_SQL);
  await migrateFromAsyncStorageIfNeeded(db);
  debugLog('DB', 'SQLite inicializada');
  return db;
}

async function migrateFromAsyncStorageIfNeeded(db: SQLiteDatabase) {
  const migrated = await db.getFirstAsync<{ value: string }>(
    'SELECT value FROM schema_meta WHERE key = ?',
    MIGRATION_KEY,
  );
  if (migrated) return;

  debugLog('DB', 'a migrar dados do AsyncStorage para SQLite…');

  try {
    const keys = await AsyncStorage.getAllKeys();

    for (const key of keys.filter((entry) => entry.startsWith(CATALOG_PREFIX))) {
      const raw = await AsyncStorage.getItem(key);
      if (!raw) continue;

      try {
        const data = JSON.parse(raw) as { updatedAt?: string; products?: unknown[] };
        if (!Array.isArray(data?.products) || !data.products.length) continue;

        const locationKey = key.slice(CATALOG_PREFIX.length) || 'global';
        await db.runAsync(
          `INSERT INTO offline_catalogs (location_key, updated_at, products_json)
           VALUES (?, ?, ?)
           ON CONFLICT(location_key) DO UPDATE SET
             updated_at = excluded.updated_at,
             products_json = excluded.products_json`,
          locationKey,
          data.updatedAt || new Date().toISOString(),
          JSON.stringify(data.products),
        );
      } catch {
        // Ignorar entradas corrompidas.
      }
    }

    const queueRaw = await AsyncStorage.getItem(QUEUE_KEY);
    if (queueRaw) {
      try {
        const list = JSON.parse(queueRaw) as Array<{
          id?: string;
          tipo?: string;
          criadoEm?: string;
          tentativas?: number;
          ultimoErro?: string;
          payload?: Record<string, unknown>;
        }>;
        if (Array.isArray(list)) {
          await db.withTransactionAsync(async () => {
            for (const item of list) {
              if (!item?.id || !item?.tipo || !item?.payload) continue;
              await db.runAsync(
                `INSERT OR IGNORE INTO pending_queue
                 (id, tipo, criado_em, tentativas, ultimo_erro, payload_json)
                 VALUES (?, ?, ?, ?, ?, ?)`,
                item.id,
                item.tipo,
                item.criadoEm || new Date().toISOString(),
                Number(item.tentativas || 0),
                String(item.ultimoErro || ''),
                JSON.stringify(item.payload),
              );
            }
          });
        }
      } catch {
        // Ignorar fila corrompida.
      }
    }

    const sessionRaw = await AsyncStorage.getItem(SESSION_KEY);
    if (sessionRaw) {
      await db.runAsync(
        `INSERT INTO app_kv (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value`,
        SESSION_KEY,
        sessionRaw,
      );
    }

    await db.runAsync(
      'INSERT INTO schema_meta (key, value) VALUES (?, ?)',
      MIGRATION_KEY,
      new Date().toISOString(),
    );
    debugLog('DB', 'migração AsyncStorage concluída');
  } catch (error) {
    debugLog(
      'DB',
      `migração AsyncStorage falhou: ${error instanceof Error ? error.message : String(error)}`,
    );
  }
}
