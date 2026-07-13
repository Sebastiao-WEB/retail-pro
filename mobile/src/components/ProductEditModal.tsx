import { useEffect, useState } from 'react';
import {
  Alert,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';
import { ApiError } from '@/src/api/httpClient';
import {
  updateProduct,
  type IvaTipo,
  type Product,
  type SaleUnit,
} from '@/src/api/productsApi';
import { brand, formatMt } from '@/src/theme/brand';

type Props = {
  visible: boolean;
  product: Product | null;
  onClose: () => void;
  onSaved: () => void;
};

const unidades: Array<{ key: SaleUnit; label: string }> = [
  { key: 'UN', label: 'Por unidade' },
  { key: 'KG', label: 'Por peso (kg)' },
];

const tiposIva: Array<{ key: IvaTipo; label: string }> = [
  { key: 'ISENTO', label: 'Isento' },
  { key: 'PERCENTUAL', label: 'Percentual' },
  { key: 'MONETARIO', label: 'Monetário' },
];

function formatarStock(stock: number, unidade: SaleUnit) {
  const valor = Number(stock || 0);
  return unidade === 'KG' ? `${valor} kg` : String(valor);
}

export default function ProductEditModal({ visible, product, onClose, onSaved }: Props) {
  const [nome, setNome] = useState('');
  const [codigoBarras, setCodigoBarras] = useState('');
  const [categoria, setCategoria] = useState('');
  const [unidadeVenda, setUnidadeVenda] = useState<SaleUnit>('UN');
  const [precoCompra, setPrecoCompra] = useState('');
  const [precoVenda, setPrecoVenda] = useState('');
  const [ivaTipo, setIvaTipo] = useState<IvaTipo>('ISENTO');
  const [ivaPercentual, setIvaPercentual] = useState('');
  const [ivaValor, setIvaValor] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [controlaEstoque, setControlaEstoque] = useState(true);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!visible || !product) return;

    setNome(product.nome);
    setCodigoBarras(product.codigoBarras ?? '');
    setCategoria(product.categoria ?? '');
    setUnidadeVenda(product.unidadeVenda);
    setPrecoCompra(String(product.precoCompra ?? 0));
    setPrecoVenda(String(product.precoVenda ?? 0));
    setIvaTipo(product.ivaTipo ?? 'ISENTO');
    setIvaPercentual(String(product.ivaPercentual ?? 0));
    setIvaValor(String(product.ivaValor ?? 0));
    setIsActive(product.isActive);
    setControlaEstoque(product.controlaEstoque !== false);
    setError('');
  }, [visible, product]);

  async function handleSave() {
    if (!product || submitting) return;

    setError('');

    const compra = Number(precoCompra.replace(',', '.'));
    const venda = Number(precoVenda.replace(',', '.'));
    const ivaPerc = Number(ivaPercentual.replace(',', '.'));
    const ivaMon = Number(ivaValor.replace(',', '.'));

    if (!nome.trim()) {
      setError('Informe o nome do produto.');
      return;
    }

    if (!Number.isFinite(compra) || !Number.isFinite(venda)) {
      setError('Preços inválidos.');
      return;
    }

    if (ivaTipo === 'PERCENTUAL' && (!Number.isFinite(ivaPerc) || ivaPerc <= 0)) {
      setError('Informe um IVA percentual maior que zero.');
      return;
    }

    if (ivaTipo === 'MONETARIO' && (!Number.isFinite(ivaMon) || ivaMon <= 0)) {
      setError('Informe um valor de IVA maior que zero.');
      return;
    }

    setSubmitting(true);
    try {
      await updateProduct(product.id, {
        nome: nome.trim(),
        codigoBarras: codigoBarras.trim() || null,
        categoria: categoria.trim() || null,
        unidadeVenda,
        precoCompra: compra,
        precoVenda: venda,
        ivaTipo,
        ivaPercentual: ivaTipo === 'PERCENTUAL' ? ivaPerc : 0,
        ivaValor: ivaTipo === 'MONETARIO' ? ivaMon : 0,
        is_active: isActive,
        controlaEstoque,
      });

      onSaved();
      Alert.alert('Sucesso', 'Produto actualizado com sucesso.');
      onClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Não foi possível guardar o produto.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>Editar produto</Text>
            <Pressable onPress={onClose} style={styles.closeButton} disabled={submitting}>
              <Text style={styles.closeText}>Fechar</Text>
            </Pressable>
          </View>

          {product ? (
            <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
              <Text style={styles.label}>Nome</Text>
              <TextInput value={nome} onChangeText={setNome} style={styles.input} editable={!submitting} />

              <Text style={styles.label}>Código de barras</Text>
              <TextInput
                value={codigoBarras}
                onChangeText={setCodigoBarras}
                style={styles.input}
                autoCapitalize="none"
                editable={!submitting}
              />

              <Text style={styles.label}>Categoria</Text>
              <TextInput value={categoria} onChangeText={setCategoria} style={styles.input} editable={!submitting} />

              <Text style={styles.label}>Unidade de venda</Text>
              <View style={styles.chipRow}>
                {unidades.map((item) => (
                  <Pressable
                    key={item.key}
                    style={[styles.chip, unidadeVenda === item.key && styles.chipActive]}
                    disabled={submitting}
                    onPress={() => setUnidadeVenda(item.key)}
                  >
                    <Text style={[styles.chipText, unidadeVenda === item.key && styles.chipTextActive]}>
                      {item.label}
                    </Text>
                  </Pressable>
                ))}
              </View>
              <Text style={styles.hint}>
                {unidadeVenda === 'KG'
                  ? 'Preço e stock em quilogramas; no POS introduz kg (ex.: 0,7 ou 1,2).'
                  : 'Venda por unidade inteira.'}
              </Text>

              <Text style={styles.label}>Preço de compra</Text>
              <TextInput
                value={precoCompra}
                onChangeText={setPrecoCompra}
                keyboardType="decimal-pad"
                style={styles.input}
                editable={!submitting}
              />

              <Text style={styles.label}>
                {unidadeVenda === 'KG' ? 'Preço de venda (por kg)' : 'Preço de venda'}
              </Text>
              <TextInput
                value={precoVenda}
                onChangeText={setPrecoVenda}
                keyboardType="decimal-pad"
                style={styles.input}
                editable={!submitting}
              />

              <Text style={styles.label}>Tipo de IVA</Text>
              <View style={styles.chipRow}>
                {tiposIva.map((item) => (
                  <Pressable
                    key={item.key}
                    style={[styles.chip, ivaTipo === item.key && styles.chipActive]}
                    disabled={submitting}
                    onPress={() => setIvaTipo(item.key)}
                  >
                    <Text style={[styles.chipText, ivaTipo === item.key && styles.chipTextActive]}>
                      {item.label}
                    </Text>
                  </Pressable>
                ))}
              </View>

              {ivaTipo === 'PERCENTUAL' ? (
                <>
                  <Text style={styles.label}>IVA (%)</Text>
                  <TextInput
                    value={ivaPercentual}
                    onChangeText={setIvaPercentual}
                    keyboardType="decimal-pad"
                    style={styles.input}
                    editable={!submitting}
                  />
                </>
              ) : null}

              {ivaTipo === 'MONETARIO' ? (
                <>
                  <Text style={styles.label}>IVA (valor)</Text>
                  <TextInput
                    value={ivaValor}
                    onChangeText={setIvaValor}
                    keyboardType="decimal-pad"
                    style={styles.input}
                    editable={!submitting}
                  />
                </>
              ) : null}

              <View style={styles.infoCard}>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Stock actual: </Text>
                  {controlaEstoque ? formatarStock(product.stock, unidadeVenda) : 'Sob pedido'}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Preço actual: </Text>
                  {formatMt(product.precoVenda)}
                </Text>
              </View>

              <View style={styles.switchRow}>
                <Text style={styles.label}>Controlar stock</Text>
                <Switch
                  value={controlaEstoque}
                  onValueChange={setControlaEstoque}
                  disabled={submitting}
                  trackColor={{ false: brand.border, true: brand.gold }}
                  thumbColor={brand.white}
                />
              </View>

              <View style={styles.switchRow}>
                <Text style={styles.label}>Produto activo</Text>
                <Switch
                  value={isActive}
                  onValueChange={setIsActive}
                  disabled={submitting}
                  trackColor={{ false: brand.border, true: brand.gold }}
                  thumbColor={brand.white}
                />
              </View>

              {error ? <Text style={styles.error}>{error}</Text> : null}

              <Pressable
                style={[styles.saveButton, submitting && styles.saveButtonDisabled]}
                onPress={handleSave}
                disabled={submitting}
              >
                <Text style={styles.saveButtonText}>{submitting ? 'A guardar...' : 'Guardar alterações'}</Text>
              </Pressable>
            </ScrollView>
          ) : null}
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
  hint: { color: brand.muted, fontSize: 11, lineHeight: 16 },
  infoCard: {
    backgroundColor: brand.background,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 12,
    padding: 14,
    gap: 6,
    marginTop: 4,
  },
  infoLine: { fontSize: 14, color: brand.dark },
  infoLabel: { fontWeight: '700' },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 8,
  },
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
