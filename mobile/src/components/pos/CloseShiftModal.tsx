import { useEffect, useState } from 'react';
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
import type { ShiftMetrics } from '../../store/shiftSalesStore';
import { brand, formatMt } from '../../theme/brand';
import { roundMoney } from '../../utils/saleShift';

type Props = {
  visible: boolean;
  loading?: boolean;
  metrics: ShiftMetrics;
  operatorName: string;
  registerName: string;
  openedAt: string;
  onConfirm: (payload: {
    closingBalance: number;
    note: string;
    reportSnapshot: Record<string, unknown>;
  }) => void;
  onClose: () => void;
};

export default function CloseShiftModal({
  visible,
  loading = false,
  metrics,
  operatorName,
  registerName,
  openedAt,
  onConfirm,
  onClose,
}: Props) {
  const [cashReal, setCashReal] = useState('');
  const [transferReal, setTransferReal] = useState('');
  const [note, setNote] = useState('');

  useEffect(() => {
    if (!visible) return;
    setCashReal(String(metrics.dinheiroEsperado));
    setTransferReal(String(metrics.transferenciasEsperadas));
    setNote('');
  }, [visible, metrics]);

  const cashExpected = metrics.dinheiroEsperado;
  const transferExpected = metrics.transferenciasEsperadas;
  const cashCounted = roundMoney(Number(String(cashReal).replace(',', '.')) || 0);
  const transferCounted = roundMoney(Number(String(transferReal).replace(',', '.')) || 0);
  const cashDiff = roundMoney(cashCounted - cashExpected);
  const transferDiff = roundMoney(transferCounted - transferExpected);
  const hasDiff = cashDiff !== 0 || transferDiff !== 0;

  function handleConfirm() {
    if (hasDiff && !note.trim()) return;
    const closedAt = new Date().toISOString();
    onConfirm({
      closingBalance: cashCounted,
      note: note.trim(),
      reportSnapshot: {
        utilizador: operatorName,
        caixa: registerName,
        aberturaEm: openedAt,
        fechadoEm: closedAt,
        fundoInicial: roundMoney(metrics.dinheiroEsperado - metrics.vendasDinheiro),
        totalVendido: metrics.totalVendido,
        totalTransacoes: metrics.totalTransacoes,
        ticketMedio: metrics.ticketMedio,
        vendasDinheiro: metrics.vendasDinheiro,
        vendasTransferencia: metrics.vendasTransferencia,
        dinheiroEsperado: cashExpected,
        dinheiroReal: cashCounted,
        diferenca: cashDiff,
        transferenciasEsperadas: transferExpected,
        transferenciasReais: transferCounted,
        diferencaTransferencias: transferDiff,
        justificativaDiferenca: note.trim(),
      },
    });
  }

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.card}>
          <Text style={styles.title}>Fechar caixa</Text>
          <ScrollView contentContainerStyle={styles.scroll}>
            <View style={styles.metricRow}>
              <Text style={styles.metricLabel}>Total vendido</Text>
              <Text style={styles.metricValue}>{formatMt(metrics.totalVendido)}</Text>
            </View>
            <View style={styles.metricRow}>
              <Text style={styles.metricLabel}>Transacções</Text>
              <Text style={styles.metricValue}>{metrics.totalTransacoes}</Text>
            </View>
            <View style={styles.metricRow}>
              <Text style={styles.metricLabel}>Dinheiro esperado</Text>
              <Text style={styles.metricValue}>{formatMt(cashExpected)}</Text>
            </View>
            <View style={styles.metricRow}>
              <Text style={styles.metricLabel}>Transferências esperadas</Text>
              <Text style={styles.metricValue}>{formatMt(transferExpected)}</Text>
            </View>

            <Text style={styles.label}>Dinheiro real em caixa</Text>
            <TextInput value={cashReal} onChangeText={setCashReal} keyboardType="decimal-pad" style={styles.input} />
            {cashDiff !== 0 ? (
              <Text style={[styles.diff, cashDiff < 0 ? styles.diffNeg : styles.diffPos]}>
                Diferença: {formatMt(cashDiff)}
              </Text>
            ) : null}

            <Text style={styles.label}>Transferências reais</Text>
            <TextInput
              value={transferReal}
              onChangeText={setTransferReal}
              keyboardType="decimal-pad"
              style={styles.input}
            />
            {transferDiff !== 0 ? (
              <Text style={[styles.diff, transferDiff < 0 ? styles.diffNeg : styles.diffPos]}>
                Diferença: {formatMt(transferDiff)}
              </Text>
            ) : null}

            {hasDiff ? (
              <>
                <Text style={styles.label}>Justificativa da diferença</Text>
                <TextInput
                  value={note}
                  onChangeText={setNote}
                  multiline
                  style={[styles.input, styles.textArea]}
                  placeholder="Explique a diferença encontrada"
                />
              </>
            ) : null}
          </ScrollView>

          <View style={styles.actions}>
            <Pressable style={[styles.btn, styles.cancel]} onPress={onClose} disabled={loading}>
              <Text style={styles.cancelText}>Cancelar</Text>
            </Pressable>
            <Pressable
              style={[styles.btn, styles.confirm, (hasDiff && !note.trim()) && styles.btnDisabled]}
              onPress={handleConfirm}
              disabled={loading || (hasDiff && !note.trim())}
            >
              {loading ? (
                <ActivityIndicator color={brand.dark} />
              ) : (
                <Text style={styles.confirmText}>Fechar turno</Text>
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
  scroll: { gap: 8, paddingBottom: 12 },
  metricRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  metricLabel: { color: brand.muted, fontSize: 13 },
  metricValue: { fontWeight: '700', color: brand.dark },
  label: { fontSize: 12, fontWeight: '600', color: brand.muted, marginTop: 6 },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
  },
  textArea: { minHeight: 72, textAlignVertical: 'top' },
  diff: { fontSize: 12, fontWeight: '600' },
  diffNeg: { color: brand.danger },
  diffPos: { color: brand.success },
  actions: { flexDirection: 'row', gap: 10, marginTop: 8 },
  btn: { flex: 1, borderRadius: 10, paddingVertical: 14, alignItems: 'center' },
  cancel: { borderWidth: 1, borderColor: brand.border },
  confirm: { backgroundColor: brand.gold },
  btnDisabled: { opacity: 0.5 },
  cancelText: { color: brand.muted, fontWeight: '700' },
  confirmText: { color: brand.dark, fontWeight: '700' },
});
