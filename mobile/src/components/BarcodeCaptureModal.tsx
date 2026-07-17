import { useEffect, useRef, useState } from 'react';
import {
  Animated,
  Easing,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { brand } from '../../theme/brand';

const SCANNER_RED = '#FF2D2D';
const VIEWFINDER_HEIGHT = 168;

type Props = {
  visible: boolean;
  onClose: () => void;
  onCapture: (barcode: string) => void;
};

function ScannerOverlay() {
  const scanLine = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    scanLine.setValue(0);
    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(scanLine, {
          toValue: 1,
          duration: 2400,
          easing: Easing.inOut(Easing.quad),
          useNativeDriver: true,
        }),
        Animated.timing(scanLine, {
          toValue: 0,
          duration: 2400,
          easing: Easing.inOut(Easing.quad),
          useNativeDriver: true,
        }),
      ]),
    );
    animation.start();
    return () => animation.stop();
  }, [scanLine]);

  const lineTranslateY = scanLine.interpolate({
    inputRange: [0, 1],
    outputRange: [0, VIEWFINDER_HEIGHT - 6],
  });

  return (
    <>
      <View style={styles.viewfinder} pointerEvents="none">
        <View style={[styles.corner, styles.cornerTopLeft]} />
        <View style={[styles.corner, styles.cornerTopRight]} />
        <View style={[styles.corner, styles.cornerBottomLeft]} />
        <View style={[styles.corner, styles.cornerBottomRight]} />
        <Animated.View style={[styles.scanLine, { transform: [{ translateY: lineTranslateY }] }]} />
      </View>
      <Text style={styles.scanHint}>Alinhe o código de barras na área vermelha</Text>
    </>
  );
}

/** Scanner só para capturar o código (ex.: formulário de produto). */
export default function BarcodeCaptureModal({ visible, onClose, onCapture }: Props) {
  const [permission, requestPermission] = useCameraPermissions();
  const [cameraReady, setCameraReady] = useState(false);
  const [scanned, setScanned] = useState(false);

  useEffect(() => {
    if (visible) {
      setCameraReady(false);
      setScanned(false);
    }
  }, [visible]);

  function handleBarcode(data: string) {
    const code = String(data || '').trim();
    if (!code || !cameraReady || scanned) return;
    setScanned(true);
    onCapture(code);
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
            {visible ? (
              <CameraView
                key="product-barcode-capture"
                style={styles.camera}
                facing="back"
                barcodeScannerSettings={{
                  barcodeTypes: ['ean13', 'ean8', 'upc_a', 'upc_e', 'code128', 'code39', 'qr'],
                }}
                onCameraReady={() => setCameraReady(true)}
                onBarcodeScanned={
                  cameraReady && !scanned
                    ? ({ data }) => handleBarcode(String(data || ''))
                    : undefined
                }
              />
            ) : null}
            {visible && !scanned ? <ScannerOverlay /> : null}
          </View>
        )}

        <View style={styles.footer}>
          <Text style={styles.footerText}>
            O código lido será preenchido automaticamente no formulário do produto.
          </Text>
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
  permissionBtn: {
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  permissionBtnText: { fontWeight: '700', color: brand.dark },
  cameraWrap: {
    flex: 1,
    margin: 16,
    minHeight: 280,
    borderRadius: 16,
    backgroundColor: '#000',
    overflow: 'hidden',
  },
  camera: { flex: 1, width: '100%' },
  viewfinder: {
    position: 'absolute',
    top: '50%',
    left: '11%',
    right: '11%',
    height: VIEWFINDER_HEIGHT,
    marginTop: -VIEWFINDER_HEIGHT / 2,
  },
  corner: {
    position: 'absolute',
    width: 28,
    height: 28,
    borderColor: SCANNER_RED,
  },
  cornerTopLeft: { top: -1, left: -1, borderTopWidth: 4, borderLeftWidth: 4 },
  cornerTopRight: { top: -1, right: -1, borderTopWidth: 4, borderRightWidth: 4 },
  cornerBottomLeft: { bottom: -1, left: -1, borderBottomWidth: 4, borderLeftWidth: 4 },
  cornerBottomRight: { bottom: -1, right: -1, borderBottomWidth: 4, borderRightWidth: 4 },
  scanLine: {
    position: 'absolute',
    left: 0,
    right: 0,
    top: 0,
    height: 2,
    backgroundColor: SCANNER_RED,
  },
  scanHint: {
    position: 'absolute',
    left: 16,
    right: 16,
    bottom: 20,
    color: 'rgba(255, 255, 255, 0.92)',
    fontSize: 13,
    fontWeight: '600',
    textAlign: 'center',
    textShadowColor: 'rgba(0, 0, 0, 0.6)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 4,
  },
  footer: {
    backgroundColor: brand.white,
    padding: 16,
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
  },
  footerText: { color: brand.muted, fontSize: 13, lineHeight: 18, textAlign: 'center' },
});
