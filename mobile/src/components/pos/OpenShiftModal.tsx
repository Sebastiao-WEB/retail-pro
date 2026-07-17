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
import { brand } from '../../theme/brand';

type Props = {
  visible: boolean;
  loading?: boolean;
  onConfirm: (openingBalance: number) => void;
  onClose?: () => void;
};

export default function OpenShiftModal({ visible, loading = false, onConfirm, onClose }: Props) {
  const [value, setValue] = useState('1000');

  function handleConfirm() {
    const amount = Number(String(value).replace(',', '.'));
    if (!Number.isFinite(amount) || amount < 0) return;
    onConfirm(amount);
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.card}>
          <Text style={styles.title}>Abrir caixa</Text>
          <Text style={styles.subtitle}>Informe o fundo inicial em dinheiro para iniciar o turno.</Text>

          <Text style={styles.label}>Fundo inicial (MT)</Text>
          <TextInput
            value={value}
            onChangeText={setValue}
            keyboardType="decimal-pad"
            style={styles.input}
            placeholder="1000"
          />

          <Pressable style={styles.button} onPress={handleConfirm} disabled={loading}>
            {loading ? (
              <ActivityIndicator color={brand.dark} />
            ) : (
              <Text style={styles.buttonText}>Abrir turno</Text>
            )}
          </Pressable>
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
  card: {
    backgroundColor: brand.white,
    borderRadius: 16,
    padding: 20,
    gap: 10,
  },
  title: { fontSize: 20, fontWeight: '700', color: brand.dark },
  subtitle: { color: brand.muted, fontSize: 13, marginBottom: 4 },
  label: { fontSize: 12, fontWeight: '600', color: brand.muted, marginTop: 4 },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 18,
  },
  button: {
    marginTop: 8,
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonText: { fontWeight: '700', color: brand.dark, fontSize: 16 },
});
