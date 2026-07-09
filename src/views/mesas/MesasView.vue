<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useI18n } from "vue-i18n";
import BotaoBase from "../../components/BotaoBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import { useMesaStore } from "../../store/useMesaStore";
import { useProdutoStore } from "../../store/useProdutoStore";
import { useVendaStore } from "../../store/useVendaStore";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";
import { useSessaoStore } from "../../store/useSessaoStore";
import { temApiConfigurada } from "../../api";
import { temCatalogoOffline } from "../../services/offline/catalogCache";
import { redeDisponivel } from "../../services/offline/networkError";
import { mostrarToastSwal } from "../../services/toast";
import { enviarTalaoParaImpressao, abrirGavetaPosVenda } from "../../services/talaoImpressao";
import { intlLocale } from "../../services/localeStorage.js";
import {
  formatarQuantidadeExibicao,
  normalizarQuantidadeVenda,
  parseQuantidadeTexto,
  quantidadeMinima,
  UNIDADE_VENDA_KG,
  vendidoPorPeso,
} from "../../utils/produtoQuantidade";
import {
  ArrowRightLeft,
  Banknote,
  Check,
  DoorClosed,
  LoaderCircle,
  Plus,
  Printer,
  Search,
  Trash2,
  UtensilsCrossed,
  X,
} from "lucide-vue-next";

const { t, locale } = useI18n();
const mesaStore = useMesaStore();
const produtoStore = useProdutoStore();
const vendaStore = useVendaStore();
const configuracaoStore = useConfiguracaoStore();
const sessaoStore = useSessaoStore();

const { mesasOrdenadas, pedidoSeleccionado, mesaSeleccionada, totalPedido } = storeToRefs(mesaStore);

const pesquisa = ref("");
const modalAbrirMesa = ref(false);
const modalCriarMesa = ref(false);
const modalTransferir = ref(false);
const modalPagar = ref(false);
const modalQuantidade = ref(false);
const produtoPendente = ref(null);
const quantidadeInput = ref("");
const inputQuantidadeRef = ref(null);
const descricaoAbertura = ref("");
const codigoNovaMesa = ref("");
const nomeNovaMesa = ref("");
const mesaDestinoTransferencia = ref("");
const itensSeleccionadosTransferencia = ref([]);
const metodoPagamento = ref("Dinheiro");
const valorPagoInteiro = ref("0");
const valorPagoDecimal = ref("00");
const processando = ref(false);
const imprimindo = ref(false);

const resultadosPesquisa = computed(() => produtoStore.resultadosPesquisa.slice(0, 12));

const valorPagoNumerico = computed(() => {
  const inteiro = Number(valorPagoInteiro.value || 0);
  const decimal = Number(valorPagoDecimal.value || 0);
  return Number(`${inteiro}.${String(decimal).padStart(2, "0")}`);
});

const troco = computed(() => Math.max(0, valorPagoNumerico.value - totalPedido.value));

const mesasDestinoTransferencia = computed(() =>
  mesasOrdenadas.value.filter((mesa) => mesa.id !== mesaStore.mesaSeleccionadaId)
);

const pedidoTemConsumo = computed(() => (pedidoSeleccionado.value?.itens?.length || 0) > 0);

const classeBotaoIcone =
  "inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45";
const classeBotaoIconePrimario =
  "inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--gold)] text-black transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-45";

function formatarMoeda(valor) {
  return new Intl.NumberFormat(intlLocale(locale.value), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(valor || 0));
}

function classeMesa(mesa) {
  const ocupada = !!mesa.ocupada;
  const seleccionada = mesaStore.mesaSeleccionadaId === mesa.id;
  if (seleccionada) {
    return ocupada
      ? "border-[var(--gold)] bg-[color:rgba(216,182,90,0.14)]"
      : "border-cyan-500 bg-cyan-50";
  }
  return ocupada
    ? "border-amber-400 bg-amber-50 hover:border-amber-500"
    : "border-[var(--border)] bg-[var(--panel)] hover:border-slate-400";
}

function seleccionarMesa(mesa) {
  mesaStore.seleccionarMesa(mesa.id);
}

