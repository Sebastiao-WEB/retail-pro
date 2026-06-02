<script setup>
import { computed, nextTick, onActivated, onMounted, onUnmounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import BotaoBase from "../../components/BotaoBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import PaginacaoTabela from "../../components/PaginacaoTabela.vue";
import { useProdutoStore } from "../../store/useProdutoStore";
import { useCarrinhoStore } from "../../store/useCarrinhoStore";
import { useClienteStore } from "../../store/useClienteStore";
import {
  useVendaStore,
  vendaPertenceTurnoAtual,
  podeSolicitarReversao,
  motivoBloqueioReversao,
} from "../../store/useVendaStore";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";
import { useSessaoStore } from "../../store/useSessaoStore";
import { calcularDiferencaProjetada } from "../../services/caixaMetricas";
import { temApiConfigurada } from "../../api";
import { temCatalogoOffline } from "../../services/offline/catalogCache";
import { isErroRedeOuIndisponivel, redeDisponivel } from "../../services/offline/networkError";
import { useOfflineStore } from "../../store/useOfflineStore";
import { mostrarToastSwal } from "../../services/toast";
import { intlLocale } from "../../services/localeStorage.js";
import { GENERAL_CLIENT_CANONICAL, isGeneralClient } from "../../services/i18nHelper.js";
import { enviarTalaoParaImpressao, abrirGavetaPosVenda } from "../../services/talaoImpressao";
import { enviarRelatorioFechoParaImpressao } from "../../services/relatorioFechoImpressao";
import {
  criarEstadoLeitorCodigoBarras,
  processarTeclaLeitorCodigoBarras,
} from "../../utils/leitorCodigoBarras";
import { pareceCodigoBarras } from "../../utils/produtoPesquisa";
import {
  formatarQuantidadeExibicao,
  formatarStockExibicao,
  normalizarQuantidadeVenda,
  parseQuantidadeTexto,
  passoQuantidade,
  quantidadeMinima,
  UNIDADE_VENDA_KG,
  vendidoPorPeso,
} from "../../utils/produtoQuantidade";
import {
  Barcode,
  Check,
  DoorClosed,
  DoorOpen,
  LoaderCircle,
  Plus,
  Printer,
  RotateCcw,
  Search,
  ShoppingCart,
  ShoppingCartIcon,
  Trash2,
  TriangleAlert,
  X,
} from "lucide-vue-next";

const produtoStore = useProdutoStore();
const { resultadosPesquisa, pesquisaEmCurso } = storeToRefs(produtoStore);
const carrinhoStore = useCarrinhoStore();
const clienteStore = useClienteStore();
const vendaStore = useVendaStore();
const configuracaoStore = useConfiguracaoStore();
const sessaoStore = useSessaoStore();
const offlineStore = useOfflineStore();
const route = useRoute();
const { t, locale } = useI18n();

const pesquisa = ref("");
const pesquisaInputRef = ref(null);
const estadoLeitorCodigo = criarEstadoLeitorCodigoBarras();
const cliente = ref(GENERAL_CLIENT_CANONICAL);
const descontoAtivo = ref(false);
const valorPagoInteiro = ref("0");
const valorPagoDecimal = ref("00");
const modalImpressaoAberto = ref(false);
const vendaPendente = ref(null);
const imprimindoAgora = ref(false);
const processandoConclusaoVenda = ref(false);
const vendasEmRegisto = new Set();
let filaProcessamentoLeitor = Promise.resolve();

function enfileirarProcessamentoLeitor(tarefa) {
  const corrida = filaProcessamentoLeitor.then(() => tarefa());
  filaProcessamentoLeitor = corrida.catch(() => {});
  return corrida;
}
const modalPesoAberto = ref(false);
const produtoPesoPendente = ref(null);
const quantidadeKgInput = ref("");
const inputKgPesoRef = ref(null);
const modalAberturaCaixa = ref(false);
const modalFechoCaixa = ref(false);
const fundoInicialInput = ref(1000);
const dinheiroRealFecho = ref(null);
const justificativaDiferenca = ref("");
const modalSolicitarReversaoAberto = ref(false);
const vendaParaReversao = ref(null);
const motivoReversao = ref("");
const processandoReversao = ref(false);
const listaPreVisualizacaoRef = ref(null);
const ignorarBlurQuantidadePreview = ref(false);
const processandoAberturaCaixa = ref(false);
const processandoFechoCaixa = ref(false);
const menuPosAtivo = computed(() => (route.query?.secao === "caixa" ? "caixa" : "venda"));
const modalBloqueiaLeitor = computed(
  () =>
    modalImpressaoAberto.value ||
    modalFechoCaixa.value ||
    modalSolicitarReversaoAberto.value ||
    modalAberturaCaixa.value ||
    modalPesoAberto.value
);
const pesquisaDeveTerFoco = computed(() => menuPosAtivo.value === "venda" && !modalBloqueiaLeitor.value);

const pesquisaAtiva = computed(() => pesquisa.value.trim().length > 0);

const PRODUTOS_POR_PAGINA = 15;
const paginaPesquisaProdutos = ref(1);
const totalResultadosPesquisa = computed(() => resultadosPesquisa.value.length);
const totalPaginasPesquisa = computed(() =>
  Math.max(1, Math.ceil(totalResultadosPesquisa.value / PRODUTOS_POR_PAGINA))
);
const resultadosPesquisaPaginados = computed(() => {
  const inicio = (paginaPesquisaProdutos.value - 1) * PRODUTOS_POR_PAGINA;
  return resultadosPesquisa.value.slice(inicio, inicio + PRODUTOS_POR_PAGINA);
});

function reiniciarPaginaPesquisa() {
  paginaPesquisaProdutos.value = 1;
}

function aoMudarPaginaPesquisa(pagina) {
  const destino = Math.max(1, Math.min(Number(pagina) || 1, totalPaginasPesquisa.value));
  paginaPesquisaProdutos.value = destino;
}

let debouncePesquisaTimer = null;
let sequenciaPesquisa = 0;

async function executarPesquisaProdutos(termoInformado) {
  const termo = String(termoInformado ?? pesquisa.value).trim();
  if (!termo) {
    reiniciarPaginaPesquisa();
    produtoStore.limparPesquisa();
    return;
  }

  const sequencia = ++sequenciaPesquisa;
  try {
    await produtoStore.buscarProdutos({
      search: termo,
      ...filtrosCatalogoProdutos(),
    });
    if (sequencia === sequenciaPesquisa) {
      reiniciarPaginaPesquisa();
    }
  } catch (erro) {
    if (sequencia !== sequenciaPesquisa) return;
    mostrarToastSwal(erro?.message || t("pos.toast.searchFailed"), "error");
  }
}

function limparCampoPesquisa({ manterEstadoLeitor = false } = {}) {
  clearTimeout(debouncePesquisaTimer);
  sequenciaPesquisa += 1;
  pesquisa.value = "";
  reiniciarPaginaPesquisa();
  produtoStore.limparPesquisa();
  if (!manterEstadoLeitor) {
    Object.assign(estadoLeitorCodigo, criarEstadoLeitorCodigoBarras());
  }
}

function aoTeclaCampoPesquisa(event, { capturaGlobal = false } = {}) {
  const { estado, acao, valor } = processarTeclaLeitorCodigoBarras(estadoLeitorCodigo, event);
  Object.assign(estadoLeitorCodigo, estado);

  if (acao === "confirmar-leitor") {
    event.preventDefault();
    void enfileirarProcessamentoLeitor(() => processarLeituraCodigoBarras());
    return acao;
  }

  if (acao === "confirmar-manual") {
    event.preventDefault();
    const termo = pesquisa.value.trim();
    if (pareceCodigoBarras(termo)) {
      void enfileirarProcessamentoLeitor(() => processarLeituraCodigoBarras());
    } else {
      reiniciarPaginaPesquisa();
      pesquisarProdutosAgora();
    }
    return acao;
  }

  if (acao === "iniciar-leitor") {
    event.preventDefault();
    limparCampoPesquisa({ manterEstadoLeitor: true });
    pesquisa.value = valor;
    return acao;
  }

  if (capturaGlobal && (acao === "continuar-leitor" || acao === "normal") && event.key.length === 1) {
    event.preventDefault();
    pesquisa.value += event.key;
    return acao;
  }

  return acao;
}

function capturarTeclasPosGlobais(event) {
  if (!pesquisaDeveTerFoco.value) return;
  if (event.target === pesquisaInputRef.value) return;
  if (listaPreVisualizacaoRef.value?.contains(event.target)) return;

  const teclaRelevante =
    event.key === "Enter" || (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey);
  if (!teclaRelevante) return;

  event.preventDefault();
  pesquisaInputRef.value?.focus({ preventScroll: true });
  aoTeclaCampoPesquisa(event, { capturaGlobal: true });
}

async function processarLeituraCodigoBarras() {
  const codigo = pesquisa.value.trim();
  if (!codigo) return;

  clearTimeout(debouncePesquisaTimer);
  sequenciaPesquisa += 1;

  const filtros = filtrosCatalogoProdutos();
  produtoStore.garantirCatalogoPosLocal(filtros);

  const produto =
    produtoStore.resolverPorCodigoBarras(codigo) ||
    (await produtoStore.resolverPorCodigoBarrasComFallback(codigo, filtros));

  if (!produto) {
    mostrarToastSwal(t("pos.toast.productNotFound", { code: codigo }), "error");
    limparCampoPesquisa();
    await focarCampoPesquisa();
    return;
  }

  const produtoAtualizado = obterProdutoAtualizado(produto);

  if (!sessaoStore.turnoAberto) {
    mostrarToastSwal(t("pos.toast.openRegisterFirst"), "error");
    limparCampoPesquisa();
    await focarCampoPesquisa();
    return;
  }

  if (!vendidoPorPeso(produtoAtualizado) && !podeAdicionarProduto(produtoAtualizado, 1)) {
    if (produtoAtualizado.stock <= 0) {
      mostrarToastSwal(t("pos.toast.noStock", { name: produtoAtualizado.nome }), "error");
    } else {
      const disponivel = Math.max(0, produtoAtualizado.stock - quantidadeNoCarrinho(produtoAtualizado.id));
      mostrarErroStock(
        t("pos.toast.insufficientStock", {
          name: produtoAtualizado.nome,
          available: formatarDisponivelStock(produtoAtualizado, disponivel),
        })
      );
    }
    limparCampoPesquisa();
    await focarCampoPesquisa();
    return;
  }

  if (vendidoPorPeso(produtoAtualizado) && produtoAtualizado.stock <= 0) {
    mostrarToastSwal(t("pos.toast.noStock", { name: produtoAtualizado.nome }), "error");
    limparCampoPesquisa();
    await focarCampoPesquisa();
    return;
  }

  await solicitarAdicaoProduto(produtoAtualizado);
  limparCampoPesquisa();
  await focarCampoPesquisa();
}

async function focarCampoPesquisa() {
  if (!pesquisaDeveTerFoco.value) return;

  await nextTick();

  const tentarFoco = () => {
    const input = pesquisaInputRef.value;
    if (!input || !pesquisaDeveTerFoco.value) return false;
    input.focus({ preventScroll: true });
    input.select();
    return document.activeElement === input;
  };

  if (tentarFoco()) return;

  [50, 150, 350, 700].forEach((atraso) => {
    window.setTimeout(() => {
      tentarFoco();
    }, atraso);
  });
}

async function confirmarQuantidadePreview(produtoId, valor, event) {
  event?.preventDefault?.();
  event?.stopPropagation?.();

  ignorarBlurQuantidadePreview.value = true;
  await atualizarQuantidade(produtoId, valor, { normalizar: true });
  await focarCampoPesquisa();
  window.setTimeout(() => {
    ignorarBlurQuantidadePreview.value = false;
  }, 0);
}

function aoBlurQuantidadePreview(produtoId, valor) {
  if (ignorarBlurQuantidadePreview.value) return;
  void atualizarQuantidade(produtoId, valor, { normalizar: true });
}

function pesquisarProdutosAgora() {
  clearTimeout(debouncePesquisaTimer);
  reiniciarPaginaPesquisa();
  executarPesquisaProdutos(pesquisa.value);
}

watch(pesquisa, (valor) => {
  if (estadoLeitorCodigo.modoLeitorCodigo) return;

  clearTimeout(debouncePesquisaTimer);
  const termo = String(valor || "").trim();
  if (!termo) {
    sequenciaPesquisa += 1;
    reiniciarPaginaPesquisa();
    produtoStore.limparPesquisa();
    return;
  }
  debouncePesquisaTimer = setTimeout(() => {
    reiniciarPaginaPesquisa();
    executarPesquisaProdutos(termo);
  }, 300);
});

watch(menuPosAtivo, (secao) => {
  if (secao === "venda") {
    void garantirInventarioPosLocal();
    void focarCampoPesquisa();
    return;
  }
});

watch(modalAberturaCaixa, (aberto) => {
  if (!aberto) {
    void focarCampoPesquisa();
  }
});

watch(modalBloqueiaLeitor, (bloqueado) => {
  if (!bloqueado) {
    void focarCampoPesquisa();
  }
});

const clientesDisponiveis = computed(() => clienteStore.clientes);
const clientesParaSelect = computed(() => {
  const lista = [{ id: 0, nome: GENERAL_CLIENT_CANONICAL }, ...clientesDisponiveis.value];
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
const vendasTurno = computed(() =>
  vendaStore.vendas.filter((venda) => vendaPertenceTurnoAtual(venda, sessaoStore))
);
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

/** Catálogo/POS: stock igual ao admin (products.stock na API, sem filtro por local). */
function filtrosCatalogoProdutos() {
  return {};
}

/** Vendas e versões de stock: local do caixa. */
function filtrosStockPos() {
  if (!temApiConfigurada() || !origemStockVenda.value.id) return {};
  return { source_location_id: origemStockVenda.value.id };
}

function temStockLocalParaVenda() {
  return produtoStore.usarStockLocalPos || temCatalogoOffline(filtrosCatalogoProdutos()) || produtoStore.catalogoPosPronto;
}

async function garantirInventarioPosLocal() {
  if (produtoStore.usarStockLocalPos) return;
  produtoStore.garantirCatalogoPosLocal(filtrosCatalogoProdutos());
}

function ajustarCarrinhoAoStock() {
  for (const item of [...carrinhoStore.itens]) {
    const produto = produtoStore.produtos.find((reg) => reg.id === item.produtoId);
    if (!produto || produto.stock <= 0) {
      carrinhoStore.removerProduto(item.produtoId);
      continue;
    }
    if (item.quantidade > produto.stock) {
      carrinhoStore.definirQuantidade(item.produtoId, produto.stock);
    }
  }
}

async function sincronizarStockPos() {
  await garantirInventarioPosLocal();
  if (carrinhoStore.itens.length > 0) {
    ajustarCarrinhoAoStock();
  }
}

function idsProdutosCarrinho() {
  return [...new Set(carrinhoStore.itens.map((item) => item.produtoId).filter(Boolean))];
}

function obterProdutoAtualizado(produto) {
  const doCatalogo = produtoStore.produtos.find((reg) => reg.id === produto.id);
  if (!doCatalogo) return produto;
  return {
    ...doCatalogo,
    unidadeVenda: produto.unidadeVenda ?? doCatalogo.unidadeVenda,
    stock: Math.max(Number(produto.stock ?? 0), Number(doCatalogo.stock ?? 0)),
    precoVenda: produto.precoVenda ?? doCatalogo.precoVenda,
    precoVendaComIva: produto.precoVendaComIva ?? doCatalogo.precoVendaComIva,
  };
}

function validarStockCarrinhoAtual() {
  for (const item of carrinhoStore.itens) {
    const produto = produtoStore.produtos.find((reg) => reg.id === item.produtoId);
    if (!produto || produto.stock <= 0) {
      return { ok: false, erro: t("pos.toast.stockUnavailable", { name: item.nome }) };
    }
    if (item.quantidade > produto.stock) {
      return {
        ok: false,
        erro: t("pos.toast.insufficientStock", {
          name: item.nome,
          available: formatarDisponivelStock(produto, produto.stock),
        }),
      };
    }
  }
  return { ok: true };
}

function formatarMT(valor) {
  return `${new Intl.NumberFormat(intlLocale(locale.value), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(valor)} MT`;
}

function formatarIva(valor) {
  const numero = Number(valor || 0);
  return `${Number.isFinite(numero) ? numero : 0}%`;
}

function formatarData(valor) {
  return new Date(valor).toLocaleString(intlLocale(locale.value));
}

function traduzirMetodoPagamento(metodo) {
  if (metodo === "Dinheiro") return t("pos.payment.cash");
  if (metodo === "Transferência") return t("pos.payment.transfer");
  return metodo;
}

function quantidadeNoCarrinho(produtoId) {
  return carrinhoStore.itens.find((item) => item.produtoId === produtoId)?.quantidade || 0;
}

function podeAdicionarProduto(produto, quantidade = 1) {
  const quantidadeNormalizada =
    normalizarQuantidadeVenda(quantidade, produto.unidadeVenda) ?? quantidadeMinima(produto.unidadeVenda);
  return (
    sessaoStore.turnoAberto &&
    produto.stock > 0 &&
    quantidadeNoCarrinho(produto.id) + quantidadeNormalizada <= produto.stock + 0.0001
  );
}

function formatarDisponivelStock(produto, quantidadeDisponivel) {
  return formatarStockExibicao(quantidadeDisponivel, produto?.unidadeVenda, intlLocale(locale.value));
}

async function abrirModalPeso(produto) {
  produtoPesoPendente.value = produto;
  quantidadeKgInput.value = "";
  modalPesoAberto.value = true;
  await nextTick();
  inputKgPesoRef.value?.focus();
}

function fecharModalPeso() {
  modalPesoAberto.value = false;
  produtoPesoPendente.value = null;
  quantidadeKgInput.value = "";
}

async function confirmarModalPeso() {
  const produto = produtoPesoPendente.value;
  if (!produto) return;

  const quantidadeKg = parseQuantidadeTexto(quantidadeKgInput.value, UNIDADE_VENDA_KG);
  if (!quantidadeKg) {
    mostrarToastSwal(t("pos.weightModal.invalidKg"), "error");
    return;
  }

  fecharModalPeso();
  await adicionarAoCarrinho(produto, quantidadeKg);
}

async function solicitarAdicaoProduto(produto) {
  if (!sessaoStore.turnoAberto) {
    mostrarToastSwal(t("pos.toast.openRegisterFirst"), "error");
    return;
  }

  const produtoAtualizado = obterProdutoAtualizado(produto);

  if (vendidoPorPeso(produtoAtualizado)) {
    if (produtoAtualizado.stock <= 0) {
      mostrarToastSwal(t("pos.toast.noStock", { name: produtoAtualizado.nome }), "error");
      return;
    }
    await abrirModalPeso(produtoAtualizado);
    return;
  }

  await adicionarAoCarrinho(produtoAtualizado, 1);
}

function mostrarErroStock(texto = t("pos.toast.insufficientStockDefault")) {
  mostrarToastSwal(texto, "error");
}

function mostrarToast(texto, tipo = "erro") {
  const tipoSwal = tipo === "sucesso" ? "success" : "error";
  mostrarToastSwal(texto, tipoSwal);
}

async function adicionarAoCarrinho(produto, quantidade = 1) {
  const quantidadeNormalizada =
    normalizarQuantidadeVenda(quantidade, produto.unidadeVenda) ?? quantidadeMinima(produto.unidadeVenda);

  const produtoAtualizado = obterProdutoAtualizado(produto);

  if (!podeAdicionarProduto(produtoAtualizado, quantidadeNormalizada)) {
    if (produtoAtualizado.stock <= 0) {
      mostrarErroStock(t("pos.toast.noStockAvailable", { name: produtoAtualizado.nome }));
    } else if (quantidadeNoCarrinho(produtoAtualizado.id) + quantidadeNormalizada > produtoAtualizado.stock) {
      const disponivel = Math.max(0, produtoAtualizado.stock - quantidadeNoCarrinho(produtoAtualizado.id));
      mostrarErroStock(
        t("pos.toast.insufficientStock", {
          name: produtoAtualizado.nome,
          available: formatarDisponivelStock(produtoAtualizado, disponivel),
        })
      );
    } else {
      mostrarErroStock(t("pos.toast.cannotExceedStock"));
    }
    return;
  }

  carrinhoStore.adicionarProduto(produtoAtualizado, quantidadeNormalizada);
  await nextTick();
  if (listaPreVisualizacaoRef.value) {
    listaPreVisualizacaoRef.value.scrollTop = 0;
  }
  limparCampoPesquisa();
  await focarCampoPesquisa();
}

function obterUnidadeVendaItem(produtoId) {
  const item = carrinhoStore.itens.find((reg) => reg.produtoId === produtoId);
  if (item?.unidadeVenda) return item.unidadeVenda;
  const produto = produtoStore.produtos.find((reg) => reg.id === produtoId);
  return produto?.unidadeVenda;
}

async function atualizarQuantidade(produtoId, valor, { normalizar = false } = {}) {
  const item = carrinhoStore.itens.find((reg) => reg.produtoId === produtoId);
  const unidadeVenda = obterUnidadeVendaItem(produtoId);
  const texto = String(valor ?? "").trim();
  if (!normalizar && texto === "") return;

  const minimo = quantidadeMinima(unidadeVenda);
  let quantidadeFinal = parseQuantidadeTexto(texto, unidadeVenda);
  if (!quantidadeFinal && normalizar) {
    quantidadeFinal = minimo;
  }
  if (!quantidadeFinal) return;

  const produto = produtoStore.produtos.find((reg) => reg.id === produtoId);
  const limiteStock = produto?.stock ?? quantidadeFinal;
  if (quantidadeFinal > limiteStock + 0.0001) {
    carrinhoStore.definirQuantidade(produtoId, normalizarQuantidadeVenda(limiteStock, unidadeVenda) ?? minimo);
    mostrarErroStock(t("pos.toast.quantityAdjusted"));
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

function validarOrigemStock() {
  if (!temApiConfigurada()) return true;
  if (origemStockVenda.value.id) return true;
  const caixaAtual = sessaoStore.caixaAtribuido || t("pos.toast.currentRegister");
  mostrarToastSwal(t("pos.toast.stockLocationNotLinked", { register: caixaAtual }), "error");
  return false;
}

function gerarIdVenda() {
  if (typeof globalThis.crypto?.randomUUID === "function") {
    return globalThis.crypto.randomUUID();
  }
  return `local-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
}

function finalizarVenda() {
  if (!sessaoStore.turnoAberto) {
    mostrarToast(t("pos.toast.openRegisterToFinalize"), "erro");
    return;
  }
  if (!carrinhoStore.itens.length) return;
  if (!validarOrigemStock()) {
    mostrarToast(t("pos.toast.stockLocationNotConfigured"), "erro");
    return;
  }
  vendaPendente.value = { abertaEm: Date.now() };
  modalImpressaoAberto.value = true;
}

function montarVendaConfirmada(idVenda) {
  const itens = carrinhoStore.itens.map((item) => ({ ...item }));
  return {
    id: idVenda,
    cliente: cliente.value,
    itens,
    caixa: sessaoStore.caixaAtribuido,
    registerId: sessaoStore.registerId,
    registerCodigo: sessaoStore.registerCodigo,
    cashSessionId: sessaoStore.cashSessionId,
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
    register_id: sessaoStore.registerId,
    source_location_id: origemStockVenda.value.id,
    cash_session_id: sessaoStore.cashSessionId,
  };
}

function limparCarrinhoAtual() {
  carrinhoStore.limparCarrinho();
  valorPagoInteiro.value = "0";
  valorPagoDecimal.value = "00";
  descontoAtivo.value = false;
}

async function executarTarefasPosVendaAposRegisto(venda, opcoes, modoRegisto) {
  try {
    if (opcoes.imprimir) {
      if (!window.api?.imprimirTalao) {
        mostrarToastSwal(t("pos.toast.printApiUnavailable"), "error");
        return;
      }
      if (!configuracaoStore.impressoraPadrao) {
        mostrarToastSwal(t("pos.toast.setPrinterToPrint"), "error");
        return;
      }
      imprimindoAgora.value = true;
      const resultado = await enviarTalaoParaImpressao({
        venda,
        configuracao: configuracaoStore,
        opcoes: {
          detalharIva: true,
          copies: Math.max(1, Number(configuracaoStore.copiasImpressao || 1)),
          corteAutomatico: !!configuracaoStore.corteAutomatico,
          abrirGaveta: false,
        },
      });
      imprimindoAgora.value = false;
      if (!resultado?.ok) {
        mostrarToastSwal(resultado?.error || t("pos.toast.printFailed"), "error");
      }
    }

    const resultadoGaveta = await abrirGavetaPosVenda({ venda, configuracao: configuracaoStore });
    if (!resultadoGaveta?.ok && !resultadoGaveta?.skipped) {
      mostrarToastSwal(resultadoGaveta?.error || t("pos.toast.openDrawerFailed"), "warning");
    }
  } finally {
    imprimindoAgora.value = false;
  }
}

async function concluirVenda(opcoes = { imprimir: true }) {
  if (processandoConclusaoVenda.value) return;
  if (opcoes.imprimir && imprimindoAgora.value) return;

  if (!vendaPendente.value) return;

  const vendaId = gerarIdVenda();
  if (vendasEmRegisto.has(vendaId)) return;

  processandoConclusaoVenda.value = true;
  vendasEmRegisto.add(vendaId);

  try {
    const vendaBase = montarVendaConfirmada(vendaId);
    const itensPendentes = vendaBase.itens;
    if (!itensPendentes.length) {
      mostrarToastSwal(t("pos.toast.emptyCart"), "error");
      modalImpressaoAberto.value = false;
      vendaPendente.value = null;
      return;
    }

    if (!validarOrigemStock()) return;

    const totalPendente = Number(vendaBase.total ?? 0);
    if (vendaBase.metodoPagamento === "Dinheiro" && valorPagoNumerico.value < totalPendente) {
      mostrarToastSwal(t("pos.toast.insufficientPayment"), "error");
      return;
    }

    if (temApiConfigurada() && !temStockLocalParaVenda() && !redeDisponivel()) {
      mostrarToastSwal(t("pos.toast.offlineCatalogRequired"), "error");
      return;
    }

    const validacaoStock = validarStockCarrinhoAtual();
    if (!validacaoStock.ok) {
      mostrarToastSwal(validacaoStock.erro, "error");
      return;
    }

    const venda = {
      ...vendaBase,
      stockVersions: {},
    };

    produtoStore.aplicarVenda(venda.itens, filtrosCatalogoProdutos());

    let resultadoRegisto = null;
    try {
      if (produtoStore.usarStockLocalPos || temStockLocalParaVenda()) {
        resultadoRegisto = vendaStore.registarVendaPosRapida(venda);
      } else {
        resultadoRegisto = await vendaStore.registarVenda(venda);
      }
    } catch (erro) {
      produtoStore.reporStockItensVenda(venda.itens);
      mostrarToastSwal(erro?.message || t("pos.toast.registerSaleFailed"), "error");
      return;
    }

    carrinhoStore.limparCarrinho();
    limparCampoPesquisa();
    valorPagoInteiro.value = "0";
    valorPagoDecimal.value = "00";
    modalImpressaoAberto.value = false;
    vendaPendente.value = null;

    const mensagemSucesso =
      resultadoRegisto?.modo === "offline"
        ? opcoes.imprimir
          ? t("pos.toast.saleOfflinePrinted", { printer: configuracaoStore.impressoraPadrao })
          : t("pos.toast.saleOfflineQueued")
        : resultadoRegisto?.modo === "online-enviando"
          ? opcoes.imprimir
            ? t("pos.toast.saleQueuedSync")
            : t("pos.toast.saleQueuedSync")
          : opcoes.imprimir
            ? t("pos.toast.salePrinted", { printer: configuracaoStore.impressoraPadrao })
            : t("pos.toast.saleSuccess");

    mostrarToastSwal(
      mensagemSucesso,
      resultadoRegisto?.modo === "offline" ? "warning" : "success"
    );

    void executarTarefasPosVendaAposRegisto(venda, opcoes, resultadoRegisto?.modo);
  } finally {
    vendasEmRegisto.delete(vendaId);
    processandoConclusaoVenda.value = false;
    imprimindoAgora.value = false;
  }
}

async function reimprimirVenda(venda) {
  if (imprimindoAgora.value) return;
  if (!window.api?.imprimirTalao) {
    mostrarToastSwal(t("pos.toast.reprintDesktopOnly"), "error");
    return;
  }
  if (!configuracaoStore.impressoraPadrao) {
    mostrarToastSwal(t("pos.toast.setPrinterToReprint"), "error");
    return;
  }
  imprimindoAgora.value = true;
  const resultado = await enviarTalaoParaImpressao({
    venda,
    configuracao: configuracaoStore,
    opcoes: {
      detalharIva: true,
      segundaVia: true,
      copies: 1,
      corteAutomatico: !!configuracaoStore.corteAutomatico,
      abrirGaveta: false,
    },
  });
  imprimindoAgora.value = false;
  if (!resultado?.ok) {
    mostrarToastSwal(resultado?.error || t("pos.toast.reprintFailed"), "error");
    return;
  }
  mostrarToastSwal(t("pos.toast.reprintSuccess", { printer: configuracaoStore.impressoraPadrao }), "success");
}

function abrirSolicitacaoReversao(venda) {
  const bloqueio = motivoBloqueioReversao(venda, vendaStore.solicitacoesReversao);
  if (bloqueio) {
    mostrarToastSwal(bloqueio, "error");
    return;
  }
  vendaParaReversao.value = venda;
  motivoReversao.value = "";
  modalSolicitarReversaoAberto.value = true;
}

async function confirmarSolicitacaoReversao() {
  if (!vendaParaReversao.value || processandoReversao.value) return;
  processandoReversao.value = true;
  const venda = vendaParaReversao.value;
  try {
  const resultado = await vendaStore.solicitarReversao({
    vendaId: venda.id,
    venda,
    referencia: venda.referencia || String(venda.id),
    solicitadoPor: sessaoStore.utilizador || "Operador",
    motivo: motivoReversao.value.trim(),
  });
  if (!resultado.ok) {
    mostrarToastSwal(resultado.erro || t("pos.toast.reversalFailed"), "error");
    return;
  }
  modalSolicitarReversaoAberto.value = false;
  vendaParaReversao.value = null;
  mostrarToastSwal(t("pos.toast.reversalSent"), "success");
  } finally {
    processandoReversao.value = false;
  }
}

async function refrescarPosAposSyncOffline(detail = {}) {
  const enviados = Number(detail?.enviados || 0);
  if (enviados <= 0) return;

  try {
    await vendaStore.sincronizarHistorico();
  } catch {
    // Mantém histórico local se o remoto falhar.
  }

  if (menuPosAtivo.value !== "venda") return;

  try {
    await sincronizarStockPos();
    validarStockCarrinhoAtual();
  } catch {
    // O operador pode actualizar manualmente ao voltar ao POS.
  }
}

function aoSyncBackgroundConcluido() {
  if (menuPosAtivo.value !== "venda") return;
  ajustarCarrinhoAoStock();
}

function aoVendaSyncFalhou(evento) {
  const mensagem = evento?.detail?.mensagem || t("pos.toast.saleSyncFailed");
  mostrarToastSwal(mensagem, "error");
}

function aoVoltarOnline() {
  void offlineStore.atualizarConectividade();
}

function aoSyncOfflineConcluido(evento) {
  void refrescarPosAposSyncOffline(evento?.detail || {});
}

onMounted(async () => {
  window.addEventListener("keydown", capturarTeclasPosGlobais, true);
  window.addEventListener("online", aoVoltarOnline);
  window.addEventListener("retailpro:offline-sync", aoSyncOfflineConcluido);
  window.addEventListener("retailpro:offline-sync-complete", aoSyncOfflineConcluido);
  window.addEventListener("retailpro:pos-sync-background", aoSyncBackgroundConcluido);
  window.addEventListener("retailpro:venda-sync-falhou", aoVendaSyncFalhou);
  sessaoStore.hidratar();
  configuracaoStore.hidratar();
  await offlineStore.atualizarConectividade();
  if (temApiConfigurada()) {
    try {
      await configuracaoStore.hidratarDadosEmpresaRemotos();
    } catch {
      // Mantem dados locais quando a API falhar.
    }
    const sincronizacao = await sessaoStore.sincronizarTurnoRemoto();
    if (!sincronizacao.remotoOk && !sincronizacao.offlineLocal && sincronizacao.erro) {
      mostrarToastSwal(sincronizacao.erro, "error");
    }
    if (offlineStore.totalPendentes) {
      void offlineStore.sincronizarPendentes();
    }
  }
  if (!sessaoStore.turnoAberto) {
    modalAberturaCaixa.value = true;
  }
  if (menuPosAtivo.value === "venda") {
    await garantirInventarioPosLocal();
    await focarCampoPesquisa();
  }
});

onActivated(() => {
  if (menuPosAtivo.value === "venda") {
    void focarCampoPesquisa();
  }
});

onUnmounted(() => {
  window.removeEventListener("keydown", capturarTeclasPosGlobais, true);
  window.removeEventListener("online", aoVoltarOnline);
  window.removeEventListener("retailpro:offline-sync", aoSyncOfflineConcluido);
  window.removeEventListener("retailpro:offline-sync-complete", aoSyncOfflineConcluido);
  window.removeEventListener("retailpro:pos-sync-background", aoSyncBackgroundConcluido);
  window.removeEventListener("retailpro:venda-sync-falhou", aoVendaSyncFalhou);
});

async function abrirTurnoCaixa() {
  if (processandoAberturaCaixa.value) return;
  processandoAberturaCaixa.value = true;
  const fundo = Number(fundoInicialInput.value || 0);
  const resultado = await sessaoStore.abrirTurno({ fundoInicial: fundo });
  if (temApiConfigurada() && !resultado.remotoOk && !resultado.offlineLocal) {
    mostrarToastSwal(resultado.erro || t("pos.toast.openRegisterApiFailed"), "error");
    processandoAberturaCaixa.value = false;
    return;
  }
  modalAberturaCaixa.value = false;
  if (temApiConfigurada()) {
    if (resultado.offlineLocal) {
      mostrarToastSwal(t("pos.toast.openRegisterOffline"), "warning");
    } else if (resultado.remotoOk) {
      mostrarToastSwal(t("pos.toast.openRegisterSuccessRemote", { amount: formatarMT(fundo) }), "success");
    } else {
      mostrarToastSwal(t("pos.toast.openRegisterSuccessLocal", { error: resultado.erro || "erro não detalhado" }), "warning");
    }
    try {
      await sincronizarStockPos();
    } catch {
      if (!produtoStore.catalogoPosPronto) {
        mostrarToastSwal(t("pos.toast.catalogCacheRequired"), "error");
      }
    }
  } else {
    mostrarToastSwal(t("pos.toast.openRegisterSuccess", { amount: formatarMT(fundo) }), "success");
  }
  processandoAberturaCaixa.value = false;
  await focarCampoPesquisa();
  iniciarSincronizacaoStockPeriodica();
}

function abrirFechoCaixa() {
  dinheiroRealFecho.value = dinheiroEsperadoFecho.value;
  justificativaDiferenca.value = "";
  modalFechoCaixa.value = true;
}

async function confirmarFechoCaixa() {
  if (processandoFechoCaixa.value) return;
  if (diferencaFecho.value === null) {
    mostrarToastSwal(t("pos.toast.enterActualCash"), "warning");
    return;
  }
  if (diferencaFecho.value !== 0 && !justificativaDiferenca.value.trim()) {
    mostrarToastSwal(t("pos.toast.enterDifferenceJustification"), "warning");
    return;
  }
  processandoFechoCaixa.value = true;
  const fechadoEm = new Date().toISOString();
  const relatorio = {
    utilizador: sessaoStore.utilizador,
    caixa: sessaoStore.caixaAtribuido,
    aberturaEm: sessaoStore.aberturaEm,
    fechadoEm,
    fundoInicial: sessaoStore.fundoInicial,
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
  };
  const resultado = await sessaoStore.fecharTurno(relatorio);
  if (temApiConfigurada() && !resultado.remotoOk) {
    mostrarToastSwal(resultado.erro || t("pos.toast.closeRegisterApiFailed"), "error");
    processandoFechoCaixa.value = false;
    return;
  }

  let mensagemSucesso = offlineStore.totalPendentes
    ? t("pos.toast.closeCompleteOfflinePending", { count: offlineStore.totalPendentes })
    : t("pos.toast.closeComplete");
  if (window.api?.imprimirRelatorioFecho && configuracaoStore.impressoraPadrao) {
    const impressao = await enviarRelatorioFechoParaImpressao({
      relatorio,
      configuracao: configuracaoStore,
      opcoes: {
        copies: 1,
        corteAutomatico: !!configuracaoStore.corteAutomatico,
      },
    });
    if (!impressao?.ok) {
      mensagemSucesso = impressao?.error
        ? t("pos.toast.closeCompletePrintFailed", { error: impressao.error })
        : t("pos.toast.closeCompletePrintFailedGeneric");
      modalFechoCaixa.value = false;
      mostrarToastSwal(mensagemSucesso, "warning");
      processandoFechoCaixa.value = false;
      return;
    }
    mensagemSucesso = t("pos.toast.closeCompletePrinted", { printer: configuracaoStore.impressoraPadrao });
  } else if (window.api?.imprimirRelatorioFecho && !configuracaoStore.impressoraPadrao) {
    mensagemSucesso = t("pos.toast.closeCompleteNoPrinter");
  }

  modalFechoCaixa.value = false;
  mostrarToastSwal(mensagemSucesso, "success");
  processandoFechoCaixa.value = false;
}
</script>

<template>
  <section class="grid h-full grid-cols-1 gap-4 xl:grid-cols-[2.2fr_1fr] xl:items-center">
    <div v-if="menuPosAtivo === 'venda'" class="space-y-4 xl:flex xl:h-full xl:flex-col xl:justify-center">
      <div class="rp-card flex h-[595px] flex-col p-4">
        <div class="mb-3 flex items-end justify-between gap-3">
          <div class="min-w-0 flex-1">
            <p class="mb-1 text-xl font-bold text-slate-800">{{ t("pos.catalog.title") }}</p>
            <label for="pos-pesquisa" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
              {{ t("pos.catalog.searchLabel") }}
            </label>
            <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white">
              <div class="flex w-16 items-center justify-center border-r border-slate-300 px-2 text-black" :title="t('pos.catalog.barcodeTitle')">
                <Barcode :size="32" :stroke-width="1.8" />
              </div>
              <input
                id="pos-pesquisa"
                ref="pesquisaInputRef"
                v-model="pesquisa"
                type="text"
                :placeholder="t('pos.catalog.searchPlaceholder')"
                class="min-w-0 flex-1 px-3 py-4 text-sm focus:outline-none"
                autocomplete="off"
                spellcheck="false"
                :autofocus="pesquisaDeveTerFoco"
                @keydown="aoTeclaCampoPesquisa"
              />
            </div>
          </div>
        </div>
        <div v-if="pesquisaAtiva" class="flex min-h-[332px] flex-1 flex-col overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
          <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2">{{ t("pos.catalog.product") }}</th>
                <th class="px-3 py-2">{{ t("pos.catalog.code") }}</th>
                <th class="px-3 py-2">{{ t("pos.catalog.price") }}</th>
                <th class="px-3 py-2">{{ t("pos.catalog.stock") }}</th>
                <th class="px-3 py-2 text-right">{{ t("pos.catalog.action") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="pesquisaEmCurso">
                <td colspan="5" class="px-3 py-8 text-center text-xs text-slate-500">
                  <span class="inline-flex items-center gap-2">
                    <LoaderCircle class="animate-spin" :size="14" />
                    {{ t("pos.catalog.searching") }}
                  </span>
                </td>
              </tr>
              <tr v-else-if="!resultadosPesquisa.length">
                <td colspan="5" class="px-3 py-8 text-center text-xs text-slate-500">{{ t("pos.catalog.noProducts") }}</td>
              </tr>
              <tr v-for="produto in resultadosPesquisaPaginados" :key="produto.id" class="border-t border-slate-100 text-[12px] hover:bg-slate-50">
                <td class="px-3 py-2 font-semibold text-slate-800">
                  {{ produto.nome }}
                  <span
                    v-if="vendidoPorPeso(produto)"
                    class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800"
                  >kg</span>
                </td>
                <td class="px-3 py-2 text-slate-600">{{ produto.codigoBarras }}</td>
                <td class="px-3 py-2 font-semibold text-slate-800">
                  {{ formatarMT(produto.precoVendaComIva ?? produto.precoVenda) }}
                  <span v-if="vendidoPorPeso(produto)" class="text-[10px] font-normal text-slate-500">/ kg</span>
                </td>
                <td class="px-3 py-2">
                  <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="produto.stock <= (vendidoPorPeso(produto) ? 1 : 10) ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'"
                  >
                    {{ formatarStockExibicao(produto.stock, produto.unidadeVenda, intlLocale(locale)) }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right">
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--gold)] text-black hover:brightness-95"
                    :title="t('pos.catalog.addToCart')"
                    :disabled="!sessaoStore.turnoAberto || produto.stock <= 0"
                    :class="!sessaoStore.turnoAberto || produto.stock <= 0 ? 'cursor-not-allowed opacity-40' : ''"
                    @click="void solicitarAdicaoProduto(produto)"
                  >
                    <span class="relative inline-flex h-4 w-4 items-center justify-center">
                      <ShoppingCartIcon :size="14" />
                      <Plus class="absolute -right-1 -top-1 rounded-full bg-[var(--gold)] text-black" :size="9" :stroke-width="2.4" />
                    </span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
          <div
            v-if="!pesquisaEmCurso && resultadosPesquisa.length"
            class="border-t border-slate-200 bg-slate-50 px-3 py-2"
          >
            <p class="text-[11px] text-slate-500">
              {{ t("pos.catalog.resultsCount", { count: totalResultadosPesquisa }) }}
            </p>
            <PaginacaoTabela
              v-if="totalPaginasPesquisa > 1"
              :pagina-atual="paginaPesquisaProdutos"
              :total-paginas="totalPaginasPesquisa"
              @mudar-pagina="aoMudarPaginaPesquisa"
            />
          </div>
        </div>
        <div
          v-else
          class="flex h-[332px] flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-slate-400"
        >
          <Search :size="58" :stroke-width="1.8" />
          <p class="text-sm font-medium text-slate-500">{{ t("pos.catalog.emptyHint") }}</p>
        </div>
      </div>
    </div>

    <div v-if="menuPosAtivo === 'venda'" class="space-y-4 xl:flex xl:h-full xl:flex-col xl:justify-center">
      <div class="rp-card h-[527px] overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-semibold text-slate-900">{{ t("pos.preview.title") }}</h3>
        </div>
        <div class="bg-slate-50 p-4">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="mb-3 flex items-start justify-between">
              <div>
                <p class="font-serif text-lg font-bold text-slate-900">RetailPro</p>
                <p class="text-[11px] text-slate-500">NUIT: 400000099</p>
              </div>
              <div class="text-right">
                <p class="font-semibold">{{ t("pos.preview.sale") }}</p>
              </div>
            </div>
            <div class="mb-2 border-t-2 border-[var(--gold)] pt-2 text-[11px]">
              <p class="text-slate-500">{{ t("common.client") }}</p>
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
                    :title="t('pos.preview.removeItem')"
                    @click="carrinhoStore.removerProduto(item.produtoId)"
                  >
                    <Trash2 :size="12" />
                  </button>
                </div>
                <div class="flex items-center justify-between gap-2 px-1">
                  <div class="flex items-center gap-1">
                    <span class="text-[11px] text-slate-500">
                      {{
                        item.unidadeVenda === "KG"
                          ? formatarQuantidadeExibicao(item.quantidade, "KG", intlLocale(locale))
                          : t("common.qty")
                      }}
                    </span>
                    <input
                      :data-pos-quantidade="item.produtoId"
                      :value="item.quantidade"
                      type="number"
                      :min="quantidadeMinima(item.unidadeVenda)"
                      :step="passoQuantidade(item.unidadeVenda)"
                      :inputmode="item.unidadeVenda === 'KG' ? 'decimal' : 'numeric'"
                      :max="produtoStore.produtos.find((p) => p.id === item.produtoId)?.stock || quantidadeMinima(item.unidadeVenda)"
                      class="w-20 rounded border border-slate-300 px-1.5 py-1 text-center text-xs font-semibold text-slate-900 focus:border-[var(--gold)] focus:outline-none"
                      autocomplete="off"
                      @focus="$event.target.select()"
                      @input="atualizarQuantidade(item.produtoId, $event.target.value)"
                      @blur="aoBlurQuantidadePreview(item.produtoId, $event.target.value)"
                      @keydown.enter="confirmarQuantidadePreview(item.produtoId, $event.target.value, $event)"
                    />
                  </div>
                  <span class="font-semibold">{{ formatarMT(item.subtotal) }}</span>
                </div>
              </div>
              <div v-if="!carrinhoStore.itens.length" class="flex flex-col items-center justify-center gap-2 text-slate-400">
                <ShoppingCart :size="40" :stroke-width="1.8" />
                <p class="text-xs font-medium text-slate-500">{{ t("pos.preview.emptyCart") }}</p>
              </div>
            </div>
            <div class="mt-3 border-t border-slate-200 pt-2 text-sm font-bold">
              <div class="mb-1 flex items-center justify-between text-xs font-medium text-slate-500">
                <span>{{ t("common.subtotal") }}:</span>
                <span>{{ formatarMT(carrinhoStore.subtotal) }}</span>
              </div>
              <div class="mb-1 flex items-center justify-between text-xs font-medium text-amber-700">
                <span>{{ t("common.discount") }}:</span>
                <span>- {{ formatarMT(descontoAplicado) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span>{{ t("common.total") }}:</span>
                <span>{{ formatarMT(carrinhoStore.total) }}</span>
              </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
              <button
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-600 text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!carrinhoStore.itens.length"
                :title="t('pos.preview.resetCart')"
                :aria-label="t('pos.preview.resetCart')"
                @click="limparCarrinhoAtual"
              >
                <RotateCcw :size="16" :stroke-width="2.2" />
              </button>
              <BotaoBase class="flex-1" variante="aviso" :disabled="!carrinhoStore.itens.length" @click="finalizarVenda">
                {{ t("pos.preview.finalizeSale") }}
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
            <h3 class="text-base font-bold text-slate-900">{{ t("pos.cashDashboard.title") }}</h3>
            <p class="text-xs text-slate-500">{{ t("pos.cashDashboard.subtitle") }}</p>
          </div>
          <button
            v-if="sessaoStore.turnoAberto"
            class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700"
            @click="abrirFechoCaixa"
          >
            {{ t("pos.cashDashboard.closeRegister") }}
          </button>
          <button v-else class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="modalAberturaCaixa = true">
            {{ t("pos.cashDashboard.openRegister") }}
          </button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ t("pos.cashDashboard.soldInShift") }}</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatarMT(totalVendidoTurno) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ t("pos.cashDashboard.expectedCash") }}</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatarMT(dinheiroEsperadoFecho) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ t("pos.cashDashboard.transactions") }}</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ totalTransacoesTurno }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ t("pos.cashDashboard.averageTicket") }}</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatarMT(ticketMedioTurno) }}</p>
          </div>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">{{ t("pos.cashDashboard.cashSales") }}</p>
            <p class="mt-1 text-base font-bold text-emerald-900">{{ formatarMT(totalDinheiroTurno) }}</p>
          </div>
          <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            <p class="text-xs font-semibold text-blue-800">{{ t("pos.cashDashboard.transferSales") }}</p>
            <p class="mt-1 text-base font-bold text-blue-900">{{ formatarMT(totalTransferenciaTurno) }}</p>
          </div>
        </div>

      </div>

      <div class="rp-card overflow-hidden p-4 xl:flex-1 xl:min-h-0">
        <div class="mb-3 flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="text-sm font-bold text-slate-900">{{ t("pos.cashDashboard.lastSalesTitle") }}</h3>
            <p class="text-xs text-slate-500">{{ t("pos.cashDashboard.lastSalesSubtitle") }}</p>
          </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 xl:flex xl:h-41 xl:min-h-0 xl:flex-col">
          <table class="w-full table-fixed text-xs">
            <thead class="bg-slate-50 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="w-[22%] px-2 py-1.5">{{ t("common.reference") }}</th>
                <th class="w-[21%] px-2 py-1.5">{{ t("common.date") }}</th>
                <th class="w-[21%] px-2 py-1.5">{{ t("common.client") }}</th>
                <th class="w-[14%] px-2 py-1.5">{{ t("common.payment") }}</th>
                <th class="w-[12%] px-2 py-1.5">{{ t("common.total") }}</th>
                <th class="w-[10%] px-2 py-1.5 text-right">{{ t("common.actions") }}</th>
              </tr>
            </thead>
          </table>

          <div class="max-h-28 overflow-y-auto overflow-x-hidden xl:max-h-28">
            <table class="w-full table-fixed text-xs">
              <tbody>
                <tr v-if="!ultimasVendasTurno.length">
                  <td colspan="6" class="px-2 py-5 text-center text-[11px] text-slate-500">{{ t("pos.cashDashboard.noSalesInShift") }}</td>
                </tr>
                <tr v-for="venda in ultimasVendasTurno" :key="venda.id" class="border-t border-slate-100 text-[11px] hover:bg-slate-50">
                  <td class="w-[22%] px-2 py-1.5 font-semibold text-slate-700">{{ venda.referencia || venda.id }}</td>
                  <td class="w-[21%] px-2 py-1.5 text-slate-600">{{ formatarData(venda.data) }}</td>
                  <td class="w-[21%] px-2 py-1.5 font-semibold text-slate-800">{{ venda.cliente }}</td>
                  <td class="w-[14%] px-2 py-1.5 text-slate-600">{{ traduzirMetodoPagamento(venda.metodoPagamento) }}</td>
                  <td class="w-[12%] px-2 py-1.5 font-semibold text-slate-800">{{ formatarMT(venda.total) }}</td>
                  <td class="w-[10%] px-2 py-1.5">
                    <div class="flex justify-end gap-1.5">
                      <button
                        class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-[var(--gold)] text-black hover:brightness-95"
                        :title="t('common.reprint')"
                        :aria-label="t('common.reprint')"
                        @click="reimprimirVenda(venda)"
                      >
                        <Printer :size="13" />
                      </button>
                      <button
                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                        :title="t('history.sales.requestReversal')"
                        :aria-label="t('history.sales.requestReversal')"
                        :disabled="!podeSolicitarReversao(venda, vendaStore.solicitacoesReversao)"
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

  <ModalBase
    :aberto="modalPesoAberto"
    :mostrar-fechar="true"
    :titulo="t('pos.weightModal.title')"
    @fechar="fecharModalPeso"
  >
    <div v-if="produtoPesoPendente" class="space-y-4 p-1">
      <p class="text-sm font-semibold text-slate-800">{{ produtoPesoPendente.nome }}</p>
      <p class="text-xs text-slate-500">
        {{ t("pos.weightModal.pricePerKg", { price: formatarMT(produtoPesoPendente.precoVendaComIva ?? produtoPesoPendente.precoVenda) }) }}
      </p>
      <p class="text-xs text-slate-500">
        {{ t("pos.weightModal.stockAvailable", { stock: formatarStockExibicao(produtoPesoPendente.stock, 'KG', intlLocale(locale)) }) }}
      </p>
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("pos.weightModal.kgLabel") }}</label>
        <input
          ref="inputKgPesoRef"
          v-model="quantidadeKgInput"
          type="number"
          min="0.001"
          step="0.001"
          inputmode="decimal"
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[var(--gold)] focus:outline-none"
          :placeholder="t('pos.weightModal.kgPlaceholder')"
          @keydown.enter.prevent="confirmarModalPeso"
        />
        <p class="mt-1 text-xs text-slate-500">{{ t("pos.weightModal.hint") }}</p>
      </div>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="secundario" @click="fecharModalPeso">{{ t("common.cancel") }}</BotaoBase>
        <BotaoBase @click="confirmarModalPeso">{{ t("pos.weightModal.confirm") }}</BotaoBase>
      </div>
    </div>
  </ModalBase>

  <ModalBase :aberto="modalAberturaCaixa" :mostrar-fechar="false" :titulo="t('pos.openingModal.title')">
    <div class="space-y-4">
      <p class="text-sm text-slate-600">
        {{ t("pos.openingModal.description") }}
      </p>
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("pos.openingModal.initialFund") }}</label>
        <input v-model.number="fundoInicialInput" type="number" min="0" class="rp-input" @keyup.enter="abrirTurnoCaixa" />
      </div>
      <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
        {{ t("pos.openingModal.userRegister", { user: sessaoStore.utilizador || t("common.operator"), register: sessaoStore.caixaAtribuido || t("login.register01") }) }}
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalAberturaCaixa = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>{{ t("common.cancel") }}</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" :disabled="processandoAberturaCaixa" @click="abrirTurnoCaixa">
          <span class="inline-flex items-center gap-1.5">
            <LoaderCircle v-if="processandoAberturaCaixa" class="animate-spin" :size="14" />
            <DoorOpen v-else :size="14" />
            <span>{{ processandoAberturaCaixa ? t("pos.openingModal.opening") : t("pos.openingModal.confirm") }}</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

  <ModalBase :aberto="modalFechoCaixa" :mostrar-fechar="false" :titulo="t('pos.closingModal.title')" @fechar="modalFechoCaixa = false">
    <div class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        <p><strong>{{ t("pos.closingModal.totalSold") }}</strong> {{ formatarMT(totalVendidoTurno) }}</p>
        <p><strong>{{ t("pos.closingModal.totalTransactions") }}</strong> {{ totalTransacoesTurno }}</p>
        <p><strong>{{ t("pos.closingModal.averageTicket") }}</strong> {{ formatarMT(ticketMedioTurno) }}</p>
        <p><strong>{{ t("pos.closingModal.cashSales") }}</strong> {{ formatarMT(totalDinheiroTurno) }}</p>
        <p><strong>{{ t("pos.closingModal.transferSales") }}</strong> {{ formatarMT(totalTransferenciaTurno) }}</p>
        <p><strong>{{ t("pos.closingModal.expectedCash") }}</strong> {{ formatarMT(dinheiroEsperadoFecho) }}</p>
      </div>

      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("pos.closingModal.actualCashLabel") }}</label>
        <input v-model.number="dinheiroRealFecho" type="number" min="0" class="rp-input" @keyup.enter="confirmarFechoCaixa" />
      </div>

      <div class="rounded-lg p-3 text-sm" :class="diferencaFecho >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ t("pos.closingModal.difference") }} <strong>{{ formatarMT(diferencaFecho) }}</strong>
      </div>

      <div v-if="diferencaFechoTemValor && diferencaFecho !== 0">
        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("pos.closingModal.justificationRequired") }}</label>
        <textarea
          v-model="justificativaDiferenca"
          rows="2"
          class="rp-input"
          :placeholder="t('pos.closingModal.justificationPlaceholder')"
        />
      </div>

    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalFechoCaixa = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>{{ t("common.cancel") }}</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" :disabled="processandoFechoCaixa" @click="confirmarFechoCaixa">
          <span class="inline-flex items-center gap-1.5">
            <LoaderCircle v-if="processandoFechoCaixa" class="animate-spin" :size="14" />
            <DoorClosed v-else :size="14" />
            <span>{{ processandoFechoCaixa ? t("pos.closingModal.closing") : t("pos.closingModal.confirm") }}</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

  <ModalBase :aberto="modalImpressaoAberto" :mostrar-fechar="false" :titulo="t('pos.checkoutModal.title')" @fechar="modalImpressaoAberto = false">
    <div v-if="vendaPendente" class="space-y-4">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("common.client") }}</label>
          <select v-model="cliente" class="rp-input">
            <option v-for="clienteItem in clientesParaSelect" :key="clienteItem.id + clienteItem.nome" :value="clienteItem.nome">
              {{ isGeneralClient(clienteItem.nome) ? t("common.generalClient") : clienteItem.nome }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("pos.checkoutModal.paymentMethod") }}</label>
          <select v-model="carrinhoStore.metodoPagamento" class="rp-input">
            <option value="Dinheiro">{{ t("pos.payment.cash") }}</option>
            <option value="Transferência">{{ t("pos.payment.transfer") }}</option>
          </select>
        </div>
      </div>

      <div class="rounded-lg bg-slate-900 p-4 text-center">
        <p class="text-[11px] uppercase tracking-widest text-slate-300">{{ t("pos.checkoutModal.totalToPay") }}</p>
        <p class="text-4xl font-black text-white">{{ formatarMT(carrinhoStore.total) }}</p>
      </div>

      <div
        v-if="temApiConfigurada()"
        class="rounded-lg border p-3 text-xs"
        :class="origemStockVenda.id ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700'"
      >
        <p class="font-semibold">{{ t("pos.checkoutModal.stockOrigin") }}</p>
        <p v-if="origemStockVenda.id">
          {{ origemStockVenda.nome || origemStockVenda.codigo || t("pos.checkoutModal.locationFallback", { id: origemStockVenda.id }) }}
        </p>
        <p v-else>
          {{ t("pos.checkoutModal.stockNotConfigured") }}
        </p>
      </div>

      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
        <div class="mb-2 flex items-center justify-between">
          <p class="text-xs font-semibold text-slate-700">{{ t("pos.checkoutModal.discountOptional") }}</p>
          <button
            class="rounded-md px-2 py-1 text-[11px] font-semibold"
            :class="descontoAtivo ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600'"
            @click="alternarDesconto"
          >
            {{ descontoAtivo ? t("pos.checkoutModal.discountActive") : t("pos.checkoutModal.discountInactive") }}
          </button>
        </div>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-[120px_1fr]">
          <select
            :value="carrinhoStore.descontoTipo"
            class="rp-input"
            :disabled="!descontoAtivo"
            @change="carrinhoStore.definirDesconto({ tipo: $event.target.value, valor: carrinhoStore.descontoValor })"
          >
            <option value="valor">{{ t("pos.checkoutModal.discountValue") }}</option>
            <option value="percentual">{{ t("pos.checkoutModal.discountPercent") }}</option>
          </select>
          <input
            :value="carrinhoStore.descontoValor"
            type="number"
            min="0"
            class="rp-input"
            :disabled="!descontoAtivo"
            :placeholder="carrinhoStore.descontoTipo === 'percentual' ? t('pos.checkoutModal.discountPlaceholderPercent') : t('pos.checkoutModal.discountPlaceholderValue')"
            @input="atualizarDesconto($event.target.value)"
          />
        </div>
      </div>

      <div class="space-y-2">
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("pos.checkoutModal.amountPaid") }}</label>
          <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white">
            <div class="flex items-center border-r border-slate-300 px-3 text-slate-500">{{ t("common.currency") }}</div>
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
            <span class="text-slate-500">{{ t("pos.checkoutModal.change") }}</span>
            <strong class="text-emerald-700">{{ formatarMT(troco) }}</strong>
          </div>
          <div class="mt-1 flex items-center justify-between">
            <span class="text-slate-500">{{ t("pos.checkoutModal.shortfall") }}</span>
            <strong class="text-red-600">{{ formatarMT(falta) }}</strong>
          </div>
        </div>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" :title="t('common.cancel')" :aria-label="t('common.cancel')" @click="modalImpressaoAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>{{ t("common.cancel") }}</span>
          </span>
        </BotaoBase>
        <BotaoBase :disabled="processandoConclusaoVenda || imprimindoAgora" variante="aviso" :title="t('pos.checkoutModal.finishWithoutPrint')" :aria-label="t('pos.checkoutModal.finishWithoutPrint')" @click="concluirVenda({ imprimir: false })">
          <Check :size="16" :stroke-width="2.2" />
        </BotaoBase>
        <BotaoBase
          :disabled="!configuracaoStore.impressoraPadrao || imprimindoAgora || processandoConclusaoVenda"
          variante="sucesso"
          :title="imprimindoAgora || processandoConclusaoVenda ? t('pos.checkoutModal.finishing') : t('pos.checkoutModal.printAndFinish')"
          :aria-label="imprimindoAgora || processandoConclusaoVenda ? t('pos.checkoutModal.finishing') : t('pos.checkoutModal.printAndFinish')"
          @click="concluirVenda({ imprimir: true })"
        >
          <LoaderCircle v-if="imprimindoAgora" class="animate-spin" :size="16" :stroke-width="2.2" />
          <Printer v-else :size="16" />
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

  <ModalBase :aberto="modalSolicitarReversaoAberto" :mostrar-fechar="false" :titulo="t('pos.reversalModal.title')" @fechar="modalSolicitarReversaoAberto = false">
    <div class="space-y-4">
      <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
          <TriangleAlert :size="14" />
        </span>
        <div>
          <p class="font-semibold text-slate-900">{{ t("pos.reversalModal.question") }}</p>
          <p class="text-xs text-slate-600">{{ t("pos.reversalModal.referenceLabel") }} {{ vendaParaReversao?.referencia || vendaParaReversao?.id }}</p>
        </div>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("pos.reversalModal.reasonOptional") }}</label>
        <textarea v-model="motivoReversao" rows="2" class="rp-input" :placeholder="t('pos.reversalModal.reasonPlaceholder')" />
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalSolicitarReversaoAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>{{ t("common.cancel") }}</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="sucesso" :disabled="processandoReversao" @click="confirmarSolicitacaoReversao">
          <span class="inline-flex items-center gap-1.5">
            <Check :size="14" />
            <span>{{ t("pos.reversalModal.confirm") }}</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

</template>
