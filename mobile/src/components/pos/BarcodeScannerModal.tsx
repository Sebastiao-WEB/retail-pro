import { useEffect, useState } from 'react';
import {
  Modal,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { brand } from '../../theme/brand';

type Props = {
  visible: boolean;
  onScan: (barcode: string) => void;
  onClose: () => void;
};

export default function BarcodeScannerModal({ visible, onScan, onClose }: Props) {
  const [permission, requestPermission] = useCameraPermissions();
  const [manualCode, setManualCode] = useState('');
  const [scanned, setScanned] = useState(false);

  useEffect(() => {
    if (visible) {
      setScanned(false);
      setManualCode('');
    }
  }, [visible]);

  function handleBarcode(data: string) {
    if (scanned || !data) return;
    setScanned(true);
    onScan(data);
    onClose();
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <View style={styles.container}>
        <View style={styles.header}>
          <Text style={styles.title}>Ler código de barras</Text>
          <Pressable onPress={onClose}>
            <Text style={styles.close}>Fechar</Text>
          </Pressable>
        </View>

        {!permission?.granted ? (
          <View style={styles.permissionBox}>
            <Text style={styles.permissionText}>É necessário acesso à câmara para ler códigos.</Text>
            <Pressable style={styles.permissionBtn} onPress={requestPermission}>
              <Text style={styles.permissionBtnText}>Permitir câmara</Text>
            </Pressable>
          </View>
        ) : (
          <View style={styles.cameraWrap}>
            <CameraView
              style={styles.camera}
              facing="back"
              barcodeScannerSettings={{
                barcodeTypes: ['ean13', 'ean8', 'upc_a', 'upc_e', 'code128', 'code39', 'qr'],
              }}
              onBarcodeScanned={({ data }) => handleBarcode(String(data || ''))}
            />
          </View>
        )}

        <View style={styles.manualBox}>
          <Text style={styles.manualLabel}>Ou digite o código manualmente</Text>
          <TextInput
            value={manualCode}
            onChangeText={setManualCode}
            keyboardType="number-pad"
            style={styles.input}
            placeholder="Código de barras"
          />
          <Pressable
            style={styles.manualBtn}
            onPress={() => handleBarcode(manualCode.trim())}
            disabled={!manualCode.trim()}
          >
            <Text style={styles.manualBtnText}>Confirmar</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.dark },
  header: {
    paddingTop: 56,
    paddingHorizontal: 16,
    paddingBottom: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  title: { color: brand.white, fontSize: 18, fontWeight: '700' },
  close: { color: brand.gold, fontWeight: '700' },
  permissionBox: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24, gap: 12 },
  permissionText: { color: brand.white, textAlign: 'center' },
  permissionBtn: { backgroundColor: brand.gold, borderRadius: 10, paddingHorizontal: 16, paddingVertical: 12 },
  permissionBtnText: { fontWeight: '700', color: brand.dark },
  cameraWrap: { flex: 1, margin: 16, borderRadius: 16, overflow: 'hidden' },
  camera: { flex: 1 },
  manualBox: {
    backgroundColor: brand.white,
    padding: 16,
    gap: 8,
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
  },
  manualLabel: { fontWeight: '600', color: brand.muted, fontSize: 12 },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
  },
  manualBtn: {
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  manualBtnText: { fontWeight: '700', color: brand.dark },
});
