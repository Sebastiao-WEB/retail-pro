<script setup>
import { computed, nextTick, onMounted, ref } from "vue";
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
import { mostrarToastSwal } from "../../services/toast";
import {
  Barcode,
  Check,
  DoorClosed,
  DoorOpen,
  LoaderCircle,
  Printer,
  RotateCcw,
  Search,
  ShoppingCart,
  ShoppingCartIcon,
  SquarePlus,
  Trash2,
  TriangleAlert,
  X,
} from "lucide-vue-next";

const produtoStore = useProdutoStore();
const carrinhoStore = useCarrinhoStore();
const clienteStore = useClienteStore();
const vendaStore = useVendaStore();
const configuracaoStore = useConfiguracaoStore();
const sessaoStore = useSessaoStore();
const route = useRoute();

const pesquisa = ref("");
const cliente = ref("Cliente Geral");
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
const modalSolicitarReversaoAberto = ref(false);
const vendaParaReversao = ref(null);
const motivoReversao = ref("");
const listaPreVisualizacaoRef = ref(null);
const menuPosAtivo = computed(() => (route.query?.secao === "caixa" ? "caixa" : "venda"));

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
  mostrarToastSwal(texto, "error");
}

function mostrarToast(texto, tipo = "erro") {
  const tipoSwal = tipo === "sucesso" ? "success" : "error";
  mostrarToastSwal(texto, tipoSwal);
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
  mostrarToastSwal(`A localização de stock não está vinculada ao ${caixaAtual}. Defina esta ligação no backend antes de concluir vendas.`, "error");
  return false;
}

function finalizarVenda() {
  if (!sessaoStore.turnoAberto) {
    mostrarToast("Abra o caixa antes de finalizar vendas.", "erro");
    return;
  }
  if (!carrinhoStore.itens.length) return;
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
}

async function concluirVenda(opcoes = { imprimir: true }) {
  if (!vendaPendente.value) return;
  if (!validarOrigemStock()) return;
  if (carrinhoStore.metodoPagamento === "Dinheiro" && valorPagoNumerico.value < carrinhoStore.total) {
    mostrarToastSwal("Valor pago insuficiente para concluir a venda.", "error");
    return;
  }

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
      mostrarToastSwal("API de impressão não disponível no desktop. Reinicie o Electron para recarregar o preload.", "error");
      return;
    }
    if (!configuracaoStore.impressoraPadrao) {
      mostrarToastSwal("Defina a impressora padrão em Configurações para imprimir.", "error");
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
      mostrarToastSwal(resultado?.error || "Falha ao imprimir talão.", "error");
      return;
    }
  }

  await vendaStore.registarVenda(venda);
  produtoStore.aplicarVenda(venda.itens);
  carrinhoStore.limparCarrinho();
  valorPagoInteiro.value = "0";
  valorPagoDecimal.value = "00";
  modalImpressaoAberto.value = false;

  mostrarToastSwal(
    opcoes.imprimir
      ? `Venda realizada e enviada para impressão em ${configuracaoStore.impressoraPadrao}.`
      : "Venda realizada com sucesso.",
    "success"
  );
  vendaPendente.value = null;
}

async function reimprimirVenda(venda) {
  if (!window.api?.imprimirTalao) {
    mostrarToastSwal("Reimpressão disponível apenas na versão desktop (Electron).", "error");
    return;
  }
  if (!configuracaoStore.impressoraPadrao) {
    mostrarToastSwal("Defina a impressora padrão em Configurações para reimprimir.", "error");
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
    mostrarToastSwal(resultado?.error || "Falha ao reimprimir talão.", "error");
    return;
  }
  mostrarToastSwal(`Recibo reenviado para impressão em ${configuracaoStore.impressoraPadrao}.`, "success");
}

function abrirSolicitacaoReversao(venda) {
  if (venda.estado === "Revertida") {
    mostrarToastSwal("Venda já foi revertida.", "error");
    return;
  }
  if (solicitacoesPendentesPorVenda.value.has(venda.id)) {
    mostrarToastSwal("Já existe solicitação de reversão pendente para esta venda.", "error");
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
    mostrarToastSwal(resultado.erro || "Não foi possível solicitar reversão.", "error");
    return;
  }
  modalSolicitarReversaoAberto.value = false;
  vendaParaReversao.value = null;
  mostrarToastSwal("Solicitação de reversão enviada ao gerente para aprovação.", "success");
}

onMounted(() => {
  sessaoStore.hidratar();
  configuracaoStore.hidratar();
  if (!sessaoStore.turnoAberto) {
    modalAberturaCaixa.value = true;
  }
});

function abrirTurnoCaixa() {
  const fundo = Number(fundoInicialInput.value || 0);
  sessaoStore.abrirTurno({ fundoInicial: fundo });
  modalAberturaCaixa.value = false;
  mostrarToastSwal(`Caixa aberto com fundo inicial de ${formatarMT(fundo)}.`, "success");
}

function abrirFechoCaixa() {
  dinheiroRealFecho.value = dinheiroEsperadoFecho.value;
  justificativaDiferenca.value = "";
  modalFechoCaixa.value = true;
}