function abrirModalAbertura() {
  if (!mesaSeleccionada.value) return;
  mesaStore.deduplicarMesasEstado();
  mesaStore.reconciliarPedidosComMesas();
  const mesa = mesaStore.mesaSeleccionada;
  if (!mesa) return;
  if (mesaStore.obterPedidoDaMesa(mesa.id)) return;
  descricaoAbertura.value = "";
  modalAbrirMesa.value = true;
}

function confirmarAberturaMesa() {
  try {
    mesaStore.deduplicarMesasEstado();
    const mesa = mesaStore.mesaSeleccionada;
    if (!mesa) {
      mostrarToastSwal(t("mesas.toast.openFailed"), "error");
      return;
    }
    mesaStore.abrirPedido(mesa.id, descricaoAbertura.value);
    modalAbrirMesa.value = false;
    mostrarToastSwal(t("mesas.toast.opened"), "success");
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("mesas.toast.openFailed"), "error");
  }
}

async function criarMesa() {
  processando.value = true;
  try {
    const resultado = await mesaStore.criarMesa({
      codigo: codigoNovaMesa.value,
      nome: nomeNovaMesa.value,
    });
    codigoNovaMesa.value = "";
    nomeNovaMesa.value = "";
    modalCriarMesa.value = false;
    if (resultado.jaExistia) {
      mostrarToastSwal(t("mesas.toast.tableAlreadyExists"), "info");
    } else {
      mostrarToastSwal(t("mesas.toast.tableCreated"), "success");
    }
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("mesas.toast.tableCreateFailed"), "error");
  } finally {
    processando.value = false;
  }
}

let debouncePesquisa = null;
watch(pesquisa, (termo) => {
  clearTimeout(debouncePesquisa);
  debouncePesquisa = setTimeout(async () => {
    const valor = String(termo || "").trim();
    if (!valor) {
      produtoStore.limparPesquisa();
      return;
    }
    await produtoStore.buscarProdutos(
      sessaoStore.sourceLocationId
        ? { search: valor, source_location_id: sessaoStore.sourceLocationId }
        : { search: valor }
    );
  }, 250);
});

function adicionarProdutoComQuantidade(produto, quantidade) {
  if (!pedidoSeleccionado.value) return;
  const quantidadeNormalizada =
    normalizarQuantidadeVenda(quantidade, produto.unidadeVenda) ?? quantidadeMinima(produto.unidadeVenda);
  if (!quantidadeNormalizada) {
    mostrarToastSwal(t("mesas.toast.invalidQuantity"), "error");
    return;
  }
  try {
    mesaStore.adicionarProduto(mesaStore.mesaSeleccionadaId, produto, quantidadeNormalizada);
    pesquisa.value = "";
    produtoStore.limparPesquisa();
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("mesas.toast.addItemFailed"), "error");
  }
}

async function solicitarAdicaoProduto(produto) {
  if (!pedidoSeleccionado.value) return;
  produtoPendente.value = produto;
  const minimo = quantidadeMinima(produto.unidadeVenda);
  quantidadeInput.value = vendidoPorPeso(produto) ? "" : String(minimo);
  modalQuantidade.value = true;
  await nextTick();
  inputQuantidadeRef.value?.focus();
}

function fecharModalQuantidade() {
  modalQuantidade.value = false;
  produtoPendente.value = null;
  quantidadeInput.value = "";
}

function confirmarQuantidadeProduto() {
  const produto = produtoPendente.value;
  if (!produto) return;

  const unidade = vendidoPorPeso(produto) ? UNIDADE_VENDA_KG : produto.unidadeVenda;
  const quantidade = parseQuantidadeTexto(quantidadeInput.value, unidade);
  if (!quantidade) {
    mostrarToastSwal(
      vendidoPorPeso(produto) ? t("pos.weightModal.invalidKg") : t("mesas.toast.invalidQuantity"),
      "error"
    );
    return;
  }

  fecharModalQuantidade();
  adicionarProdutoComQuantidade(produto, quantidade);
}

function formatarQuantidadeItem(item) {
  return formatarQuantidadeExibicao(item.quantidade, item.unidadeVenda, intlLocale(locale.value));
}

