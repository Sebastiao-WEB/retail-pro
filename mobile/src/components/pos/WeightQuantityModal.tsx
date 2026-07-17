import { useEffect, useState } from 'react';
import {
  Modal,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import type { Product } from '../../api/productsApi';
import { brand } from '../../theme/brand';
import { parseQuantityText, soldByWeight } from '../../utils/productQuantity';

type Props = {
  visible: boolean;
  product: Product | null;
  onConfirm: (quantity: number) => void;
  onClose: () => void;
};

export default function WeightQuantityModal({ visible, product, onConfirm, onClose }: Props) {
  const [value, setValue] = useState('');
  const isWeight = product ? soldByWeight(product) : false;

  useEffect(() => {
    if (visible && product) {
      setValue(isWeight ? '' : '1');
      return;
    }
    if (!visible) {
      setValue('');
    }
  }, [visible, product, isWeight]);

  function handleConfirm() {
    if (!product) return;
    const quantity = parseQuantityText(value, product.unidadeVenda);
    if (!quantity) return;
    onConfirm(quantity);
    setValue('');
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.card}>
          <Text style={styles.title}>
            {isWeight ? 'Quantidade em kg' : 'Quantidade em unidades'}
          </Text>
          <Text style={styles.subtitle}>{product?.nome}</Text>
          <TextInput
            value={value}
            onChangeText={setValue}
            keyboardType={isWeight ? 'decimal-pad' : 'number-pad'}
            style={styles.input}
            placeholder={isWeight ? '0.250' : '1'}
            autoFocus
            selectTextOnFocus
          />
          <View style={styles.actions}>
            <Pressable style={[styles.btn, styles.cancel]} onPress={onClose}>
              <Text style={styles.cancelText}>Cancelar</Text>
            </Pressable>
            <Pressable style={[styles.btn, styles.confirm]} onPress={handleConfirm}>
              <Text style={styles.confirmText}>Adicionar</Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}

export function productNeedsQuantityModal(_product: Product) {
  return true;
}

/** @deprecated Use productNeedsQuantityModal */
export function productNeedsWeightModal(product: Product) {
  return soldByWeight(product);
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    justifyContent: 'center',
    padding: 24,
  },
  card: { backgroundColor: brand.white, borderRadius: 16, padding: 20, gap: 10 },
  title: { fontSize: 18, fontWeight: '700', color: brand.dark },
  subtitle: { color: brand.muted },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 18,
  },
  actions: { flexDirection: 'row', gap: 10, marginTop: 4 },
  btn: { flex: 1, borderRadius: 10, paddingVertical: 12, alignItems: 'center' },
  cancel: { borderWidth: 1, borderColor: brand.border },
  confirm: { backgroundColor: brand.gold },
  cancelText: { color: brand.muted, fontWeight: '700' },
  confirmText: { color: brand.dark, fontWeight: '700' },
});