function confirmarFechoCaixa() {
  if (diferencaFecho.value === null) {
    mostrarToastSwal("Informe o dinheiro real contado no caixa.", "warning");
    return;
  }
  if (diferencaFecho.value !== 0 && !justificativaDiferenca.value.trim()) {
    mostrarToastSwal("Informe a justificativa da diferença para concluir o fecho.", "warning");
    return;
  }
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
  mostrarToastSwal("Fecho de caixa concluído e relatório diário registado.", "success");
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
                <Barcode :size="42" :stroke-width="1.8" />
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
                    <SquarePlus :size="14" />
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
          <Search :size="58" :stroke-width="1.8" />
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
                    <Trash2 :size="12" />
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
                <ShoppingCart :size="40" :stroke-width="1.8" />
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
                <RotateCcw :size="16" :stroke-width="2.2" />
              </button>
              <BotaoBase class="flex-1" variante="aviso" :disabled="!carrinhoStore.itens.length" @click="finalizarVenda">
                Finalizar Venda
              </BotaoBase>
            </div>
          </div>
        </div>
      </div>

    </div>

    <div v-if="menuPosAtivo === 'caixa'" class="space-y-4 xl:col-span-2 xl:flex xl:h-full xl:min-h-0 xl:flex-col">
      <div class="rp-card overflow-hidden p-4">
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

      </div>

      <div class="rp-card overflow-hidden p-4 xl:flex-1 xl:min-h-0">
        <div class="mb-3 flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="text-sm font-bold text-slate-900">Últimas 5 vendas</h3>
            <p class="text-xs text-slate-500">Atalhos para reimpressão e solicitação de reversão</p>
          </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 xl:flex xl:h-41 xl:min-h-0 xl:flex-col">
          <table class="w-full table-fixed text-xs">
            <thead class="bg-slate-50 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="w-[22%] px-2 py-1.5">Referência</th>
                <th class="w-[21%] px-2 py-1.5">Data</th>
                <th class="w-[21%] px-2 py-1.5">Cliente</th>
                <th class="w-[14%] px-2 py-1.5">Pagamento</th>
                <th class="w-[12%] px-2 py-1.5">Total</th>
                <th class="w-[10%] px-2 py-1.5 text-right">Ações</th>
              </tr>
            </thead>
          </table>

          <div class="max-h-28 overflow-y-auto overflow-x-hidden xl:max-h-28">
            <table class="w-full table-fixed text-xs">
              <tbody>
                <tr v-if="!ultimasVendasTurno.length">
                  <td colspan="6" class="px-2 py-5 text-center text-[11px] text-slate-500">Sem vendas no turno atual.</td>
                </tr>
                <tr v-for="venda in ultimasVendasTurno" :key="venda.id" class="border-t border-slate-100 text-[11px] hover:bg-slate-50">
                  <td class="w-[22%] px-2 py-1.5 font-semibold text-slate-700">{{ venda.referencia || venda.id }}</td>
                  <td class="w-[21%] px-2 py-1.5 text-slate-600">{{ formatarData(venda.data) }}</td>
                  <td class="w-[21%] px-2 py-1.5 font-semibold text-slate-800">{{ venda.cliente }}</td>
                  <td class="w-[14%] px-2 py-1.5 text-slate-600">{{ venda.metodoPagamento }}</td>
                  <td class="w-[12%] px-2 py-1.5 font-semibold text-slate-800">{{ formatarMT(venda.total) }}</td>
                  <td class="w-[10%] px-2 py-1.5">
                    <div class="flex justify-end gap-1.5">
                      <button
                        class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-[var(--gold)] text-black hover:brightness-95"
                        title="Reimprimir"
                        aria-label="Reimprimir"
                        @click="reimprimirVenda(venda)"
                      >
                        <Printer :size="13" />
                      </button>
                      <button
                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                        title="Solicitar reversão"
                        aria-label="Solicitar reversão"
                        :disabled="venda.estado === 'Revertida' || solicitacoesPendentesPorVenda.has(venda.id)"
                        @click="abrirSolicitacaoReversao(venda)"
                      >
                        <RotateCcw :size="13" :stroke-width="2.1" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

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
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalAberturaCaixa = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" @click="abrirTurnoCaixa">
          <span class="inline-flex items-center gap-1.5">
            <DoorOpen :size="14" />
            <span>Confirmar abertura</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

  <ModalBase :aberto="modalFechoCaixa" :mostrar-fechar="false" titulo="Fecho de caixa" @fechar="modalFechoCaixa = false">
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

    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalFechoCaixa = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" @click="confirmarFechoCaixa">
          <span class="inline-flex items-center gap-1.5">
            <DoorClosed :size="14" />
            <span>Confirmar fecho</span>
          </span>
        </BotaoBase>
      </div>
    </template>
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

      <div
        v-if="temApiConfigurada()"
        class="rounded-lg border p-3 text-xs"
        :class="origemStockVenda.id ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700'"
      >
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
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" title="Cancelar" aria-label="Cancelar" @click="modalImpressaoAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase :disabled="imprimindoAgora" variante="aviso" title="Concluir sem imprimir" aria-label="Concluir sem imprimir" @click="concluirVenda({ imprimir: false })">
          <Check :size="16" :stroke-width="2.2" />
        </BotaoBase>
        <BotaoBase
          :disabled="!configuracaoStore.impressoraPadrao || imprimindoAgora"
          variante="sucesso"
          :title="imprimindoAgora ? 'A imprimir...' : 'Imprimir e concluir'"
          :aria-label="imprimindoAgora ? 'A imprimir...' : 'Imprimir e concluir'"
          @click="concluirVenda({ imprimir: true })"
        >
          <LoaderCircle v-if="imprimindoAgora" class="animate-spin" :size="16" :stroke-width="2.2" />
          <Printer v-else :size="16" />
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

  <ModalBase :aberto="modalSolicitarReversaoAberto" :mostrar-fechar="false" titulo="Confirmar solicitação de reversão" @fechar="modalSolicitarReversaoAberto = false">
    <div class="space-y-4">
      <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
          <TriangleAlert :size="14" />
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
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalSolicitarReversaoAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="sucesso" @click="confirmarSolicitacaoReversao">
          <span class="inline-flex items-center gap-1.5">
            <Check :size="14" />
            <span>Confirmar solicitação</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

</template>
