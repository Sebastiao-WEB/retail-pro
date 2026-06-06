import i18n from '../i18n/index.js';

/** Nome canónico do cliente geral na base de dados (não traduzir para comparações API). */
export const GENERAL_CLIENT_CANONICAL = 'Cliente Geral';

export function t(key, params = {}) {
  return i18n.global.t(key, params);
}

export function generalClientLabel() {
  return t('common.generalClient');
}

export function isGeneralClient(nome) {
  return nome === GENERAL_CLIENT_CANONICAL || nome === generalClientLabel();
}

export function buildReceiptLabels() {
  return {
    nuit: t('pos.receipt.labels.nuit'),
    ref: t('pos.receipt.labels.ref'),
    date: t('pos.receipt.labels.date'),
    client: t('pos.receipt.labels.client'),
    payment: t('pos.receipt.labels.payment'),
    item: t('pos.receipt.labels.item'),
    qtyIvaTotal: t('pos.receipt.labels.qtyIvaTotal'),
    qtyIvaIvaTotal: t('pos.receipt.labels.qtyIvaIvaTotal'),
    subtotal: t('pos.receipt.labels.subtotal'),
    totalIva: t('pos.receipt.labels.totalIva'),
    discount: t('pos.receipt.labels.discount'),
    total: t('pos.receipt.labels.total'),
    amountPaid: t('pos.receipt.labels.amountPaid'),
    change: t('pos.receipt.labels.change'),
  };
}

export function buildClosingReportLabels() {
  return {
    register: t('pos.closingReport.labels.register'),
    operator: t('pos.closingReport.labels.operator'),
    opening: t('pos.closingReport.labels.opening'),
    closing: t('pos.closingReport.labels.closing'),
    initialFund: t('pos.closingReport.labels.initialFund'),
    totalSold: t('pos.closingReport.labels.totalSold'),
    transactions: t('pos.closingReport.labels.transactions'),
    averageTicket: t('pos.closingReport.labels.averageTicket'),
    cashSales: t('pos.closingReport.labels.cashSales'),
    transferSales: t('pos.closingReport.labels.transferSales'),
    expectedCash: t('pos.closingReport.labels.expectedCash'),
    expectedTransfers: t('pos.closingReport.labels.expectedTransfers'),
    actualCash: t('pos.closingReport.labels.actualCash'),
    actualMobileWallets: t('pos.closingReport.labels.actualMobileWallets'),
    difference: t('pos.closingReport.labels.difference'),
    transferDifference: t('pos.closingReport.labels.transferDifference'),
    justification: t('pos.closingReport.labels.justification'),
  };
}
