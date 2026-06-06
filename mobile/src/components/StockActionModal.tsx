import { useEffect, useState } from 'react';
import {
  Alert,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { ApiError } from '@/src/api/httpClient';
import type { Product } from '@/src/api/productsApi';
import {
  adjustStock,
  createStockTransfer,
  fetchStockBalance,
  fetchStockLocations,
  reloadStock,
  type StockLocationOption,
} from '@/src/api/stockApi';
import { brand } from '@/src/theme/brand';

export type StockActionMode = 'reload' | 'adjust' | 'transfer';

type Props = {
  visible: boolean;
  mode: StockActionMode;
  product: Product | null;
  defaultLocationId: string | null;
  onClose: () => void;
  onSaved: () => void;
};

function formatarStock(stock: number, unidade: Product['unidadeVenda']) {
  const valor = Number(stock || 0);
  return unidade === 'KG' ? `${valor} kg` : String(valor);
}

const titulos: Record<StockActionMode, string> = {
  reload: 'Recarregar stock',
  adjust: 'Ajustar stock',
  transfer: 'Transferir stock',
};

export default function StockActionModal({
  visible,
  mode,
  product,
  defaultLocationId,
  onClose,
  onSaved,
}: Props) {
  const [locations, setLocations] = useState<StockLocationOption[]>([]);
  const [locationId, setLocationId] = useState<string | null>(null);
  const [fromLocationId, setFromLocationId] = useState<string | null>(null);
  const [toLocationId, setToLocationId] = useState<string | null>(null);
  const [quantity, setQuantity] = useState('1');
  const [delta, setDelta] = useState('');
  const [unitCost, setUnitCost] = useState('0');
  const [supplier, setSupplier] = useState('Reposição Mobile');
  const [note, setNote] = useState('');
  const [saldoLocal, setSaldoLocal] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!visible) return;

    fetchStockLocations()
      .then((items) => {
        setLocations(items);
        const fallback = defaultLocationId ?? items[0]?.id ?? null;
        setLocationId(fallback);
        setFromLocationId(fallback);
        setToLocationId(items.find((item) => item.id !== fallback)?.id ?? fallback);
      })
      .catch(() => setLocations([]));
  }, [visible, defaultLocationId]);

  useEffect(() => {
    if (!visible || !product) return;

    setQuantity('1');
    setDelta('');
    setUnitCost(String(product.precoCompra ?? 0));
    setSupplier('Reposição Mobile');
    setNote('');
    setError('');
    setSaldoLocal(null);
  }, [visible, product, mode]);

  useEffect(() => {
    if (!visible || !product) return;

    const locationParaSaldo =
      mode === 'transfer' ? fromLocationId : locationId;

    if (!locationParaSaldo) {
      setSaldoLocal(null);
      return;
    }

    fetchStockBalance(locationParaSaldo, product.id)
      .then(setSaldoLocal)
      .catch(() => setSaldoLocal(null));
  }, [visible, product, mode, locationId, fromLocationId]);

  async function handleSave() {
    if (!product || submitting) return;
    setError('');

    setSubmitting(true);
    try {
      if (mode === 'reload') {
        if (!locationId) {
          setError('Seleccione a localização de destino.');
          return;
        }
        const qty = Number(quantity.replace(',', '.'));
        const cost = Number(unitCost.replace(',', '.'));
        if (!qty || qty <= 0) {
          setError('Informe uma quantidade válida.');
          return;
        }
        if (cost < 0 || Number.isNaN(cost)) {
          setError('Informe um custo unitário válido.');
          return;
        }

        await reloadStock({
          product_id: product.id,
          quantity: qty,
          unit_cost: cost,
          supplier: supplier.trim() || undefined,
          note: note.trim() || undefined,
          to_location_id: locationId,
        });
      } else if (mode === 'adjust') {
        if (!locationId) {
          setError('Seleccione a localização.');
          return;
        }
        const valorDelta = Number(delta.replace(',', '.'));
        if (!valorDelta || valorDelta === 0 || Number.isNaN(valorDelta)) {
          setError('Informe um ajuste diferente de zero.');
          return;
        }

        const cost = Number(unitCost.replace(',', '.'));
        await adjustStock({
          product_id: product.id,
          location_id: locationId,
          delta: valorDelta,
          note: note.trim() || undefined,
          unit_cost: Number.isNaN(cost) ? undefined : cost,
        });
      } else {
        if (!fromLocationId || !toLocationId) {
          setError('Seleccione origem e destino.');
          return;
        }
        if (fromLocationId === toLocationId) {
          setError('Origem e destino devem ser diferentes.');
          return;
        }
        const qty = Number(quantity.replace(',', '.'));
        if (!qty || qty <= 0) {
          setError('Informe uma quantidade válida.');
          return;
        }

        await createStockTransfer({
          from_location_id: fromLocationId,
          to_location_id: toLocationId,
          product_id: product.id,
          quantity: qty,
          note: note.trim() || undefined,
        });
      }

      onSaved();
      Alert.alert('Sucesso', 'Operação de stock concluída com sucesso.');
      onClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Não foi possível concluir a operação.');
    } finally {
      setSubmitting(false);
    }
  }

  function renderLocationChips(
    selectedId: string | null,
    onSelect: (id: string) => void,
    excludeId?: string | null,
  ) {
    const items = excludeId ? locations.filter((item) => item.id !== excludeId) : locations;

    return (
      <View style={styles.chipRow}>
        {items.map((item) => (
          <Pressable
            key={item.id}
            style={[styles.chip, selectedId === item.id && styles.chipActive]}
            disabled={submitting}
            onPress={() => onSelect(item.id)}
          >
            <Text style={[styles.chipText, selectedId === item.id && styles.chipTextActive]}>
              {item.name}
            </Text>
          </Pressable>
        ))}
      </View>
    );
  }

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>{titulos[mode]}</Text>
            <Pressable onPress={onClose} style={styles.closeButton} disabled={submitting}>
              <Text style={styles.closeText}>Fechar</Text>
            </Pressable>
          </View>

          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            {product ? (
              <View style={styles.infoCard}>
                <Text style={styles.productName}>{product.nome}</Text>
                <Text style={styles.infoLine}>
                  Stock global: {formatarStock(product.stock, product.unidadeVenda)}
                </Text>
                {saldoLocal !== null ? (
                  <Text style={styles.infoLine}>
                    Saldo na localização: {formatarStock(saldoLocal, product.unidadeVenda)}
                  </Text>
                ) : null}
              </View>
            ) : null}

            {mode === 'transfer' ? (
              <>
                <Text style={styles.label}>Origem</Text>
                {renderLocationChips(fromLocationId, setFromLocationId, toLocationId)}

                <Text style={styles.label}>Destino</Text>
                {renderLocationChips(toLocationId, setToLocationId, fromLocationId)}
              </>
            ) : (
              <>
                <Text style={styles.label}>Localização</Text>
                {renderLocationChips(locationId, setLocationId)}
              </>
            )}

            {mode === 'adjust' ? (
              <>
                <Text style={styles.label}>Ajuste (+/-)</Text>
                <TextInput
                  value={delta}
                  onChangeText={setDelta}
                  keyboardType="decimal-pad"
                  placeholder="Ex: -2 ou 5"
                  style={styles.input}
                  editable={!submitting}
                />
              </>
            ) : (
              <>
                <Text style={styles.label}>Quantidade</Text>
                <TextInput
                  value={quantity}
                  onChangeText={setQuantity}
                  keyboardType="decimal-pad"
                  style={styles.input}
                  editable={!submitting}
                />
              </>
            )}

            {mode === 'reload' ? (
              <>
                <Text style={styles.label}>Custo unitário</Text>
                <TextInput
                  value={unitCost}
                  onChangeText={setUnitCost}
                  keyboardType="decimal-pad"
                  style={styles.input}
                  editable={!submitting}
                />

                <Text style={styles.label}>Fornecedor</Text>
                <TextInput
                  value={supplier}
                  onChangeText={setSupplier}
                  style={styles.input}
                  editable={!submitting}
                />
              </>
            ) : mode === 'adjust' ? (
              <>
                <Text style={styles.label}>Custo unitário (opcional)</Text>
                <TextInput
                  value={unitCost}
                  onChangeText={setUnitCost}
                  keyboardType="decimal-pad"
                  style={styles.input}
                  editable={!submitting}
                />
              </>
            ) : null}

            <Text style={styles.label}>Nota (opcional)</Text>
            <TextInput
              value={note}
              onChangeText={setNote}
              style={styles.input}
              editable={!submitting}
            />

            {error ? <Text style={styles.error}>{error}</Text> : null}

            <Pressable
              style={[styles.saveButton, submitting && styles.saveButtonDisabled]}
              onPress={handleSave}
              disabled={submitting}
            >
              <Text style={styles.saveButtonText}>
                {submitting ? 'A processar...' : 'Confirmar'}
              </Text>
            </Pressable>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    justifyContent: 'flex-end',
  },
  sheet: {
    maxHeight: '92%',
    backgroundColor: brand.white,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    paddingBottom: 24,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: brand.border,
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  title: { fontSize: 16, fontWeight: '700', color: brand.dark },
  closeButton: { paddingHorizontal: 8, paddingVertical: 4 },
  closeText: { color: brand.danger, fontWeight: '700' },
  content: { padding: 16, gap: 8 },
  infoCard: {
    backgroundColor: brand.background,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 12,
    padding: 14,
    gap: 6,
    marginBottom: 4,
  },
  productName: { fontSize: 15, fontWeight: '700', color: brand.dark },
  infoLine: { fontSize: 13, color: brand.muted },
  label: { color: brand.muted, fontSize: 12, fontWeight: '600', marginTop: 4 },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    backgroundColor: brand.white,
  },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4 },
  chip: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: brand.white,
  },
  chipActive: { backgroundColor: brand.dark, borderColor: brand.dark },
  chipText: { color: brand.muted, fontWeight: '600', fontSize: 12 },
  chipTextActive: { color: brand.white },
  error: { color: brand.danger, fontSize: 13, marginTop: 4 },
  saveButton: {
    marginTop: 12,
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonDisabled: { opacity: 0.6 },
  saveButtonText: { fontWeight: '700', color: brand.dark, fontSize: 15 },
});
