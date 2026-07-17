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
              <View key={item.produtoId} style={styles.line}>
                <Text style={styles.lineName} numberOfLines={1}>
                  {item.quantidade}× {item.nome}
                </Text>
                <Text style={styles.lineTotal}>{formatMt(item.subtotal)}</Text>
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
  line: { flexDirection: 'row', justifyContent: 'space-between', gap: 8 },
  lineName: { flex: 1, color: brand.dark },
  lineTotal: { fontWeight: '600', color: brand.dark },
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
