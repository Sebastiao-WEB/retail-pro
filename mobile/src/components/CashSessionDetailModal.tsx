import {
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import type { CashSession } from '@/src/api/cashSessionsApi';
import { brand, formatMt } from '@/src/theme/brand';

type Props = {
  visible: boolean;
  session: CashSession | null;
  onClose: () => void;
};

function formatarData(valor: string | null | undefined) {
  if (!valor) return '—';
  const data = new Date(valor);
  if (Number.isNaN(data.getTime())) return '—';
  return data.toLocaleString('pt-MZ', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function valorSnapshot(session: CashSession, campo: keyof NonNullable<CashSession['reportSnapshot']>, fallback = 0) {
  const snapshot = session.reportSnapshot;
  const valor = snapshot?.[campo];
  if (valor === undefined || valor === null) return fallback;
  return Number(valor);
}

function linha(label: string, valor: string) {
  return (
    <Text style={styles.infoLine}>
      <Text style={styles.infoLabel}>{label}: </Text>
      {valor}
    </Text>
  );
}

export default function CashSessionDetailModal({ visible, session, onClose }: Props) {
  if (!session) {
    return null;
  }

  const snapshot = session.reportSnapshot ?? {};
  const diferenca = valorSnapshot(session, 'diferenca', session.differenceAmount ?? 0);
  const diferencaTransferencias = valorSnapshot(session, 'diferencaTransferencias', 0);

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>
              Fecho — {session.registerName || snapshot.caixa || 'Caixa'}
            </Text>
            <Pressable onPress={onClose} style={styles.closeButton}>
              <Text style={styles.closeText}>Fechar</Text>
            </Pressable>
          </View>

          <ScrollView contentContainerStyle={styles.content}>
            {linha('Operador', session.operatorName || snapshot.utilizador || '—')}
            {linha('Abertura', formatarData(session.openedAt || snapshot.aberturaEm))}
            {linha('Fecho', formatarData(session.closedAt || snapshot.fechadoEm))}
            {linha(
              'Fundo inicial',
              formatMt(valorSnapshot(session, 'fundoInicial', session.openingBalance)),
            )}
            {linha('Total vendido', formatMt(valorSnapshot(session, 'totalVendido')))}
            {linha('Transacções', String(snapshot.totalTransacoes ?? '—'))}
            {linha('Vendas em dinheiro', formatMt(valorSnapshot(session, 'vendasDinheiro')))}
            {linha('Vendas por transferência', formatMt(valorSnapshot(session, 'vendasTransferencia')))}
            {linha('Dinheiro esperado', formatMt(valorSnapshot(session, 'dinheiroEsperado')))}
            {linha(
              'Dinheiro contado',
              formatMt(valorSnapshot(session, 'dinheiroReal', session.closingBalance ?? 0)),
            )}
            <Text style={styles.infoLine}>
              <Text style={styles.infoLabel}>Diferença: </Text>
              <Text style={diferenca === 0 ? styles.okValue : styles.warnValue}>
                {formatMt(diferenca)}
              </Text>
            </Text>
            {linha(
              'Transferências esperadas',
              formatMt(
                valorSnapshot(
                  session,
                  'transferenciasEsperadas',
                  valorSnapshot(session, 'vendasTransferencia'),
                ),
              ),
            )}
            {linha('Transferências contadas', formatMt(valorSnapshot(session, 'transferenciasReais')))}
            <Text style={styles.infoLine}>
              <Text style={styles.infoLabel}>Diferença transferências: </Text>
              <Text style={diferencaTransferencias === 0 ? styles.okValue : styles.warnValue}>
                {formatMt(diferencaTransferencias)}
              </Text>
            </Text>
            {snapshot.justificativaDiferenca || session.note
              ? linha('Justificação', snapshot.justificativaDiferenca || session.note || '—')
              : null}
          </ScrollView>
        </View>
      </View>
    </Modal>
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
  title: { flex: 1, fontSize: 16, fontWeight: '700', color: brand.dark, marginRight: 8 },
  closeButton: { paddingHorizontal: 8, paddingVertical: 4 },
  closeText: { color: brand.danger, fontWeight: '700' },
  content: { padding: 16, gap: 8 },
  infoLine: { fontSize: 14, color: brand.dark },
  infoLabel: { fontWeight: '700' },
  okValue: { color: '#16a34a', fontWeight: '700' },
  warnValue: { color: '#b45309', fontWeight: '700' },
});
