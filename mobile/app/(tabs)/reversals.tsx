import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { fetchReversalRequests, updateReversalRequest, type ReversalRequest } from '@/src/api/reversalsApi';
import { ApiError } from '@/src/api/httpClient';
import { brand } from '@/src/theme/brand';

export default function ReversalsScreen() {
  const [items, setItems] = useState<ReversalRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setError('');
    try {
      const data = await fetchReversalRequests();
      setItems(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar reversões.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleDecision(item: ReversalRequest, status: 'APPROVED' | 'REJECTED') {
    try {
      await updateReversalRequest(item.id, status);
      await load();
    } catch (err) {
      Alert.alert('Erro', err instanceof ApiError ? err.message : 'Não foi possível actualizar.');
    }
  }

  const pending = items.filter((item) => item.status === 'PENDING');

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={brand.gold} />}
    >
      {loading && items.length === 0 ? <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} /> : null}
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Text style={styles.section}>{pending.length} pendente(s)</Text>

      {pending.length === 0 && !loading ? <Text style={styles.empty}>Nenhuma reversão pendente.</Text> : null}

      {pending.map((item) => (
        <View key={item.id} style={styles.card}>
          <Text style={styles.title}>Venda {item.saleId.slice(0, 8)}…</Text>
          <Text style={styles.meta}>Solicitado por {item.requestedBy}</Text>
          {item.reason ? <Text style={styles.reason}>{item.reason}</Text> : null}
          <View style={styles.actions}>
            <Pressable style={[styles.btn, styles.reject]} onPress={() => handleDecision(item, 'REJECTED')}>
              <Text style={styles.btnText}>Rejeitar</Text>
            </Pressable>
            <Pressable style={[styles.btn, styles.approve]} onPress={() => handleDecision(item, 'APPROVED')}>
              <Text style={styles.btnText}>Aprovar</Text>
            </Pressable>
          </View>
        </View>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  section: { padding: 16, fontWeight: '700', color: brand.dark },
  empty: { paddingHorizontal: 16, color: brand.muted },
  error: { color: brand.danger, padding: 16 },
  card: {
    marginHorizontal: 16,
    marginBottom: 12,
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: brand.border,
    gap: 6,
  },
  title: { fontWeight: '700', color: brand.dark },
  meta: { color: brand.muted, fontSize: 12 },
  reason: { color: brand.dark, marginTop: 4 },
  actions: { flexDirection: 'row', gap: 8, marginTop: 12 },
  btn: { flex: 1, borderRadius: 8, paddingVertical: 10, alignItems: 'center' },
  approve: { backgroundColor: brand.success },
  reject: { backgroundColor: brand.danger },
  btnText: { color: brand.white, fontWeight: '700' },
});