function removerItem(itemId) {
  mesaStore.removerItem(mesaStore.mesaSeleccionadaId, itemId);
}

function abrirModalTransferir() {
  if (!pedidoSeleccionado.value?.itens?.length) return;
  mesaDestinoTransferencia.value = "";
  itensSeleccionadosTransferencia.value = pedidoSeleccionado.value.itens.map((item) => item.id);
  modalTransferir.value = true;
}

function alternarItemTransferencia(itemId) {
  if (itensSeleccionadosTransferencia.value.includes(itemId)) {
    itensSeleccionadosTransferencia.value = itensSeleccionadosTransferencia.value.filter((id) => id !== itemId);
  } else {
    itensSeleccionadosTransferencia.value = [...itensSeleccionadosTransferencia.value, itemId];
  }
}

function confirmarTransferencia() {
  try {
    const pedido = pedidoSeleccionado.value;
    if (!pedido?.itens?.length) {
      mostrarToastSwal(t("mesas.toast.transferFailed"), "error");
      return;
    }

    mesaStore.transferirItens({
      deMesaId: pedido.mesaId || mesaStore.mesaSeleccionadaId,
      paraMesaId: mesaDestinoTransferencia.value,
      itemIds: itensSeleccionadosTransferencia.value,
    });
    modalTransferir.value = false;
    mostrarToastSwal(t("mesas.toast.transferred"), "success");
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("mesas.toast.transferFailed"), "error");
  }
}

function abrirModalPagar() {
  if (!pedidoTemConsumo.value) return;
  metodoPagamento.value = "Dinheiro";
  const total = totalPedido.value;
  valorPagoInteiro.value = String(Math.floor(total));
  valorPagoDecimal.value = String(Math.round((total % 1) * 100)).padStart(2, "0");
  modalPagar.value = true;
}

function fecharMesa() {
  if (!pedidoSeleccionado.value) return;
  if (pedidoTemConsumo.value) {
    mostrarToastSwal(t("mesas.toast.payRequiredToClose"), "warning");
    return;
  }

  mesaStore.fecharPedidoLocal(pedidoSeleccionado.value.mesaId);
  mostrarToastSwal(t("mesas.toast.closed"), "success");
}

async function imprimirConta(opcoes = { pagarDepois: true }) {
  if (!pedidoSeleccionado.value?.itens?.length) return;
  if (!configuracaoStore.impressoraPadrao) {
    mostrarToastSwal(t("pos.toast.setPrinterToPrint"), "error");
    return;
  }

  imprimindo.value = true;
  try {
    const venda = mesaStore.montarVendaDoPedido(pedidoSeleccionado.value, {
      metodoPagamento: metodoPagamento.value,
      valorPago: valorPagoNumerico.value,
      troco: troco.value,
    });
    const resultado = await enviarTalaoParaImpressao({
      venda,
      configuracao: configuracaoStore,
      opcoes: {
        detalharIva: false,
        titulo: t("mesas.receipt.title"),
        copies: 1,
        corteAutomatico: !!configuracaoStore.corteAutomatico,
        abrirGaveta: false,
      },
    });
    if (!resultado?.ok) {
      mostrarToastSwal(resultado?.error || t("pos.toast.printFailed"), "error");
      return;
    }
    if (!opcoes.pagarDepois) {
      mostrarToastSwal(t("mesas.toast.printed"), "success");
    }
  } finally {
    imprimindo.value = false;
  }
}

