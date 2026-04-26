<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import BotaoBase from "../../components/BotaoBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import { useProdutoStore } from "../../store/useProdutoStore";
import { useCarrinhoStore } from "../../store/useCarrinhoStore";
import { useClienteStore } from "../../store/useClienteStore";
import { useVendaStore } from "../../store/useVendaStore";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";
import { useSessaoStore } from "../../store/useSessaoStore";
import { calcularDiferencaProjetada } from "../../services/caixaMetricas";
import { temApiConfigurada } from "../../api";

const produtoStore = useProdutoStore();
const carrinhoStore = useCarrinhoStore();
const clienteStore = useClienteStore();
const vendaStore = useVendaStore();
const configuracaoStore = useConfiguracaoStore();
const sessaoStore = useSessaoStore();
const route = useRoute();

const pesquisa = ref("");
const cliente = ref("Cliente Geral");
const mensagem = ref("");
const tipoMensagem = ref("sucesso");
const descontoAtivo = ref(false);
const valorPagoInteiro = ref("0");
const valorPagoDecimal = ref("00");
const modalImpressaoAberto = ref(false);
const vendaPendente = ref(null);
const imprimindoAgora = ref(false);
const modalAberturaCaixa = ref(false);
const modalFechoCaixa = ref(false);
const fundoInicialInput = ref(1000);
const dinheiroRealFecho = ref(null);
const justificativaDiferenca = ref("");
const erroFecho = ref("");
const erroFinalizacao = ref("");
const modalSolicitarReversaoAberto = ref(false);
const vendaParaReversao = ref(null);
const motivoReversao = ref("");
const listaPreVisualizacaoRef = ref(null);
const menuPosAtivo = computed(() => (route.query?.secao === "caixa" ? "caixa" : "venda"));
const toastAberto = ref(false);
const toastMensagem = ref("");
const toastTipo = ref("erro");
let temporizadorToast = null;

const pesquisaAtiva = computed(() => pesquisa.value.trim().length > 0);
const produtosFiltrados = computed(() => {
  if (!pesquisaAtiva.value) return [];
  return produtoStore.produtos
    .filter((produto) => `${produto.nome} ${produto.codigoBarras}`.toLowerCase().includes(pesquisa.value.toLowerCase()))
    .slice(0, 5);
});

const clientesDisponiveis = computed(() => clienteStore.clientes);
const clientesParaSelect = computed(() => {
  const lista = [{ id: 0, nome: "Cliente Geral" }, ...clientesDisponiveis.value];
  const nomes = new Set();
  return lista.filter((item) => {
    if (nomes.has(item.nome)) return false;
    nomes.add(item.nome);
    return true;
  });
});
const podeInformarPagamento = computed(() => carrinhoStore.itens.length > 0);
const itensCarrinhoOrdenados = computed(() =>
  [...carrinhoStore.itens].sort((a, b) => Number(b.ordemAdicao || 0) - Number(a.ordemAdicao || 0))
);
const descontoAplicado = computed(() => carrinhoStore.valorDesconto);
const valorPagoNumerico = computed(() => {
  const inteiro = Number.parseInt((valorPagoInteiro.value || "0").replace(/\D/g, ""), 10) || 0;
  const decimalNormalizado = (valorPagoDecimal.value || "00").replace(/\D/g, "").slice(0, 2).padEnd(2, "0");
  const decimal = Number.parseInt(decimalNormalizado, 10) || 0;
  return inteiro + decimal / 100;
});
const troco = computed(() => Math.max(0, valorPagoNumerico.value - carrinhoStore.total));
const falta = computed(() => Math.max(0, carrinhoStore.total - valorPagoNumerico.value));
const vendasTurno = computed(() => {
  if (!sessaoStore.aberturaEm) return [];
  const inicio = new Date(sessaoStore.aberturaEm).getTime();
  return vendaStore.vendas.filter((venda) => new Date(venda.data).getTime() >= inicio);
});
const ultimasVendasTurno = computed(() =>
  [...vendasTurno.value].sort((a, b) => new Date(b.data).getTime() - new Date(a.data).getTime()).slice(0, 5)
);
const solicitacoesPendentesPorVenda = computed(() => {
  const mapa = new Map();
  vendaStore.solicitacoesPendentes.forEach((item) => {
    mapa.set(item.vendaId, item);
  });
  return mapa;
});
const totalVendidoTurno = computed(() => vendasTurno.value.reduce((acc, venda) => acc + venda.total, 0));
const totalTransacoesTurno = computed(() => vendasTurno.value.length);
const ticketMedioTurno = computed(() => (totalTransacoesTurno.value ? totalVendidoTurno.value / totalTransacoesTurno.value : 0));
const totalDinheiroTurno = computed(() =>
  vendasTurno.value
    .filter((venda) => venda.metodoPagamento === "Dinheiro")
    .reduce((acc, venda) => acc + venda.total, 0)
);
const totalTransferenciaTurno = computed(() =>
  vendasTurno.value
    .filter((venda) => venda.metodoPagamento === "Transferência")
    .reduce((acc, venda) => acc + venda.total, 0)
);
const dinheiroEsperadoFecho = computed(() => Number(sessaoStore.fundoInicial || 0) + totalDinheiroTurno.value);
const diferencaFecho = computed(() =>
  calcularDiferencaProjetada({
    dinheiroReal: dinheiroRealFecho.value,
    dinheiroEsperado: dinheiroEsperadoFecho.value,
  })
);
const diferencaFechoTemValor = computed(() => diferencaFecho.value !== null);
const origemStockVenda = computed(() => ({
  id: sessaoStore.sourceLocationId,
  codigo: sessaoStore.sourceLocationCodigo || "",
  nome: sessaoStore.sourceLocationNome || "",
}));

