import { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from 'react-native';
import { httpRequest, ApiError } from '@/src/api/httpClient';
import { brand, formatMt } from '@/src/theme/brand';

type Sale = {
  id: string;
  referencia: string;
  cliente: string;
  caixa: string;
  metodoPagamento: string;
  total: number;
  estado: string;
  data: string | null;
};

export default function SalesScreen() {
  const [sales, setSales] = useState<Sale[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const response = await httpRequest<{ data: Sale[] }>('/sales');
        setSales(response.data ?? []);
      } catch (err) {
        setError(err instanceof ApiError ? err.message : 'Erro ao carregar vendas.');
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  return (
    <ScrollView style={styles.container}>
      {loading ? <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} /> : null}
      {error ? <Text style={styles.error}>{error}</Text> : null}
      {sales.map((sale) => (
        <View key={sale.id} style={styles.card}>
          <Text style={styles.ref}>{sale.referencia}</Text>
          <Text style={styles.meta}>{sale.cliente} · {sale.metodoPagamento}</Text>
          <View style={styles.row}>
            <Text style={styles.state}>{sale.estado}</Text>
            <Text style={styles.total}>{formatMt(Number(sale.total))}</Text>
          </View>
        </View>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  error: { color: brand.danger, padding: 16 },
  card: {
    marginHorizontal: 16,
    marginBottom: 10,
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: brand.border,
  },
  ref: { fontWeight: '700', color: brand.dark },
  meta: { color: brand.muted, fontSize: 12, marginTop: 4 },
  row: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 },
  state: { color: brand.muted, fontWeight: '600' },
  total: { fontWeight: '700', color: brand.dark },
});