async function confirmarPagamento() {
  if (!pedidoSeleccionado.value?.itens?.length) return;
  if (!sessaoStore.turnoAberto) {
    mostrarToastSwal(t("mesas.toast.shiftRequired"), "error");
    return;
  }
  if (metodoPagamento.value === "Dinheiro" && valorPagoNumerico.value < totalPedido.value) {
    mostrarToastSwal(t("pos.toast.insufficientPayment"), "error");
    return;
  }
  if (temApiConfigurada() && !temCatalogoOffline() && !redeDisponivel()) {
    mostrarToastSwal(t("pos.toast.offlineCatalogRequired"), "error");
    return;
  }

  processando.value = true;
  try {
    const pedido = { ...pedidoSeleccionado.value };
    const venda = mesaStore.montarVendaDoPedido(pedido, {
      metodoPagamento: metodoPagamento.value,
      valorPago: valorPagoNumerico.value,
      troco: troco.value,
    });

    produtoStore.aplicarVenda(venda.itens, sessaoStore.sourceLocationId ? { source_location_id: sessaoStore.sourceLocationId } : {});

    let resultadoRegisto;
    try {
      if (produtoStore.usarStockLocalPos) {
        resultadoRegisto = vendaStore.registarVendaPosRapida(venda);
      } else {
        resultadoRegisto = await vendaStore.registarVenda(venda);
      }
    } catch (erro) {
      produtoStore.reporStockItensVenda(venda.itens);
      throw erro;
    }

    mesaStore.fecharPedidoLocal(pedido.mesaId, venda.id);
    modalPagar.value = false;
    mesaStore.seleccionarMesa(null);

    await imprimirConta({ pagarDepois: false });
    await abrirGavetaPosVenda({ venda, configuracao: configuracaoStore });

    const modo = resultadoRegisto?.modo || "local";
    if (modo === "offline") {
      mostrarToastSwal(t("mesas.toast.paidOffline"), "warning");
    } else {
      mostrarToastSwal(t("mesas.toast.paid"), "success");
    }
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("mesas.toast.payFailed"), "error");
  } finally {
    processando.value = false;
  }
}

onMounted(() => {
  sessaoStore.hidratar();
  mesaStore.hidratarLocal();
  mesaStore.agendarSincronizacaoMesas();
  window.addEventListener("retailpro:pos-sync-background", aoSyncBackground);
});

onUnmounted(() => {
  window.removeEventListener("retailpro:pos-sync-background", aoSyncBackground);
});

function aoSyncBackground() {
  mesaStore.agendarSincronizacaoMesas(0);
}
</script>