function formatarMT(valor) {
  return `${new Intl.NumberFormat("pt-MZ", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(valor)} MT`;
}

function formatarIva(valor) {
  const numero = Number(valor || 0);
  return `${Number.isFinite(numero) ? numero : 0}%`;
}

function formatarData(valor) {
  return new Date(valor).toLocaleString("pt-MZ");
}

function quantidadeNoCarrinho(produtoId) {
  return carrinhoStore.itens.find((item) => item.produtoId === produtoId)?.quantidade || 0;
}

function podeAdicionarProduto(produto) {
  return sessaoStore.turnoAberto && produto.stock > 0 && quantidadeNoCarrinho(produto.id) < produto.stock;
}

function mostrarErroStock(texto = "Stock insuficiente para esta quantidade.") {
  tipoMensagem.value = "erro";
  mensagem.value = texto;
  setTimeout(() => {
    if (tipoMensagem.value === "erro") mensagem.value = "";
  }, 2500);
}

function mostrarToast(texto, tipo = "erro") {
  toastMensagem.value = texto;
  toastTipo.value = tipo;
  toastAberto.value = true;
  if (temporizadorToast) clearTimeout(temporizadorToast);
  temporizadorToast = setTimeout(() => {
    toastAberto.value = false;
  }, 2200);
}

async function adicionarAoCarrinho(produto) {
  if (!podeAdicionarProduto(produto)) {
    mostrarErroStock("Não é possível adicionar acima do stock disponível.");
    return;
  }
  carrinhoStore.adicionarProduto(produto);
  await nextTick();
  if (listaPreVisualizacaoRef.value) {
    listaPreVisualizacaoRef.value.scrollTop = 0;
  }
}

function atualizarQuantidade(produtoId, valor) {
  const quantidade = Number.parseInt(valor, 10);
  const produto = produtoStore.produtos.find((reg) => reg.id === produtoId);
  const quantidadeFinal = Number.isNaN(quantidade) ? 1 : quantidade;
  const limiteStock = produto?.stock ?? quantidadeFinal;
  if (quantidadeFinal > limiteStock) {
    carrinhoStore.definirQuantidade(produtoId, limiteStock);
    mostrarErroStock("Quantidade ajustada ao limite de stock.");
    return;
  }
  carrinhoStore.definirQuantidade(produtoId, quantidadeFinal);
}

function atualizarValorPagoInteiro(valor) {
  const texto = String(valor || "").trim();
  const temSeparador = texto.includes(",") || texto.includes(".");
  if (temSeparador) {
    const partes = texto.split(/[,.]/);
    const parteInteira = (partes[0] || "0").replace(/\D/g, "");
    const parteDecimal = (partes[1] || "").replace(/\D/g, "").slice(0, 2);
    valorPagoInteiro.value = parteInteira || "0";
    if (parteDecimal) valorPagoDecimal.value = parteDecimal.padEnd(2, "0");
    return;
  }
  valorPagoInteiro.value = texto.replace(/\D/g, "") || "0";
}

function atualizarValorPagoDecimal(valor) {
  valorPagoDecimal.value = String(valor || "").replace(/\D/g, "").slice(0, 2).padEnd(2, "0");
}

function atualizarDesconto(valor) {
  carrinhoStore.definirDesconto({ tipo: carrinhoStore.descontoTipo, valor: Number(valor || 0) });
}

function alternarDesconto() {
  descontoAtivo.value = !descontoAtivo.value;
  if (!descontoAtivo.value) {
    carrinhoStore.definirDesconto({ tipo: carrinhoStore.descontoTipo, valor: 0 });
  }
}

