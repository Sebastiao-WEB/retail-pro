import { useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import type { SaleDetail } from '../../api/salesApi';
import { brand, formatMt } from '../../theme/brand';

type Props = {
  visible: boolean;
  sale: SaleDetail | null;
  blockReason?: string;
  loading?: boolean;
  onConfirm: (reason: string) => void;
  onClose: () => void;
};

export default function RequestReversalModal({
  visible,
  sale,
  blockReason = '',
  loading = false,
  onConfirm,
  onClose,
}: Props) {
  const [reason, setReason] = useState('');

  function handleConfirm() {
    onConfirm(reason.trim());
    setReason('');
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.card}>
          <Text style={styles.title}>Solicitar reversão</Text>
          {sale ? (
            <Text style={styles.subtitle}>
              {sale.referencia} · {formatMt(sale.total)}
            </Text>
          ) : null}

          {blockReason ? <Text style={styles.error}>{blockReason}</Text> : null}

          <Text style={styles.label}>Motivo (opcional)</Text>
          <TextInput
            value={reason}
            onChangeText={setReason}
            multiline
            style={[styles.input, styles.textArea]}
            placeholder="Descreva o motivo da reversão"
            editable={!blockReason}
          />

          <View style={styles.actions}>
            <Pressable style={[styles.btn, styles.cancel]} onPress={onClose} disabled={loading}>
              <Text style={styles.cancelText}>Cancelar</Text>
            </Pressable>
            <Pressable
              style={[styles.btn, styles.confirm, !!blockReason && styles.btnDisabled]}
              onPress={handleConfirm}
              disabled={loading || !!blockReason}
            >
              {loading ? (
                <ActivityIndicator color={brand.dark} />
              ) : (
                <Text style={styles.confirmText}>Solicitar</Text>
              )}
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
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
  label: { fontSize: 12, fontWeight: '600', color: brand.muted },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 15,
  },
  textArea: { minHeight: 80, textAlignVertical: 'top' },
  error: { color: brand.danger, fontSize: 13 },
  actions: { flexDirection: 'row', gap: 10, marginTop: 4 },
  btn: { flex: 1, borderRadius: 10, paddingVertical: 12, alignItems: 'center' },
  cancel: { borderWidth: 1, borderColor: brand.border },
  confirm: { backgroundColor: brand.gold },
  btnDisabled: { opacity: 0.5 },
  cancelText: { color: brand.muted, fontWeight: '700' },
  confirmText: { color: brand.dark, fontWeight: '700' },
});
