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
import { SymbolView } from 'expo-symbols';
import { ApiError } from '@/src/api/httpClient';
import {
  createProduct,
  updateProduct,
  type IvaTipo,
  type Product,
  type SaleUnit,
} from '@/src/api/productsApi';
import BarcodeCaptureModal from '@/src/components/BarcodeCaptureModal';
import { brand, formatMt } from '@/src/theme/brand';

type Props = {
  visible: boolean;
  /** null = criar produto novo */
  product: Product | null;
  mode?: 'create' | 'edit';
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

function resetFormDefaults(
  setters: {
    setNome: (v: string) => void;
    setCodigoBarras: (v: string) => void;
    setCategoria: (v: string) => void;
    setUnidadeVenda: (v: SaleUnit) => void;
    setPrecoCompra: (v: string) => void;
    setPrecoVenda: (v: string) => void;
    setIvaTipo: (v: IvaTipo) => void;
    setIvaPercentual: (v: string) => void;
    setIvaValor: (v: string) => void;
    setStockInicial: (v: string) => void;
    setIsActive: (v: boolean) => void;
    setControlaEstoque: (v: boolean) => void;
    setError: (v: string) => void;
  },
) {
  setters.setNome('');
  setters.setCodigoBarras('');
  setters.setCategoria('');
  setters.setUnidadeVenda('UN');
  setters.setPrecoCompra('0');
  setters.setPrecoVenda('');
  setters.setIvaTipo('ISENTO');
  setters.setIvaPercentual('0');
  setters.setIvaValor('0');
  setters.setStockInicial('0');
  setters.setIsActive(true);
  setters.setControlaEstoque(true);
  setters.setError('');
}

export default function ProductEditModal({
  visible,
  product,
  mode = product ? 'edit' : 'create',
  onClose,
  onSaved,
}: Props) {
  const isCreate = mode === 'create' || !product;
  const [nome, setNome] = useState('');
  const [codigoBarras, setCodigoBarras] = useState('');
  const [categoria, setCategoria] = useState('');
  const [unidadeVenda, setUnidadeVenda] = useState<SaleUnit>('UN');
  const [precoCompra, setPrecoCompra] = useState('0');
  const [precoVenda, setPrecoVenda] = useState('');
  const [ivaTipo, setIvaTipo] = useState<IvaTipo>('ISENTO');
  const [ivaPercentual, setIvaPercentual] = useState('0');
  const [ivaValor, setIvaValor] = useState('0');
  const [stockInicial, setStockInicial] = useState('0');
  const [isActive, setIsActive] = useState(true);
  const [controlaEstoque, setControlaEstoque] = useState(true);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [scannerVisible, setScannerVisible] = useState(false);

  useEffect(() => {
    if (!visible) return;

    if (isCreate) {
      resetFormDefaults({
        setNome,
        setCodigoBarras,
        setCategoria,
        setUnidadeVenda,
        setPrecoCompra,
        setPrecoVenda,
        setIvaTipo,
        setIvaPercentual,
        setIvaValor,
        setStockInicial,
        setIsActive,
        setControlaEstoque,
        setError,
      });
      return;
    }

    if (!product) return;

    setNome(product.nome);
    setCodigoBarras(product.codigoBarras ?? '');
    setCategoria(product.categoria ?? '');
    setUnidadeVenda(product.unidadeVenda);
    setPrecoCompra(String(product.precoCompra ?? 0));
    setPrecoVenda(String(product.precoVenda ?? 0));
    setIvaTipo(product.ivaTipo ?? 'ISENTO');
    setIvaPercentual(String(product.ivaPercentual ?? 0));
    setIvaValor(String(product.ivaValor ?? 0));
    setStockInicial(String(product.stock ?? 0));
    setIsActive(product.isActive);
    setControlaEstoque(product.controlaEstoque !== false);
    setError('');
  }, [visible, product, isCreate]);

  async function handleSave() {
    if (submitting) return;

    setError('');

    const compra = Number(precoCompra.replace(',', '.'));
    const venda = Number(precoVenda.replace(',', '.'));
    const ivaPerc = Number(ivaPercentual.replace(',', '.'));
    const ivaMon = Number(ivaValor.replace(',', '.'));
    const stock = Number(stockInicial.replace(',', '.'));

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

    if (isCreate && (!Number.isFinite(stock) || stock < 0)) {
      setError('Stock inicial inválido.');
      return;
    }

    setSubmitting(true);
    try {
      if (isCreate) {
        await createProduct({
          nome: nome.trim(),
          codigoBarras: codigoBarras.trim() || null,
          categoria: categoria.trim() || null,
          unidadeVenda,
          precoCompra: compra,
          precoVenda: venda,
          ivaTipo,
          ivaPercentual: ivaTipo === 'PERCENTUAL' ? ivaPerc : 0,
          ivaValor: ivaTipo === 'MONETARIO' ? ivaMon : 0,
          stock: controlaEstoque ? stock : 0,
          controlaEstoque,
        });
        Alert.alert('Sucesso', 'Produto criado com sucesso.');
      } else if (product) {
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
        Alert.alert('Sucesso', 'Produto actualizado com sucesso.');
      }

      onSaved();
      onClose();
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : isCreate
            ? 'Não foi possível criar o produto.'
            : 'Não foi possível guardar o produto.',
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
        <View style={styles.overlay}>
          <View style={styles.sheet}>
            <View style={styles.header}>
              <Text style={styles.title}>{isCreate ? 'Novo produto' : 'Editar produto'}</Text>
              <Pressable onPress={onClose} style={styles.closeButton} disabled={submitting}>
                <Text style={styles.closeText}>Fechar</Text>
              </Pressable>
            </View>

            <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
              <Text style={styles.label}>Nome</Text>
              <TextInput value={nome} onChangeText={setNome} style={styles.input} editable={!submitting} />

              <Text style={styles.label}>Código de barras</Text>
              <View style={styles.barcodeRow}>
                <TextInput
                  value={codigoBarras}
                  onChangeText={setCodigoBarras}
                  style={[styles.input, styles.barcodeInput]}
                  autoCapitalize="none"
                  editable={!submitting}
                  placeholder="Escanear ou digitar"
                />
                <Pressable
                  style={[styles.scanBtn, submitting && styles.saveButtonDisabled]}
                  onPress={() => setScannerVisible(true)}
                  disabled={submitting}
                  accessibilityLabel="Escanear código de barras"
                >
                  <SymbolView
                    name={{ ios: 'barcode.viewfinder', android: 'qr_code_scanner', web: 'qr_code_scanner' }}
                    tintColor={brand.dark}
                    size={22}
                  />
                </Pressable>
              </View>

              <Text style={styles.label}>Categoria</Text>
              <TextInput
                value={categoria}
                onChangeText={setCategoria}
                style={styles.input}
                editable={!submitting}
              />

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

              {isCreate ? (
                <>
                  <Text style={styles.label}>
                    {unidadeVenda === 'KG' ? 'Stock inicial (kg)' : 'Stock inicial'}
                  </Text>
                  <TextInput
                    value={stockInicial}
                    onChangeText={setStockInicial}
                    keyboardType="decimal-pad"
                    style={[styles.input, !controlaEstoque && styles.inputDisabled]}
                    editable={!submitting && controlaEstoque}
                  />
                  {!controlaEstoque ? (
                    <Text style={styles.hint}>Produto sob pedido — stock inicial não é obrigatório.</Text>
                  ) : null}
                </>
              ) : product ? (
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
              ) : null}

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

              {!isCreate ? (
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
              ) : null}

              {error ? <Text style={styles.error}>{error}</Text> : null}

              <Pressable
                style={[styles.saveButton, submitting && styles.saveButtonDisabled]}
                onPress={handleSave}
                disabled={submitting}
              >
                <Text style={styles.saveButtonText}>
                  {submitting
                    ? isCreate
                      ? 'A criar...'
                      : 'A guardar...'
                    : isCreate
                      ? 'Criar produto'
                      : 'Guardar alterações'}
                </Text>
              </Pressable>
            </ScrollView>
          </View>
        </View>
      </Modal>

      <BarcodeCaptureModal
        visible={scannerVisible}
        onClose={() => setScannerVisible(false)}
        onCapture={(code) => setCodigoBarras(code)}
      />
    </>
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
  inputDisabled: { backgroundColor: brand.background, color: brand.muted },
  barcodeRow: { flexDirection: 'row', gap: 8, alignItems: 'center' },
  barcodeInput: { flex: 1 },
  scanBtn: {
    width: 48,
    height: 48,
    borderRadius: 10,
    backgroundColor: brand.gold,
    alignItems: 'center',
    justifyContent: 'center',
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
