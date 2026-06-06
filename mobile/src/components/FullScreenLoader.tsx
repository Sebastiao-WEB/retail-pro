import { ActivityIndicator, Modal, StyleSheet, Text, View } from 'react-native';
import { brand } from '@/src/theme/brand';

type Props = {
  visible: boolean;
  message?: string;
};

export default function FullScreenLoader({ visible, message = 'Carregando...' }: Props) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={() => {}}>
      <View style={styles.overlay}>
        <ActivityIndicator size="large" color={brand.gold} />
        <Text style={styles.message}>{message}</Text>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 12,
  },
  message: {
    color: brand.white,
    fontSize: 15,
    fontWeight: '600',
  },
});