function escaparHtml(valor) {
  return String(valor ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function gerarHtmlTalao(venda) {
  const largura = configuracaoStore.larguraTalao === "58mm" ? "58mm" : "80mm";
  const linhasItens = venda.itens
    .map(
      (item) => `
        <tr>
          <td>${escaparHtml(item.nome)}</td>
          <td style="text-align:center;">${item.quantidade}</td>
          <td style="text-align:center;">${formatarIva(item.ivaPercentual)}</td>
          <td style="text-align:right;">${formatarMT(item.subtotal)}</td>
        </tr>
      `
    )
    .join("");

  const nomeEmpresa = escaparHtml(configuracaoStore.nomeEmpresa || "RetailPro POS");
  const nifEmpresa = escaparHtml(configuracaoStore.nif || "");
  const enderecoEmpresa = escaparHtml(configuracaoStore.endereco || "");
  const telefoneEmpresa = escaparHtml(configuracaoStore.telefone || "");
  const rodapeEmpresa = escaparHtml(configuracaoStore.rodapeFacturas || "Obrigado pela preferência.");

  return `
    <!doctype html>
    <html lang="pt">
      <head>
        <meta charset="UTF-8" />
        <title>Talão</title>
        <style>
          @page { size: ${largura} auto; margin: 4mm; }
          body { font-family: Arial, sans-serif; width: ${largura}; margin: 0 auto; color: #111; font-size: 12px; }
          .center { text-align: center; }
          .title { font-size: 18px; font-weight: 700; margin: 4px 0; }
          .muted { color: #555; font-size: 11px; }
          .sep { border-top: 1px dashed #444; margin: 8px 0; }
          table { width: 100%; border-collapse: collapse; }
          th, td { padding: 4px 0; font-size: 11px; }
          th { text-transform: uppercase; font-size: 10px; color: #666; border-bottom: 1px solid #ddd; }
          .tot { font-weight: 700; font-size: 14px; }
          .foot { margin-top: 10px; font-size: 10px; color: #555; text-align: center; }
        </style>
      </head>
      <body>
        <div class="center">
          <div class="title">${nomeEmpresa}</div>
          <div class="muted">Talão de Venda</div>
          <div class="muted">NUIT: ${nifEmpresa}</div>
          <div class="muted">${enderecoEmpresa}</div>
          <div class="muted">${telefoneEmpresa}</div>
          <div class="muted">Data: ${escaparHtml(new Date(venda.data).toLocaleString("pt-MZ"))}</div>
        </div>
        <div class="sep"></div>
        <div><strong>Cliente:</strong> ${escaparHtml(venda.cliente)}</div>
        <div><strong>Pagamento:</strong> ${escaparHtml(venda.metodoPagamento)}</div>
        <div class="sep"></div>
        <table>
          <thead>
            <tr><th>Item</th><th>Qtd</th><th>IVA</th><th>Total</th></tr>
          </thead>
          <tbody>
            ${linhasItens}
          </tbody>
        </table>
        <div class="sep"></div>
        <table>
          <tbody>
            <tr><td>Subtotal</td><td style="text-align:right">${formatarMT(venda.subtotal ?? venda.total)}</td></tr>
            <tr><td>Desconto</td><td style="text-align:right">- ${formatarMT(venda.descontoAplicado || 0)}</td></tr>
            <tr><td>Total</td><td style="text-align:right" class="tot">${formatarMT(venda.total)}</td></tr>
            <tr><td>Valor Pago</td><td style="text-align:right">${venda.metodoPagamento === "Dinheiro" ? formatarMT(venda.valorPago) : "--"}</td></tr>
            <tr><td>Troco</td><td style="text-align:right">${venda.metodoPagamento === "Dinheiro" ? formatarMT(venda.troco) : "--"}</td></tr>
          </tbody>
        </table>
        <div class="sep"></div>
        <div class="foot">${rodapeEmpresa}</div>
      </body>
    </html>
  `;
}

function validarOrigemStock() {
  if (!temApiConfigurada()) return true;
  if (origemStockVenda.value.id) return true;
  const caixaAtual = sessaoStore.caixaAtribuido || "caixa atual";
  erroFinalizacao.value = `A localização de stock não está vinculada ao ${caixaAtual}. Defina esta ligação no backend antes de concluir vendas.`;
  return false;
}

function finalizarVenda() {
  if (!sessaoStore.turnoAberto) {
    mostrarToast("Abra o caixa antes de finalizar vendas.", "erro");
    return;
  }
  if (!carrinhoStore.itens.length) return;
  erroFinalizacao.value = "";
  if (!validarOrigemStock()) {
    mostrarToast("Localização de stock não configurada para o caixa.", "erro");
    return;
  }
  vendaPendente.value = {
    cliente: cliente.value,
    itens: carrinhoStore.itens.map((item) => ({ ...item })),
    caixa: sessaoStore.caixaAtribuido,
    registerId: sessaoStore.registerId,
    registerCodigo: sessaoStore.registerCodigo,
    sourceLocationId: origemStockVenda.value.id,
    sourceLocationCodigo: origemStockVenda.value.codigo,
    sourceLocationNome: origemStockVenda.value.nome,
    operador: sessaoStore.utilizador,
    turnoAbertura: sessaoStore.aberturaEm,
    subtotal: carrinhoStore.subtotal,
    descontoTipo: carrinhoStore.descontoTipo,
    descontoValor: carrinhoStore.descontoValor,
    descontoAplicado: carrinhoStore.valorDesconto,
    total: carrinhoStore.total,
    data: new Date().toISOString(),
    metodoPagamento: carrinhoStore.metodoPagamento,
    valorPago: valorPagoNumerico.value,
    troco: troco.value,
  };
  modalImpressaoAberto.value = true;
}

function limparCarrinhoAtual() {
  carrinhoStore.limparCarrinho();
  valorPagoInteiro.value = "0";
  valorPagoDecimal.value = "00";
  descontoAtivo.value = false;
  mensagem.value = "";
}

async function concluirVenda(opcoes = { imprimir: true }) {
  if (!vendaPendente.value) return;
  if (!validarOrigemStock()) return;
  if (carrinhoStore.metodoPagamento === "Dinheiro" && valorPagoNumerico.value < carrinhoStore.total) {
    erroFinalizacao.value = "Valor pago insuficiente para concluir a venda.";
    return;
  }
  erroFinalizacao.value = "";

  const venda = {
    ...vendaPendente.value,
    cliente: cliente.value,
    metodoPagamento: carrinhoStore.metodoPagamento,
    valorPago: valorPagoNumerico.value,
    troco: troco.value,
    registerId: sessaoStore.registerId,
    registerCodigo: sessaoStore.registerCodigo,
    sourceLocationId: origemStockVenda.value.id,
    sourceLocationCodigo: origemStockVenda.value.codigo,
    sourceLocationNome: origemStockVenda.value.nome,
    register_id: sessaoStore.registerId,
    source_location_id: origemStockVenda.value.id,
  };
  if (opcoes.imprimir) {
    if (!window.api?.imprimirTalao) {
      tipoMensagem.value = "erro";
      mensagem.value = "API de impressão não disponível no desktop. Reinicie o Electron para recarregar o preload.";
      return;
    }
    if (!configuracaoStore.impressoraPadrao) {
      tipoMensagem.value = "erro";
      mensagem.value = "Defina a impressora padrão em Configurações para imprimir.";
      return;
    }
    imprimindoAgora.value = true;
    const resultado = await window.api.imprimirTalao({
      html: gerarHtmlTalao(venda),
      deviceName: configuracaoStore.impressoraPadrao,
      copies: Math.max(1, Number(configuracaoStore.copiasImpressao || 1)),
      larguraTalao: configuracaoStore.larguraTalao || "80mm",
      corteAutomatico: !!configuracaoStore.corteAutomatico,
    });
    imprimindoAgora.value = false;
    if (!resultado?.ok) {
      tipoMensagem.value = "erro";
      mensagem.value = resultado?.error || "Falha ao imprimir talão.";
      return;
    }
  }

  await vendaStore.registarVenda(venda);
  produtoStore.aplicarVenda(venda.itens);
  carrinhoStore.limparCarrinho();
  valorPagoInteiro.value = "0";
  valorPagoDecimal.value = "00";
  modalImpressaoAberto.value = false;

  tipoMensagem.value = "sucesso";
  mensagem.value = opcoes.imprimir
    ? `Venda realizada e enviada para impressão em ${configuracaoStore.impressoraPadrao}.`
    : "Venda realizada com sucesso.";
  vendaPendente.value = null;
  setTimeout(() => (mensagem.value = ""), 2500);
}

async function reimprimirVenda(venda) {
  if (!window.api?.imprimirTalao) {
    tipoMensagem.value = "erro";
    mensagem.value = "Reimpressão disponível apenas na versão desktop (Electron).";
    return;
  }
  if (!configuracaoStore.impressoraPadrao) {
    tipoMensagem.value = "erro";
    mensagem.value = "Defina a impressora padrão em Configurações para reimprimir.";
    return;
  }
  imprimindoAgora.value = true;
  const resultado = await window.api.imprimirTalao({
    html: gerarHtmlTalao(venda),
    deviceName: configuracaoStore.impressoraPadrao,
    copies: 1,
    larguraTalao: configuracaoStore.larguraTalao || "80mm",
    corteAutomatico: !!configuracaoStore.corteAutomatico,
  });
  imprimindoAgora.value = false;
  if (!resultado?.ok) {
    tipoMensagem.value = "erro";
    mensagem.value = resultado?.error || "Falha ao reimprimir talão.";
    return;
  }
  tipoMensagem.value = "sucesso";
  mensagem.value = `Recibo reenviado para impressão em ${configuracaoStore.impressoraPadrao}.`;
}

function abrirSolicitacaoReversao(venda) {
  if (venda.estado === "Revertida") {
    tipoMensagem.value = "erro";
    mensagem.value = "Venda já foi revertida.";
    return;
  }
  if (solicitacoesPendentesPorVenda.value.has(venda.id)) {
    tipoMensagem.value = "erro";
    mensagem.value = "Já existe solicitação de reversão pendente para esta venda.";
    return;
  }
  vendaParaReversao.value = venda;
  motivoReversao.value = "";
  modalSolicitarReversaoAberto.value = true;
}

async function confirmarSolicitacaoReversao() {
  if (!vendaParaReversao.value) return;
  const venda = vendaParaReversao.value;
  const resultado = await vendaStore.solicitarReversao({
    vendaId: venda.id,
    referencia: venda.referencia || String(venda.id),
    solicitadoPor: sessaoStore.utilizador || "Operador",
    motivo: motivoReversao.value.trim(),
  });
  if (!resultado.ok) {
    tipoMensagem.value = "erro";
    mensagem.value = resultado.erro || "Não foi possível solicitar reversão.";
    return;
  }
  modalSolicitarReversaoAberto.value = false;
  vendaParaReversao.value = null;
  tipoMensagem.value = "sucesso";
  mensagem.value = "Solicitação de reversão enviada ao gerente para aprovação.";
}

onMounted(() => {
  sessaoStore.hidratar();
  configuracaoStore.hidratar();
  if (!sessaoStore.turnoAberto) {
    modalAberturaCaixa.value = true;
  }
});

onBeforeUnmount(() => {
  if (temporizadorToast) clearTimeout(temporizadorToast);
});

function abrirTurnoCaixa() {
  const fundo = Number(fundoInicialInput.value || 0);
  sessaoStore.abrirTurno({ fundoInicial: fundo });
  modalAberturaCaixa.value = false;
  tipoMensagem.value = "sucesso";
  mensagem.value = `Caixa aberto com fundo inicial de ${formatarMT(fundo)}.`;
}

function abrirFechoCaixa() {
  dinheiroRealFecho.value = dinheiroEsperadoFecho.value;
  justificativaDiferenca.value = "";
  erroFecho.value = "";
  modalFechoCaixa.value = true;
}

function confirmarFechoCaixa() {
  if (diferencaFecho.value === null) {
    erroFecho.value = "Informe o dinheiro real contado no caixa.";
    return;
  }
  if (diferencaFecho.value !== 0 && !justificativaDiferenca.value.trim()) {
    erroFecho.value = "Informe a justificativa da diferença para concluir o fecho.";
    return;
  }
  erroFecho.value = "";
  sessaoStore.fecharTurno({
    utilizador: sessaoStore.utilizador,
    caixa: sessaoStore.caixaAtribuido,
    aberturaEm: sessaoStore.aberturaEm,
    totalVendido: totalVendidoTurno.value,
    totalTransacoes: totalTransacoesTurno.value,
    ticketMedio: ticketMedioTurno.value,
    dinheiroEsperado: dinheiroEsperadoFecho.value,
    dinheiroReal: Number(dinheiroRealFecho.value || 0),
    diferenca: diferencaFecho.value,
    vendasDinheiro: totalDinheiroTurno.value,
    vendasTransferencia: totalTransferenciaTurno.value,
    justificativaDiferenca: justificativaDiferenca.value.trim(),
    auditoriaVendas: vendasTurno.value.map((venda) => ({
      data: venda.data,
      total: venda.total,
      metodoPagamento: venda.metodoPagamento,
      cliente: venda.cliente,
      itens: venda.itens.length,
    })),
  });
  modalFechoCaixa.value = false;
  tipoMensagem.value = "sucesso";
  mensagem.value = "Fecho de caixa concluído e relatório diário registado.";
}
</script>

<template>
  <section class="grid h-full grid-cols-1 gap-4 xl:grid-cols-[2.2fr_1fr] xl:items-center">
    <div v-if="menuPosAtivo === 'venda'" class="space-y-4 xl:flex xl:h-full xl:flex-col xl:justify-center">
      <div class="rp-card p-4 h-[420px]">
        <div class="mb-3 flex items-end justify-between gap-3">
          <div class="min-w-0 flex-1">
            <p class="mb-1 text-xl font-bold text-slate-800">Catálogo rápido de venda</p>
            <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white">
              <div class="flex w-24 items-center justify-center border-r border-slate-300 px-3 text-black" title="Código de barras">
                <svg width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                  <line x1="4" y1="5" x2="4" y2="19" />
                  <line x1="7" y1="5" x2="7" y2="19" />
                  <line x1="10" y1="5" x2="10" y2="19" />
                  <line x1="14" y1="5" x2="14" y2="19" />
                  <line x1="17" y1="5" x2="17" y2="19" />
                  <line x1="20" y1="5" x2="20" y2="19" />
                </svg>
              </div>
              <input v-model="pesquisa" type="text" placeholder="Nome ou código de barras..." class="min-w-0 flex-1 px-3 py-4 text-sm focus:outline-none" />
            </div>
          </div>
        </div>
        <div v-if="pesquisaAtiva" class="overflow-hidden rounded-lg border border-slate-200">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2">Produto</th>
                <th class="px-3 py-2">Código</th>
                <th class="px-3 py-2">Preço (MT)</th>
                <th class="px-3 py-2">Stock</th>
                <th class="px-3 py-2 text-right">Ação</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!produtosFiltrados.length">
                <td colspan="5" class="px-3 py-8 text-center text-xs text-slate-500">Nenhum produto encontrado.</td>
              </tr>
              <tr v-for="produto in produtosFiltrados" :key="produto.id" class="border-t border-slate-100 text-[12px] hover:bg-slate-50">
                <td class="px-3 py-2 font-semibold text-slate-800">{{ produto.nome }}</td>
                <td class="px-3 py-2 text-slate-600">{{ produto.codigoBarras }}</td>
                <td class="px-3 py-2 font-semibold text-slate-800">{{ formatarMT(produto.precoVendaComIva ?? produto.precoVenda) }}</td>
                <td class="px-3 py-2">
                  <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="produto.stock <= 10 ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'"
                  >
                    {{ produto.stock }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right">
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--gold)] text-black hover:brightness-95"
                    title="Adicionar ao carrinho"
                    :disabled="!podeAdicionarProduto(produto)"
                    :class="!podeAdicionarProduto(produto) ? 'cursor-not-allowed opacity-40' : ''"
                    @click="adicionarAoCarrinho(produto)"
                  >
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <circle cx="9" cy="20" r="1" />
                      <circle cx="17" cy="20" r="1" />
                      <path d="M1 1h3l2.68 12.39a2 2 0 0 0 2 1.61h7.72a2 2 0 0 0 2-1.61L21 6H6" />
                      <line x1="12" y1="8" x2="12" y2="13" />
                      <line x1="9.5" y1="10.5" x2="14.5" y2="10.5" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-else
          class="flex h-[290px] flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-slate-400"
        >
          <svg width="58" height="58" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="11" cy="11" r="7" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <p class="text-sm font-medium text-slate-500">Digite no campo de pesquisa para listar produtos.</p>
        </div>
      </div>
    </div>

    <div v-if="menuPosAtivo === 'venda'" class="space-y-4 xl:flex xl:h-full xl:flex-col xl:justify-center">
      <div class="rp-card h-[527px] overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-semibold text-slate-900">Pré-visualização</h3>
        </div>
        <div class="bg-slate-50 p-4">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="mb-3 flex items-start justify-between">
              <div>
                <p class="font-serif text-lg font-bold text-slate-900">RetailPro</p>
                <p class="text-[11px] text-slate-500">NUIT: 400000099</p>
              </div>
              <div class="text-right">
                <p class="font-semibold">VENDA</p>
              </div>
            </div>
            <div class="mb-2 border-t-2 border-[var(--gold)] pt-2 text-[11px]">
              <p class="text-slate-500">Cliente</p>
              <p class="font-semibold text-slate-800">{{ cliente }}</p>
            </div>
            <div
              ref="listaPreVisualizacaoRef"
              class="h-44 overflow-auto border-t border-slate-200 pt-2 text-xs"
              :class="!carrinhoStore.itens.length ? 'flex items-center justify-center pt-0' : ''"
            >
              <div
                v-for="item in itensCarrinhoOrdenados"
                :key="`pv-${item.produtoId}`"
                class="border-b border-dashed border-slate-300 py-2 last:border-b-0"
              >
                <div class="mb-1 flex items-start justify-between gap-2 px-1">
                  <span class="truncate pr-2 font-medium text-slate-700">{{ item.nome }}</span>
                  <button
                    class="flex h-5 w-5 items-center justify-center rounded text-red-600 hover:bg-red-50"
                    title="Remover item"
                    @click="carrinhoStore.removerProduto(item.produtoId)"
                  >
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <polyline points="3 6 5 6 21 6" />
                      <path d="M19 6l-1 14H6L5 6" />
                      <path d="M10 11v6M14 11v6" />
                    </svg>
                  </button>
                </div>
                <div class="flex items-center justify-between gap-2 px-1">
                  <div class="flex items-center gap-1">
                    <span class="text-[11px] text-slate-500">Qtd</span>
                    <input
                      :value="item.quantidade"
                      type="number"
                      min="1"
                      :max="produtoStore.produtos.find((p) => p.id === item.produtoId)?.stock || 1"
                      class="w-14 rounded border border-slate-300 px-1.5 py-0.5 text-[11px]"
                      @input="atualizarQuantidade(item.produtoId, $event.target.value)"
                    />
                  </div>
                  <span class="font-semibold">{{ formatarMT(item.subtotal) }}</span>
                </div>
              </div>
              <div v-if="!carrinhoStore.itens.length" class="flex flex-col items-center justify-center gap-2 text-slate-400">
                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="9" cy="20" r="1" />
                  <circle cx="18" cy="20" r="1" />
                  <path d="M2 3h2l2.2 10.2a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6L22 7H7" />
                  <line x1="10" y1="10" x2="17" y2="10" />
                </svg>
                <p class="text-xs font-medium text-slate-500">Sem itens no carrinho</p>
              </div>
            </div>
            <div class="mt-3 border-t border-slate-200 pt-2 text-sm font-bold">
              <div class="mb-1 flex items-center justify-between text-xs font-medium text-slate-500">
                <span>Subtotal:</span>
                <span>{{ formatarMT(carrinhoStore.subtotal) }}</span>
              </div>
              <div class="mb-1 flex items-center justify-between text-xs font-medium text-amber-700">
                <span>Desconto:</span>
                <span>- {{ formatarMT(descontoAplicado) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span>Total:</span>
                <span>{{ formatarMT(carrinhoStore.total) }}</span>
              </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
              <button
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-600 text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!carrinhoStore.itens.length"
                title="Resetar carrinho"
                aria-label="Resetar carrinho"
                @click="limparCarrinhoAtual"
              >
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                  <polyline points="1 4 1 10 7 10" />
                  <path d="M3.51 15a9 9 0 1 0 .49-9" />
                </svg>
              </button>
              <BotaoBase class="flex-1" variante="aviso" :disabled="!carrinhoStore.itens.length" @click="finalizarVenda">
                Finalizar Venda
              </BotaoBase>
            </div>
          </div>
        </div>
      </div>

      <p v-if="mensagem" class="text-sm font-semibold" :class="tipoMensagem === 'erro' ? 'text-red-600' : 'text-emerald-700'">
        {{ mensagem }}
      </p>
    </div>

    <div v-if="menuPosAtivo === 'caixa'" class="space-y-4 xl:col-span-2">
      <div class="rp-card p-4">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Dashboard de Caixa</h3>
            <p class="text-xs text-slate-500">Resumo financeiro e operacional do turno atual</p>
          </div>
          <button
            v-if="sessaoStore.turnoAberto"
            class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700"
            @click="abrirFechoCaixa"
          >
            Fechar caixa
          </button>
          <button v-else class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="modalAberturaCaixa = true">
            Abrir caixa
          </button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Vendido no turno</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatarMT(totalVendidoTurno) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Dinheiro esperado</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatarMT(dinheiroEsperadoFecho) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Transações</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ totalTransacoesTurno }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Ticket médio</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatarMT(ticketMedioTurno) }}</p>
          </div>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">Vendas em Dinheiro</p>
            <p class="mt-1 text-base font-bold text-emerald-900">{{ formatarMT(totalDinheiroTurno) }}</p>
          </div>
          <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            <p class="text-xs font-semibold text-blue-800">Vendas por Transferência</p>
            <p class="mt-1 text-base font-bold text-blue-900">{{ formatarMT(totalTransferenciaTurno) }}</p>
          </div>
        </div>

        <div
          class="mt-3 rounded-lg border p-3 text-sm"
          :class="
            !diferencaFechoTemValor
              ? 'border-slate-200 bg-slate-50 text-slate-700'
              : diferencaFecho >= 0
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-red-200 bg-red-50 text-red-800'
          "
        >
          Diferença projetada (contado - esperado):
          <strong>{{ diferencaFechoTemValor ? formatarMT(diferencaFecho) : "Aguardando contagem" }}</strong>
        </div>
      </div>

      <div class="rp-card p-4">
        <div class="mb-3 flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="text-sm font-bold text-slate-900">Últimas 5 vendas</h3>
            <p class="text-xs text-slate-500">Atalhos para reimpressão e solicitação de reversão</p>
          </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2">Referência</th>
                <th class="px-3 py-2">Data</th>
                <th class="px-3 py-2">Cliente</th>
                <th class="px-3 py-2">Pagamento</th>
                <th class="px-3 py-2">Total</th>
                <th class="px-3 py-2 text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!ultimasVendasTurno.length">
                <td colspan="6" class="px-3 py-7 text-center text-xs text-slate-500">Sem vendas no turno atual.</td>
              </tr>
              <tr v-for="venda in ultimasVendasTurno" :key="venda.id" class="border-t border-slate-100 text-[12px] hover:bg-slate-50">
                <td class="px-3 py-2 font-semibold text-slate-700">{{ venda.referencia || venda.id }}</td>
                <td class="px-3 py-2 text-slate-600">{{ formatarData(venda.data) }}</td>
                <td class="px-3 py-2 font-semibold text-slate-800">{{ venda.cliente }}</td>
                <td class="px-3 py-2 text-slate-600">{{ venda.metodoPagamento }}</td>
                <td class="px-3 py-2 font-semibold text-slate-800">{{ formatarMT(venda.total) }}</td>
                <td class="px-3 py-2">
                  <div class="flex justify-end gap-2">
                    <button
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--gold)] text-black hover:brightness-95"
                      title="Reimprimir"
                      aria-label="Reimprimir"
                      @click="reimprimirVenda(venda)"
                    >
                      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <polyline points="6 9 6 2 18 2 18 9" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                      </svg>
                    </button>
                    <button
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                      title="Solicitar reversão"
                      aria-label="Solicitar reversão"
                      :disabled="venda.estado === 'Revertida' || solicitacoesPendentesPorVenda.has(venda.id)"
                      @click="abrirSolicitacaoReversao(venda)"
                    >
                      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.1" viewBox="0 0 24 24" aria-hidden="true">
                        <polyline points="1 4 1 10 7 10" />
                        <path d="M3.51 15a9 9 0 1 0 .49-9" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-if="mensagem" class="text-sm font-semibold" :class="tipoMensagem === 'erro' ? 'text-red-600' : 'text-emerald-700'">
        {{ mensagem }}
      </p>
    </div>
  </section>

  <ModalBase :aberto="modalAberturaCaixa" :mostrar-fechar="false" titulo="Abertura de caixa">
    <div class="space-y-4">
      <p class="text-sm text-slate-600">
        Informe o valor inicial disponível no caixa para troco neste turno.
      </p>
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">Fundo inicial (MT)</label>
        <input v-model.number="fundoInicialInput" type="number" min="0" class="rp-input" @keyup.enter="abrirTurnoCaixa" />
      </div>
      <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
        Utilizador: <strong>{{ sessaoStore.utilizador || "Operador" }}</strong> · Caixa: <strong>{{ sessaoStore.caixaAtribuido || "Caixa 01" }}</strong>
      </div>
      <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
        <BotaoBase variante="perigo" @click="modalAberturaCaixa = false">
          <span class="inline-flex items-center gap-1.5">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" @click="abrirTurnoCaixa">Confirmar abertura</BotaoBase>
      </div>
    </div>
  </ModalBase>

  <ModalBase :aberto="modalFechoCaixa" titulo="Fecho de caixa" @fechar="modalFechoCaixa = false">
    <div class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        <p><strong>Total vendido no turno:</strong> {{ formatarMT(totalVendidoTurno) }}</p>
        <p><strong>Total de transações:</strong> {{ totalTransacoesTurno }}</p>
        <p><strong>Ticket médio:</strong> {{ formatarMT(ticketMedioTurno) }}</p>
        <p><strong>Vendas em Dinheiro:</strong> {{ formatarMT(totalDinheiroTurno) }}</p>
        <p><strong>Vendas por Transferência:</strong> {{ formatarMT(totalTransferenciaTurno) }}</p>
        <p><strong>Dinheiro esperado:</strong> {{ formatarMT(dinheiroEsperadoFecho) }}</p>
      </div>

      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">Dinheiro real contado no caixa</label>
        <input v-model.number="dinheiroRealFecho" type="number" min="0" class="rp-input" @keyup.enter="confirmarFechoCaixa" />
      </div>

      <div class="rounded-lg p-3 text-sm" :class="diferencaFecho >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        Diferença (sobra/falta): <strong>{{ formatarMT(diferencaFecho) }}</strong>
      </div>

      <div v-if="diferencaFechoTemValor && diferencaFecho !== 0">
        <label class="mb-1 block text-xs font-semibold text-slate-600">Justificativa da diferença (obrigatório)</label>
        <textarea
          v-model="justificativaDiferenca"
          rows="2"
          class="rp-input"
          placeholder="Ex: troco em aberto, sangria não registada, erro operacional..."
        />
      </div>

      <p v-if="erroFecho" class="text-sm font-semibold text-red-600">{{ erroFecho }}</p>

      <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
        <BotaoBase variante="secundario" @click="modalFechoCaixa = false">Cancelar</BotaoBase>
        <BotaoBase variante="perigo" @click="confirmarFechoCaixa">Confirmar fecho</BotaoBase>
      </div>
    </div>
  </ModalBase>

  <ModalBase :aberto="modalImpressaoAberto" :mostrar-fechar="false" titulo="Concluir venda" @fechar="modalImpressaoAberto = false">
    <div v-if="vendaPendente" class="space-y-4">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Cliente</label>
          <select v-model="cliente" class="rp-input">
            <option v-for="clienteItem in clientesParaSelect" :key="clienteItem.id + clienteItem.nome" :value="clienteItem.nome">
              {{ clienteItem.nome }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Método de pagamento</label>
          <select v-model="carrinhoStore.metodoPagamento" class="rp-input">
            <option>Dinheiro</option>
            <option>Transferência</option>
          </select>
        </div>
      </div>

      <div class="rounded-lg bg-slate-900 p-4 text-center">
        <p class="text-[11px] uppercase tracking-widest text-slate-300">Total a pagar</p>
        <p class="text-4xl font-black text-white">{{ formatarMT(carrinhoStore.total) }}</p>
      </div>

      <div class="rounded-lg border p-3 text-xs" :class="origemStockVenda.id ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700'">
        <p class="font-semibold">Origem do stock desta venda</p>
        <p v-if="origemStockVenda.id">
          {{ origemStockVenda.nome || origemStockVenda.codigo || `Localização #${origemStockVenda.id}` }}
        </p>
        <p v-else>
          Não configurada para este caixa. Vincule a localização de venda ao caixa no backend.
        </p>
      </div>

      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
        <div class="mb-2 flex items-center justify-between">
          <p class="text-xs font-semibold text-slate-700">Desconto (opcional)</p>
          <button
            class="rounded-md px-2 py-1 text-[11px] font-semibold"
            :class="descontoAtivo ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600'"
            @click="alternarDesconto"
          >
            {{ descontoAtivo ? "Ativo" : "Inativo" }}
          </button>
        </div>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-[120px_1fr]">
          <select
            :value="carrinhoStore.descontoTipo"
            class="rp-input"
            :disabled="!descontoAtivo"
            @change="carrinhoStore.definirDesconto({ tipo: $event.target.value, valor: carrinhoStore.descontoValor })"
          >
            <option value="valor">Valor (MT)</option>
            <option value="percentual">Percentual (%)</option>
          </select>
          <input
            :value="carrinhoStore.descontoValor"
            type="number"
            min="0"
            class="rp-input"
            :disabled="!descontoAtivo"
            :placeholder="carrinhoStore.descontoTipo === 'percentual' ? 'Ex: 10' : 'Ex: 100'"
            @input="atualizarDesconto($event.target.value)"
          />
        </div>
      </div>

      <div class="space-y-2">
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Valor pago</label>
          <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white">
            <div class="flex items-center border-r border-slate-300 px-3 text-slate-500">MT</div>
            <input
              :value="valorPagoInteiro"
              type="text"
              class="min-w-0 flex-1 px-3 py-2 text-right text-sm font-semibold text-slate-800 focus:outline-none disabled:bg-slate-50"
              :disabled="!podeInformarPagamento"
              @input="atualizarValorPagoInteiro($event.target.value)"
            />
            <div class="flex items-center border-l border-slate-300 px-2 text-slate-500">,</div>
            <input
              :value="valorPagoDecimal"
              type="text"
              maxlength="2"
              class="w-10 px-1 py-2 text-sm font-semibold text-slate-700 focus:outline-none disabled:bg-slate-50"
              :disabled="!podeInformarPagamento"
              @input="atualizarValorPagoDecimal($event.target.value)"
            />
          </div>
        </div>
        <div class="rounded-lg bg-slate-50 p-3 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Troco</span>
            <strong class="text-emerald-700">{{ formatarMT(troco) }}</strong>
          </div>
          <div class="mt-1 flex items-center justify-between">
            <span class="text-slate-500">Falta</span>
            <strong class="text-red-600">{{ formatarMT(falta) }}</strong>
          </div>
        </div>
      </div>
      <p v-if="erroFinalizacao" class="text-sm font-semibold text-red-600">{{ erroFinalizacao }}</p>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" title="Cancelar" aria-label="Cancelar" @click="modalImpressaoAberto = false">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </BotaoBase>
        <BotaoBase :disabled="imprimindoAgora" variante="aviso" title="Concluir sem imprimir" aria-label="Concluir sem imprimir" @click="concluirVenda({ imprimir: false })">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <polyline points="20 6 9 17 4 12" />
          </svg>
        </BotaoBase>
        <BotaoBase
          :disabled="!configuracaoStore.impressoraPadrao || imprimindoAgora"
          variante="sucesso"
          :title="imprimindoAgora ? 'A imprimir...' : 'Imprimir e concluir'"
          :aria-label="imprimindoAgora ? 'A imprimir...' : 'Imprimir e concluir'"
          @click="concluirVenda({ imprimir: true })"
        >
          <svg v-if="imprimindoAgora" class="animate-spin" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M21 12a9 9 0 1 1-3-6.7" />
          </svg>
          <svg v-else width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <polyline points="6 9 6 2 18 2 18 9" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <rect x="6" y="14" width="12" height="8" />
          </svg>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

  <ModalBase :aberto="modalSolicitarReversaoAberto" titulo="Confirmar solicitação de reversão" @fechar="modalSolicitarReversaoAberto = false">
    <div class="space-y-4">
      <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          </svg>
        </span>
        <div>
          <p class="font-semibold text-slate-900">Deseja realmente solicitar a reversão desta venda?</p>
          <p class="text-xs text-slate-600">Referência: {{ vendaParaReversao?.referencia || vendaParaReversao?.id }}</p>
        </div>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">Motivo (opcional)</label>
        <textarea v-model="motivoReversao" rows="2" class="rp-input" placeholder="Ex: item lançado por engano, cliente desistiu..." />
      </div>
      <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
        <BotaoBase variante="perigo" @click="modalSolicitarReversaoAberto = false">Cancelar</BotaoBase>
        <BotaoBase variante="sucesso" @click="confirmarSolicitacaoReversao">Confirmar solicitação</BotaoBase>
      </div>
    </div>
  </ModalBase>

  <transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="translate-y-2 opacity-0"
    enter-to-class="translate-y-0 opacity-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="translate-y-0 opacity-100"
    leave-to-class="translate-y-2 opacity-0"
  >
    <div
      v-if="toastAberto"
      class="pointer-events-none fixed bottom-5 right-5 z-[9999] max-w-sm rounded-lg border px-4 py-3 text-sm font-semibold shadow-xl"
      :class="toastTipo === 'erro' ? 'border-red-200 bg-red-600 text-white' : 'border-emerald-200 bg-emerald-600 text-white'"
    >
      {{ toastMensagem }}
    </div>
  </transition>
</template>
