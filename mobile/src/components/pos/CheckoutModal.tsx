import { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useCartStore } from '../../store/cartStore';
import { brand, formatMt } from '../../theme/brand';
import { parseQuantityText } from '../../utils/productQuantity';
import { roundMoney } from '../../utils/saleShift';

type Props = {
  visible: boolean;
  loading?: boolean;
  onConfirm: (payload: {
    cliente: string;
    valorPago: number;
    troco: number;
  }) => void;
  onClose: () => void;
};

function formatQuantityLabel(quantity: number, unit: 'UN' | 'KG') {
  if (unit === 'KG') {
    return quantity.toLocaleString('pt-MZ', { maximumFractionDigits: 3 });
  }
  return String(Math.floor(quantity));
}

export default function CheckoutModal({ visible, loading = false, onConfirm, onClose }: Props) {
  const cart = useCartStore();
  const [cliente, setCliente] = useState('Cliente Geral');
  const [valorPago, setValorPago] = useState('');
  const [discountInput, setDiscountInput] = useState('');

  const isCash = cart.paymentMethod === 'Dinheiro';
  const paid = roundMoney(Number(String(valorPago).replace(',', '.')) || 0);
  const troco = isCash ? roundMoney(Math.max(0, paid - cart.total)) : 0;
  const falta = isCash ? roundMoney(Math.max(0, cart.total - paid)) : 0;
  const canConfirm = useMemo(() => {
    if (cart.items.length === 0) return false;
    if (!isCash) return true;
    return paid >= cart.total;
  }, [cart.items.length, cart.total, isCash, paid]);

  function applyDiscount() {
    const value = Number(String(discountInput).replace(',', '.'));
    if (!Number.isFinite(value)) return;
    cart.setDiscount('valor', value);
  }

  function handleQuantityBlur(productId: string, text: string, unit: 'UN' | 'KG') {
    const parsed = parseQuantityText(text, unit);
    if (!parsed) {
      cart.removeProduct(productId);
      return;
    }
    cart.setQuantity(productId, parsed);
  }

  function handleConfirm() {
    if (!canConfirm) return;
    onConfirm({
      cliente: cliente.trim() || 'Cliente Geral',
      valorPago: isCash ? paid : cart.total,
      troco,
    });
  }

  function resetOnClose() {
    setCliente('Cliente Geral');
    setValorPago('');
    setDiscountInput('');
  }

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={() => {
        resetOnClose();
        onClose();
      }}
    >
      <View style={styles.overlay}>
        <View style={styles.card}>
          <Text style={styles.title}>Finalizar venda</Text>

          <ScrollView contentContainerStyle={styles.scroll}>
            {cart.items.map((item) => (
              <View key={item.produtoId} style={styles.lineCard}>
                <View style={styles.lineHeader}>
                  <Text style={styles.lineName} numberOfLines={2}>
                    {item.nome}
                  </Text>
                  <Pressable
                    style={styles.removeBtn}
                    onPress={() => cart.removeProduct(item.produtoId)}
                    disabled={loading}
                  >
                    <Text style={styles.removeBtnText}>Remover</Text>
                  </Pressable>
                </View>

                <View style={styles.lineFooter}>
                  <View style={styles.qtyRow}>
                    <Pressable
                      style={styles.qtyBtn}
                      onPress={() => cart.decreaseQuantity(item.produtoId)}
                      disabled={loading}
                    >
                      <Text style={styles.qtyBtnText}>−</Text>
                    </Pressable>
                    <TextInput
                      value={formatQuantityLabel(item.quantidade, item.unidadeVenda)}
                      onChangeText={(text) => {
                        const parsed = parseQuantityText(text, item.unidadeVenda);
                        if (parsed) cart.setQuantity(item.produtoId, parsed);
                      }}
                      onBlur={(event) =>
                        handleQuantityBlur(item.produtoId, event.nativeEvent.text, item.unidadeVenda)
                      }
                      keyboardType={item.unidadeVenda === 'KG' ? 'decimal-pad' : 'number-pad'}
                      style={styles.qtyInput}
                      selectTextOnFocus
                    />
                    <Pressable
                      style={styles.qtyBtn}
                      onPress={() => cart.increaseQuantity(item.produtoId)}
                      disabled={loading}
                    >
                      <Text style={styles.qtyBtnText}>+</Text>
                    </Pressable>
                    <Text style={styles.qtyUnit}>{item.unidadeVenda === 'KG' ? 'kg' : 'un'}</Text>
                  </View>
                  <Text style={styles.lineTotal}>{formatMt(item.subtotal)}</Text>
                </View>
              </View>
            ))}

            <View style={styles.summaryRow}>
              <Text style={styles.summaryLabel}>Subtotal</Text>
              <Text style={styles.summaryValue}>{formatMt(cart.subtotal)}</Text>
            </View>
            {cart.discountAmount > 0 ? (
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Desconto</Text>
                <Text style={styles.summaryValue}>-{formatMt(cart.discountAmount)}</Text>
              </View>
            ) : null}
            <View style={styles.summaryRow}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.totalValue}>{formatMt(cart.total)}</Text>
            </View>

            <Text style={styles.label}>Cliente</Text>
            <TextInput value={cliente} onChangeText={setCliente} style={styles.input} />

            <Text style={styles.label}>Método de pagamento</Text>
            <View style={styles.methodRow}>
              {(['Dinheiro', 'Transferência'] as const).map((method) => (
                <Pressable
                  key={method}
                  style={[styles.methodChip, cart.paymentMethod === method && styles.methodChipActive]}
                  onPress={() => cart.setPaymentMethod(method)}
                >
                  <Text
                    style={[
                      styles.methodText,
                      cart.paymentMethod === method && styles.methodTextActive,
                    ]}
                  >
                    {method}
                  </Text>
                </Pressable>
              ))}
            </View>

            <Text style={styles.label}>Desconto (MT)</Text>
            <View style={styles.discountRow}>
              <TextInput
                value={discountInput}
                onChangeText={setDiscountInput}
                keyboardType="decimal-pad"
                style={[styles.input, styles.discountInput]}
                placeholder="0"
              />
              <Pressable style={styles.discountBtn} onPress={applyDiscount}>
                <Text style={styles.discountBtnText}>Aplicar</Text>
              </Pressable>
            </View>

            {isCash ? (
              <>
                <Text style={styles.label}>Valor pago</Text>
                <TextInput
                  value={valorPago}
                  onChangeText={setValorPago}
                  keyboardType="decimal-pad"
                  style={styles.input}
                  placeholder={String(cart.total)}
                />
                {paid > 0 && falta > 0 ? (
                  <Text style={styles.warning}>Falta {formatMt(falta)}</Text>
                ) : null}
                {paid >= cart.total ? (
                  <Text style={styles.success}>Troco: {formatMt(troco)}</Text>
                ) : null}
              </>
            ) : null}
          </ScrollView>

          <View style={styles.actions}>
            <Pressable
              style={[styles.btn, styles.cancel]}
              onPress={() => {
                resetOnClose();
                onClose();
              }}
              disabled={loading}
            >
              <Text style={styles.cancelText}>Cancelar</Text>
            </Pressable>
            <Pressable
              style={[styles.btn, styles.confirm, !canConfirm && styles.btnDisabled]}
              onPress={handleConfirm}
              disabled={loading || !canConfirm}
            >
              {loading ? (
                <ActivityIndicator color={brand.dark} />
              ) : (
                <Text style={styles.confirmText}>Confirmar venda</Text>
              )}
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: { flex: 1, backgroundColor: 'rgba(15, 23, 42, 0.6)', justifyContent: 'flex-end' },
  card: {
    backgroundColor: brand.white,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    maxHeight: '92%',
  },
  title: { fontSize: 20, fontWeight: '700', color: brand.dark, marginBottom: 8 },
  scroll: { gap: 8, paddingBottom: 8 },
  lineCard: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    padding: 10,
    gap: 8,
  },
  lineHeader: { flexDirection: 'row', justifyContent: 'space-between', gap: 8, alignItems: 'flex-start' },
  lineName: { flex: 1, color: brand.dark, fontWeight: '600' },
  removeBtn: {
    borderWidth: 1,
    borderColor: brand.danger,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  removeBtnText: { color: brand.danger, fontSize: 12, fontWeight: '700' },
  lineFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  qtyRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  qtyBtn: {
    width: 32,
    height: 32,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: brand.border,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: brand.background,
  },
  qtyBtnText: { fontSize: 18, fontWeight: '700', color: brand.dark, lineHeight: 20 },
  qtyInput: {
    minWidth: 56,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 6,
    textAlign: 'center',
    fontWeight: '700',
    color: brand.dark,
  },
  qtyUnit: { color: brand.muted, fontSize: 12, fontWeight: '600' },
  lineTotal: { fontWeight: '700', color: brand.dark },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between' },
  summaryLabel: { color: brand.muted },
  summaryValue: { fontWeight: '600', color: brand.dark },
  totalLabel: { fontWeight: '700', color: brand.dark },
  totalValue: { fontWeight: '700', color: brand.dark, fontSize: 18 },
  label: { fontSize: 12, fontWeight: '600', color: brand.muted, marginTop: 4 },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
  },
  methodRow: { flexDirection: 'row', gap: 8 },
  methodChip: {
    flex: 1,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: 'center',
  },
  methodChipActive: { backgroundColor: brand.dark, borderColor: brand.dark },
  methodText: { fontWeight: '600', color: brand.muted },
  methodTextActive: { color: brand.white },
  discountRow: { flexDirection: 'row', gap: 8, alignItems: 'center' },
  discountInput: { flex: 1 },
  discountBtn: {
    backgroundColor: brand.background,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  discountBtnText: { fontWeight: '700', color: brand.dark },
  warning: { color: brand.danger, fontWeight: '600' },
  success: { color: brand.success, fontWeight: '600' },
  actions: { flexDirection: 'row', gap: 10, marginTop: 8 },
  btn: { flex: 1, borderRadius: 10, paddingVertical: 14, alignItems: 'center' },
  cancel: { borderWidth: 1, borderColor: brand.border },
  confirm: { backgroundColor: brand.gold },
  btnDisabled: { opacity: 0.5 },
  cancelText: { color: brand.muted, fontWeight: '700' },
  confirmText: { color: brand.dark, fontWeight: '700' },
});