<template>
  <div class="flex h-full min-h-0 flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ t("mesas.title") }}</h2>
        <p class="text-sm text-slate-600">{{ t("mesas.subtitle") }}</p>
      </div>
      <button
        type="button"
        :class="classeBotaoIcone"
        :title="t('mesas.actions.newTable')"
        :aria-label="t('mesas.actions.newTable')"
        @click="modalCriarMesa = true"
      >
        <Plus :size="18" />
      </button>
    </div>

    <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(280px,360px)_1fr]">
      <section class="min-h-0 overflow-auto rounded-xl border border-[var(--border)] bg-[var(--panel)] p-3">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t("mesas.tables") }}</p>
        <div v-if="!mesasOrdenadas.length" class="rounded-lg border border-dashed border-[var(--border)] p-6 text-center text-sm text-slate-400">
          {{ t("mesas.emptyTables") }}
        </div>
        <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3">
          <button
            v-for="mesa in mesasOrdenadas"
            :key="mesa.id"
            type="button"
            class="rounded-xl border p-3 text-left transition"
            :class="classeMesa(mesa)"
            @click="seleccionarMesa(mesa)"
          >
            <div class="flex items-center gap-2">
              <UtensilsCrossed :size="16" class="text-[var(--gold)]" />
              <span class="font-semibold text-slate-900">{{ mesa.codigo }}</span>
            </div>
            <p v-if="mesa.nome" class="mt-1 truncate text-xs text-slate-600">{{ mesa.nome }}</p>
            <p class="mt-2 text-[11px] font-medium" :class="mesa.ocupada ? 'text-amber-700' : 'text-emerald-700'">
              {{ mesa.ocupada ? t("mesas.status.occupied") : t("mesas.status.free") }}
            </p>
          </button>
        </div>
      </section>

      <section class="flex min-h-0 flex-col rounded-xl border border-[var(--border)] bg-[var(--panel)] p-4">
        <template v-if="!mesaSeleccionada">
          <div class="flex flex-1 items-center justify-center text-center text-slate-500">
            <div>
              <UtensilsCrossed :size="40" class="mx-auto mb-3 opacity-40" />
              <p>{{ t("mesas.selectTable") }}</p>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-[var(--border)] pb-4">
            <div>
              <h3 class="text-xl font-bold text-slate-900">{{ mesaSeleccionada.codigo }}</h3>
              <p v-if="mesaSeleccionada.nome" class="text-sm text-slate-600">{{ mesaSeleccionada.nome }}</p>
              <p v-if="pedidoSeleccionado?.descricao" class="mt-1 text-sm text-slate-700">{{ pedidoSeleccionado.descricao }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <BotaoBase v-if="!pedidoSeleccionado" @click="abrirModalAbertura">{{ t("mesas.actions.open") }}</BotaoBase>
              <template v-else>
                <button
                  type="button"
                  :class="classeBotaoIcone"
                  :title="t('mesas.actions.print')"
                  :aria-label="t('mesas.actions.print')"
                  :disabled="imprimindo || !pedidoTemConsumo"
                  @click="imprimirConta"
                >
                  <LoaderCircle v-if="imprimindo" :size="18" class="animate-spin" />
                  <Printer v-else :size="18" />
                </button>
                <button
                  type="button"
                  :class="classeBotaoIcone"
                  :title="t('mesas.actions.transfer')"
                  :aria-label="t('mesas.actions.transfer')"
                  :disabled="!pedidoTemConsumo"
                  @click="abrirModalTransferir"
                >
                  <ArrowRightLeft :size="18" />
                </button>
                <button
                  type="button"
                  :class="classeBotaoIconePrimario"
                  :title="t('mesas.actions.pay')"
                  :aria-label="t('mesas.actions.pay')"
                  :disabled="!pedidoTemConsumo"
                  @click="abrirModalPagar"
                >
                  <Banknote :size="18" />
                </button>
                <button
                  type="button"
                  :class="classeBotaoIcone"
                  :title="pedidoTemConsumo ? t('mesas.actions.closeRequiresPay') : t('mesas.actions.close')"
                  :aria-label="t('mesas.actions.close')"
                  @click="fecharMesa"
                >
                  <DoorClosed :size="18" />
                </button>
              </template>
            </div>
          </div>

          <template v-if="pedidoSeleccionado">
            <div class="mb-4">
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t("mesas.addProducts") }}</label>
              <div
                class="flex overflow-hidden rounded-lg border border-[var(--border)] bg-white focus-within:border-[#c8ab5b] focus-within:shadow-[0_0_0_3px_rgba(216,182,90,0.18)]"
              >
                <div
                  class="flex w-10 shrink-0 items-center justify-center border-r border-[var(--border)] text-slate-500"
                  :title="t('mesas.searchPlaceholder')"
                >
                  <Search :size="16" />
                </div>
                <input
                  v-model="pesquisa"
                  type="text"
                  class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
                  :placeholder="t('mesas.searchPlaceholder')"
                />
              </div>
              <div v-if="resultadosPesquisa.length" class="mt-2 grid gap-1 sm:grid-cols-2">
                <button
                  v-for="produto in resultadosPesquisa"
                  :key="produto.id"
                  type="button"
                  class="flex items-center justify-between rounded-lg border border-[var(--border)] px-3 py-2 text-left text-sm hover:border-[var(--gold)]"
                  @click="solicitarAdicaoProduto(produto)"
                >
                  <span class="truncate text-slate-800">{{ produto.nome }}</span>
                  <span class="ml-2 shrink-0 text-[var(--gold)]">{{ formatarMoeda(produto.precoVendaComIva ?? produto.precoVenda) }}</span>
                </button>
              </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-[var(--border)]">
              <table class="w-full text-sm">
                <thead class="sticky top-0 bg-[var(--panel-muted)] text-left text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-3 py-2">{{ t("mesas.columns.item") }}</th>
                    <th class="px-3 py-2 text-right">{{ t("mesas.columns.qty") }}</th>
                    <th class="px-3 py-2 text-right">{{ t("mesas.columns.total") }}</th>
                    <th class="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!pedidoSeleccionado.itens.length">
                    <td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ t("mesas.emptyOrder") }}</td>
                  </tr>
                  <tr v-for="item in pedidoSeleccionado.itens" :key="item.id" class="border-t border-[var(--border)]">
                    <td class="px-3 py-2 text-slate-800">{{ item.nome }}</td>
                    <td class="px-3 py-2 text-right text-slate-700">{{ formatarQuantidadeItem(item) }}</td>
                    <td class="px-3 py-2 text-right font-medium text-[var(--gold)]">{{ formatarMoeda(item.subtotal) }}</td>
                    <td class="px-3 py-2 text-right">
                      <button type="button" class="text-red-600 hover:text-red-700" @click="removerItem(item.id)">
                        <Trash2 :size="15" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex items-center justify-between rounded-lg bg-[var(--panel-muted)] px-4 py-3">
              <span class="text-sm text-slate-600">{{ t("mesas.total") }}</span>
              <span class="text-2xl font-bold text-[var(--gold)]">{{ formatarMoeda(totalPedido) }} MT</span>
            </div>
          </template>
        </template>
      </section>
    </div>

    <ModalBase v-model:aberto="modalAbrirMesa" :titulo="t('mesas.modals.openTitle')">
      <div class="space-y-4">
        <label class="rp-label">
          {{ t("mesas.modals.description") }}
          <input
            v-model="descricaoAbertura"
            type="text"
            class="rp-input"
            :placeholder="t('mesas.modals.descriptionPlaceholder')"
          />
        </label>
        <div class="flex justify-end gap-2">
          <BotaoBase variante="secundario" @click="modalAbrirMesa = false">{{ t("common.cancel") }}</BotaoBase>
          <BotaoBase @click="confirmarAberturaMesa">{{ t("mesas.actions.open") }}</BotaoBase>
        </div>
      </div>
    </ModalBase>

    <ModalBase v-model:aberto="modalCriarMesa" :titulo="t('mesas.modals.createTitle')">
      <div class="space-y-4">
        <label class="rp-label">
          {{ t("mesas.modals.code") }}
          <input v-model="codigoNovaMesa" type="text" class="rp-input uppercase" />
        </label>
        <label class="rp-label">
          {{ t("mesas.modals.name") }}
          <input v-model="nomeNovaMesa" type="text" class="rp-input" />
        </label>
        <div class="flex justify-end gap-2">
          <BotaoBase variante="secundario" @click="modalCriarMesa = false">{{ t("common.cancel") }}</BotaoBase>
          <BotaoBase :disabled="processando" @click="criarMesa">
            <LoaderCircle v-if="processando" :size="16" class="animate-spin" />
            {{ t("mesas.actions.create") }}
          </BotaoBase>
        </div>
      </div>
    </ModalBase>

    <ModalBase v-model:aberto="modalTransferir" :titulo="t('mesas.modals.transferTitle')">
      <div class="space-y-4">
        <label class="rp-label">
          {{ t("mesas.modals.destination") }}
          <select v-model="mesaDestinoTransferencia" class="rp-select">
            <option value="">{{ t("mesas.modals.selectDestination") }}</option>
            <option v-for="mesa in mesasDestinoTransferencia" :key="mesa.id" :value="mesa.id">
              {{ mesa.codigo }} {{ mesa.nome ? `- ${mesa.nome}` : "" }}
            </option>
          </select>
        </label>
        <div>
          <p class="mb-2 text-sm font-medium text-slate-700">{{ t("mesas.modals.items") }}</p>
          <div class="max-h-48 space-y-1 overflow-auto rounded-lg border border-[var(--border)] p-2">
            <label
              v-for="item in pedidoSeleccionado?.itens || []"
              :key="item.id"
              class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-slate-50"
            >
              <input type="checkbox" :checked="itensSeleccionadosTransferencia.includes(item.id)" @change="alternarItemTransferencia(item.id)" />
              <span class="flex-1 text-sm text-slate-800">{{ item.nome }}</span>
              <span class="text-xs text-slate-500">x{{ item.quantidade }}</span>
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <BotaoBase variante="secundario" @click="modalTransferir = false">{{ t("common.cancel") }}</BotaoBase>
          <BotaoBase :disabled="!mesaDestinoTransferencia || !itensSeleccionadosTransferencia.length" @click="confirmarTransferencia">
            {{ t("mesas.actions.transfer") }}
          </BotaoBase>
        </div>
      </div>
    </ModalBase>

    <ModalBase v-model:aberto="modalPagar" :titulo="t('mesas.modals.payTitle')">
      <div class="space-y-4">
        <div class="rounded-lg bg-[var(--panel-muted)] px-4 py-3 text-center">
          <p class="text-sm text-slate-600">{{ t("mesas.total") }}</p>
          <p class="text-3xl font-bold text-[var(--gold)]">{{ formatarMoeda(totalPedido) }} MT</p>
        </div>
        <label class="rp-label">
          {{ t("common.payment") }}
          <select v-model="metodoPagamento" class="rp-select">
            <option value="Dinheiro">{{ t("pos.payment.cash") }}</option>
            <option value="Transferência">{{ t("pos.payment.transfer") }}</option>
          </select>
        </label>
        <div v-if="metodoPagamento === 'Dinheiro'" class="grid grid-cols-2 gap-2">
          <label class="rp-label">
            {{ t("pos.receipt.labels.amountPaid") }}
            <input v-model="valorPagoInteiro" type="number" min="0" class="rp-input" />
          </label>
          <label class="rp-label">
            Cêntimos
            <input v-model="valorPagoDecimal" type="number" min="0" max="99" class="rp-input" />
          </label>
          <p class="col-span-2 text-sm text-slate-600">{{ t("pos.receipt.labels.change") }}: <span class="font-semibold text-emerald-700">{{ formatarMoeda(troco) }} MT</span></p>
        </div>
        <div class="flex justify-end gap-2">
          <BotaoBase variante="secundario" @click="modalPagar = false">{{ t("common.cancel") }}</BotaoBase>
          <BotaoBase :disabled="processando" @click="confirmarPagamento">
            <LoaderCircle v-if="processando" :size="16" class="animate-spin" />
            <Check v-else :size="16" />
            {{ t("mesas.actions.confirmPay") }}
          </BotaoBase>
        </div>
      </div>
    </ModalBase>

    <ModalBase
      v-model:aberto="modalQuantidade"
      :titulo="produtoPendente && vendidoPorPeso(produtoPendente) ? t('pos.weightModal.title') : t('mesas.modals.quantityTitle')"
      @fechar="fecharModalQuantidade"
    >
      <div v-if="produtoPendente" class="space-y-4">
        <p class="text-sm font-semibold text-slate-800">{{ produtoPendente.nome }}</p>
        <p class="text-xs text-slate-600">
          {{ formatarMoeda(produtoPendente.precoVendaComIva ?? produtoPendente.precoVenda) }} MT
        </p>
        <label class="rp-label">
          {{ vendidoPorPeso(produtoPendente) ? t("pos.weightModal.kgLabel") : t("mesas.modals.quantityLabel") }}
          <input
            ref="inputQuantidadeRef"
            v-model="quantidadeInput"
            type="number"
            :min="vendidoPorPeso(produtoPendente) ? 0.001 : 1"
            :step="vendidoPorPeso(produtoPendente) ? 0.001 : 1"
            :inputmode="vendidoPorPeso(produtoPendente) ? 'decimal' : 'numeric'"
            class="rp-input"
            :placeholder="vendidoPorPeso(produtoPendente) ? t('pos.weightModal.kgPlaceholder') : t('mesas.modals.quantityPlaceholder')"
            @keydown.enter.prevent="confirmarQuantidadeProduto"
          />
        </label>
        <p v-if="vendidoPorPeso(produtoPendente)" class="text-xs text-slate-500">{{ t("pos.weightModal.hint") }}</p>
        <div class="flex justify-end gap-2">
          <BotaoBase variante="secundario" @click="fecharModalQuantidade">{{ t("common.cancel") }}</BotaoBase>
          <BotaoBase @click="confirmarQuantidadeProduto">{{ t("mesas.modals.confirmAdd") }}</BotaoBase>
        </div>
      </div>
    </ModalBase>
  </div>
</template>
