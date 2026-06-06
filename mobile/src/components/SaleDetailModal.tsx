import {
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { calcularIvaTotalItem, type SaleDetail } from '@/src/api/salesApi';
import type { ReversalRequest } from '@/src/api/reversalsApi';
import { brand, formatMt } from '@/src/theme/brand';

type Props = {
  visible: boolean;
  sale: SaleDetail | null;
  reversal?: ReversalRequest | null;
  onClose: () => void;
};

function formatarData(valor: string | null | undefined) {
  if (!valor) return '—';
  return new Date(valor).toLocaleString('pt-MZ');
}

function traduzirMetodoPagamento(metodo: string) {
  if (metodo === 'Dinheiro') return 'Dinheiro';
  if (metodo === 'Transferência') return 'Transferência';
  return metodo;
}

function formatarIva(valor?: number) {
  const numero = Number(valor || 0);
  return `${Number.isFinite(numero) ? numero : 0}%`;
}

function traduzirEstadoReversao(status: string) {
  if (status === 'PENDING') return 'Pendente';
  if (status === 'APPROVED') return 'Aprovada';
  if (status === 'REJECTED') return 'Rejeitada';
  return status;
}

export default function SaleDetailModal({ visible, sale, reversal, onClose }: Props) {
  const totalIva = (sale?.itens ?? []).reduce(
    (acc, item) => acc + calcularIvaTotalItem(item),
    0,
  );

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>Detalhes da venda</Text>
            <Pressable onPress={onClose} style={styles.closeButton}>
              <Text style={styles.closeText}>Fechar</Text>
            </Pressable>
          </View>

          {sale ? (
            <ScrollView contentContainerStyle={styles.content}>
              {reversal ? (
                <View style={styles.reversalCard}>
                  <Text style={styles.reversalTitle}>Solicitação de reversão</Text>
                  <Text style={styles.infoLine}>
                    <Text style={styles.infoLabel}>Estado: </Text>
                    {traduzirEstadoReversao(reversal.status)}
                  </Text>
                  <Text style={styles.infoLine}>
                    <Text style={styles.infoLabel}>Solicitado por: </Text>
                    {reversal.requestedBy}
                  </Text>
                  <Text style={styles.infoLine}>
                    <Text style={styles.infoLabel}>Data do pedido: </Text>
                    {formatarData(reversal.requestedAt)}
                  </Text>
                  {reversal.approvedBy ? (
                    <Text style={styles.infoLine}>
                      <Text style={styles.infoLabel}>Decidido por: </Text>
                      {reversal.approvedBy}
                    </Text>
                  ) : null}
                  {reversal.decidedAt ? (
                    <Text style={styles.infoLine}>
                      <Text style={styles.infoLabel}>Data da decisão: </Text>
                      {formatarData(reversal.decidedAt)}
                    </Text>
                  ) : null}
                  {reversal.reason ? (
                    <Text style={styles.reasonLine}>
                      <Text style={styles.infoLabel}>Motivo: </Text>
                      {reversal.reason}
                    </Text>
                  ) : null}
                </View>
              ) : null}

              <View style={styles.infoCard}>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Data: </Text>
                  {formatarData(sale.data || sale.createdAt)}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Cliente: </Text>
                  {sale.cliente}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Caixa: </Text>
                  {sale.caixa || '—'}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Operador: </Text>
                  {sale.operador || '—'}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Pagamento: </Text>
                  {traduzirMetodoPagamento(sale.metodoPagamento)}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Referência: </Text>
                  {sale.referencia || sale.id}
                </Text>
              </View>

              <View style={styles.table}>
                <View style={styles.tableHeader}>
                  <Text style={[styles.cellHeader, styles.itemCol]}>Item</Text>
                  <Text style={[styles.cellHeader, styles.qtyCol]}>Qtd</Text>
                  <Text style={[styles.cellHeader, styles.ivaCol]}>IVA</Text>
                  <Text style={[styles.cellHeader, styles.totalCol]}>Total</Text>
                </View>
                {sale.itens.map((item, index) => (
                  <View key={`${item.produtoId ?? item.nome}-${index}`} style={styles.tableRow}>
                    <Text style={[styles.cell, styles.itemCol]} numberOfLines={2}>
                      {item.nome}
                    </Text>
                    <Text style={[styles.cell, styles.qtyCol]}>{item.quantidade}</Text>
                    <Text style={[styles.cell, styles.ivaCol]}>{formatarIva(item.ivaPercentual)}</Text>
                    <Text style={[styles.cell, styles.totalCol]}>{formatMt(item.subtotal)}</Text>
                  </View>
                ))}
              </View>

              <View style={styles.infoCard}>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Subtotal: </Text>
                  {formatMt(sale.subtotal || sale.total)}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Total IVA: </Text>
                  {formatMt(totalIva)}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Desconto: </Text>
                  - {formatMt(sale.descontoAplicado || 0)}
                </Text>
                <Text style={[styles.infoLine, styles.totalLine]}>
                  <Text style={styles.infoLabel}>Total: </Text>
                  {formatMt(sale.total)}
                </Text>
              </View>
            </ScrollView>
          ) : null}
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
    maxHeight: '88%',
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
  title: {
    fontSize: 16,
    fontWeight: '700',
    color: brand.dark,
  },
  closeButton: {
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  closeText: {
    color: brand.danger,
    fontWeight: '700',
  },
  content: {
    padding: 16,
    gap: 12,
  },
  reversalCard: {
    backgroundColor: '#FFFBEB',
    borderWidth: 1,
    borderColor: brand.gold,
    borderRadius: 12,
    padding: 14,
    gap: 6,
  },
  reversalTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: brand.dark,
    marginBottom: 2,
  },
  reasonLine: {
    fontSize: 14,
    color: brand.dark,
    marginTop: 4,
  },
  infoCard: {
    backgroundColor: brand.background,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 12,
    padding: 14,
    gap: 6,
  },
  infoLine: {
    fontSize: 14,
    color: brand.dark,
  },
  infoLabel: {
    fontWeight: '700',
  },
  totalLine: {
    marginTop: 4,
    fontWeight: '700',
  },
  table: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 12,
    overflow: 'hidden',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: brand.background,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  tableRow: {
    flexDirection: 'row',
    borderTopWidth: 1,
    borderTopColor: brand.border,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  cellHeader: {
    fontSize: 11,
    fontWeight: '700',
    color: brand.muted,
    textTransform: 'uppercase',
  },
  cell: {
    fontSize: 12,
    color: brand.dark,
  },
  itemCol: { flex: 2.2 },
  qtyCol: { flex: 0.6, textAlign: 'center' },
  ivaCol: { flex: 0.7, textAlign: 'center' },
  totalCol: { flex: 1.1, textAlign: 'right', fontWeight: '700' },
});
