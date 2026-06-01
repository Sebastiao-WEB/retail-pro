<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import BotaoBase from "../../components/BotaoBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import PaginacaoTabela from "../../components/PaginacaoTabela.vue";
import { useVendaStore } from "../../store/useVendaStore";
import { useSessaoStore } from "../../store/useSessaoStore";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";
import { mostrarToastSwal } from "../../services/toast";
import { enviarTalaoParaImpressao, formatarMT, formatarIva, obterIvaItem, obterTotalIvaVenda } from "../../services/talaoImpressao";
import { temApiConfigurada } from "../../api";
import { intlLocale } from "../../services/localeStorage.js";
import { Check, Eye, Printer, RotateCcw, TriangleAlert, X } from "lucide-vue-next";

const ITENS_POR_PAGINA = 10;

const { t, locale } = useI18n();
const vendaStore = useVendaStore();
const sessaoStore = useSessaoStore();
const configuracaoStore = useConfiguracaoStore();
const imprimindoAgora = ref(false);
const paginaAtual = ref(1);

const vendaSelecionada = ref(null);
const modalDetalhesAberto = ref(false);
const modalSolicitarReversaoAberto = ref(false);
const vendaParaReversao = ref(null);
const motivoReversao = ref("");

onMounted(async () => {
  configuracaoStore.hidratar();
  if (temApiConfigurada()) {
    try {
      await configuracaoStore.hidratarDadosEmpresaRemotos();
    } catch {
      // Mantem dados locais quando a API falhar.
    }
  }
});

const vendasDoTurnoCaixa = computed(() => {
  const caixaAtual = sessaoStore.caixaAtribuido;
  const abertura = sessaoStore.aberturaEm ? new Date(sessaoStore.aberturaEm).getTime() : null;
  if (!caixaAtual || !abertura) return [];
  return vendaStore.vendas
    .filter((venda) => {
      const dataVenda = new Date(venda.data).getTime();
      const mesmaCaixa = venda.caixa ? venda.caixa === caixaAtual : true;
      return mesmaCaixa && dataVenda >= abertura;
    })
    .sort((a, b) => new Date(b.data).getTime() - new Date(a.data).getTime());
});

const totalVendas = computed(() => vendasDoTurnoCaixa.value.length);
const totalPaginas = computed(() => Math.max(1, Math.ceil(totalVendas.value / ITENS_POR_PAGINA)));

const vendasPagina = computed(() => {
  const inicio = (paginaAtual.value - 1) * ITENS_POR_PAGINA;
  return vendasDoTurnoCaixa.value.slice(inicio, inicio + ITENS_POR_PAGINA);
});

const subtituloPagina = computed(() =>
  t("history.sales.subtitle", { total: totalVendas.value, perPage: ITENS_POR_PAGINA })
);

watch(totalPaginas, (total) => {
  if (paginaAtual.value > total) {
    paginaAtual.value = total;
  }
});

function irParaPagina(pagina) {
  paginaAtual.value = Math.min(Math.max(1, pagina), totalPaginas.value);
}
const solicitacoesPendentesPorVenda = computed(() => {
  const mapa = new Map();
  vendaStore.solicitacoesPendentes.forEach((item) => {
    mapa.set(item.vendaId, item);
  });
  return mapa;
});

function formatarData(valor) {
  return new Date(valor).toLocaleString(intlLocale(locale.value));
}

function traduzirMetodoPagamento(metodo) {
  if (metodo === "Dinheiro") return t("pos.payment.cash");
  if (metodo === "Transferência") return t("pos.payment.transfer");
  return metodo;
}

function abrirDetalhes(venda) {
  vendaSelecionada.value = venda;
  modalDetalhesAberto.value = true;
}

async function reimprimirVenda(venda) {
  if (imprimindoAgora.value) return;
  try {
    imprimindoAgora.value = true;
    const resultado = await enviarTalaoParaImpressao({
      venda,
      configuracao: configuracaoStore,
      opcoes: {
        segundaVia: true,
        detalharIva: true,
        copies: 1,
        corteAutomatico: true,
        abrirGaveta: false,
      },
    });
    if (!resultado?.ok) {
      mostrarToastSwal(resultado?.error || t("history.sales.toast.reprintFailed"), "error");
      return;
    }
    mostrarToastSwal(t("history.sales.toast.reprintSuccess", { printer: configuracaoStore.impressoraPadrao }), "success");
  } catch {
    mostrarToastSwal(t("history.sales.toast.reprintFailed"), "error");
  } finally {
    imprimindoAgora.value = false;
  }
}

function abrirSolicitacaoReversao(venda) {
  if (venda.estado === "Revertida") {
    mostrarToastSwal(t("history.sales.toast.alreadyReverted"), "error");
    return;
  }
  if (solicitacoesPendentesPorVenda.value.has(venda.id)) {
    mostrarToastSwal(t("history.sales.toast.reversalPending"), "error");
    return;
  }
  vendaParaReversao.value = venda;
  motivoReversao.value = "";
  modalSolicitarReversaoAberto.value = true;
}

async function solicitarReversao() {
  if (!vendaParaReversao.value) return;
  const venda = vendaParaReversao.value;
  const resultado = await vendaStore.solicitarReversao({
    vendaId: venda.id,
    referencia: venda.referencia || String(venda.id),
    solicitadoPor: sessaoStore.utilizador || t("common.operator"),
    motivo: motivoReversao.value.trim(),
  });
  if (!resultado.ok) {
    mostrarToastSwal(resultado.erro || t("history.sales.toast.reversalFailed"), "error");
    return;
  }
  modalSolicitarReversaoAberto.value = false;
  vendaParaReversao.value = null;
  mostrarToastSwal(t("history.sales.toast.reversalSent"), "success");
}
</script>

<template>
  <section class="space-y-4">
    <div class="rp-card p-4">
      <div class="mb-3 flex items-center justify-between border-b border-slate-200 pb-3">
        <div>
          <h3 class="text-base font-bold text-slate-900">{{ t("history.sales.title") }}</h3>
          <p class="text-xs text-slate-500">
            {{ t("history.sales.registerLabel") }} <strong>{{ sessaoStore.caixaAtribuido || t("common.noRegister") }}</strong> ·
            {{ t("history.sales.shiftLabel") }} <strong>{{ sessaoStore.aberturaEm ? formatarData(sessaoStore.aberturaEm) : t("history.sales.shiftNotStarted") }}</strong>
            · {{ subtituloPagina }}
          </p>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-3 py-2">{{ t("common.reference") }}</th>
              <th class="px-3 py-2">{{ t("common.date") }}</th>
              <th class="px-3 py-2">{{ t("common.client") }}</th>
              <th class="px-3 py-2">{{ t("common.payment") }}</th>
              <th class="px-3 py-2">{{ t("common.status") }}</th>
              <th class="px-3 py-2">{{ t("common.total") }}</th>
              <th class="px-3 py-2 text-right">{{ t("common.actions") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!vendasPagina.length">
              <td colspan="7" class="px-3 py-8 text-center text-xs text-slate-500">
                {{ t("history.sales.empty") }}
              </td>
            </tr>
            <tr v-for="venda in vendasPagina" :key="venda.id" class="border-t border-slate-100 text-[12px] hover:bg-slate-50">
              <td class="px-3 py-2 font-semibold text-slate-700">{{ venda.referencia || venda.id }}</td>
              <td class="px-3 py-2 text-slate-600">{{ formatarData(venda.data) }}</td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ venda.cliente }}</td>
              <td class="px-3 py-2 text-slate-600">{{ traduzirMetodoPagamento(venda.metodoPagamento) }}</td>
              <td class="px-3 py-2">
                <span
                  class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                  :class="
                    venda.estado === 'Revertida'
                      ? 'bg-red-100 text-red-700'
                      : solicitacoesPendentesPorVenda.has(venda.id)
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-emerald-100 text-emerald-700'
                  "
                >
                  {{ venda.estado === "Revertida" ? t("history.sales.statusReverted") : solicitacoesPendentesPorVenda.has(venda.id) ? t("history.sales.statusReversalPending") : t("history.sales.statusCompleted") }}
                </span>
              </td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ formatarMT(venda.total) }}</td>
              <td class="px-3 py-2">
                <div class="flex justify-end gap-2">
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                    :title="t('common.details')"
                    :aria-label="t('common.details')"
                    @click="abrirDetalhes(venda)"
                  >
                    <Eye :size="15" />
                  </button>
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                    :title="t('history.sales.requestReversal')"
                    :aria-label="t('history.sales.requestReversal')"
                    :disabled="venda.estado === 'Revertida' || solicitacoesPendentesPorVenda.has(venda.id)"
                    @click="abrirSolicitacaoReversao(venda)"
                  >
                    <RotateCcw :size="15" />
                  </button>
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--gold)] text-black hover:brightness-95"
                    :title="t('common.reprint')"
                    :aria-label="t('common.reprint')"
                    @click="reimprimirVenda(venda)"
                  >
                    <Printer :size="15" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginacaoTabela
        v-if="totalVendas > 0"
        :pagina-atual="paginaAtual"
        :total-paginas="totalPaginas"
        @mudar-pagina="irParaPagina"
      />
    </div>
  </section>

  <ModalBase :aberto="modalDetalhesAberto" :mostrar-fechar="false" :titulo="t('history.sales.detailsModal.title')" @fechar="modalDetalhesAberto = false">
    <div v-if="vendaSelecionada" class="space-y-3">
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        <p><strong>{{ t("common.date") }}:</strong> {{ formatarData(vendaSelecionada.data) }}</p>
        <p><strong>{{ t("common.client") }}:</strong> {{ vendaSelecionada.cliente }}</p>
        <p><strong>{{ t("common.register") }}:</strong> {{ vendaSelecionada.caixa || sessaoStore.caixaAtribuido }}</p>
        <p><strong>{{ t("common.operator") }}:</strong> {{ vendaSelecionada.operador || sessaoStore.utilizador }}</p>
        <p><strong>{{ t("common.method") }}:</strong> {{ traduzirMetodoPagamento(vendaSelecionada.metodoPagamento) }}</p>
      </div>

      <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-3 py-2">{{ t("common.item") }}</th>
              <th class="px-3 py-2">{{ t("common.qty") }}</th>
              <th class="px-3 py-2">{{ t("common.ivaPercent") }}</th>
              <th class="px-3 py-2">{{ t("common.ivaAmount") }}</th>
              <th class="px-3 py-2">{{ t("common.subtotal") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in vendaSelecionada.itens || []" :key="idx" class="border-t border-slate-100">
              <td class="px-3 py-2">{{ item.nome }}</td>
              <td class="px-3 py-2">{{ item.quantidade }}</td>
              <td class="px-3 py-2">{{ formatarIva(item.ivaPercentual) }}</td>
              <td class="px-3 py-2">{{ formatarMT(obterIvaItem(item)) }}</td>
              <td class="px-3 py-2">{{ formatarMT(item.subtotal) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        <p><strong>{{ t("common.subtotal") }}:</strong> {{ formatarMT(vendaSelecionada.subtotal || vendaSelecionada.total) }}</p>
        <p><strong>{{ t("history.sales.detailsModal.totalIva") }}</strong> {{ formatarMT(obterTotalIvaVenda(vendaSelecionada)) }}</p>
        <p><strong>{{ t("common.discount") }}:</strong> - {{ formatarMT(vendaSelecionada.descontoAplicado || 0) }}</p>
        <p><strong>{{ t("common.total") }}:</strong> {{ formatarMT(vendaSelecionada.total) }}</p>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end">
        <BotaoBase variante="perigo" @click="modalDetalhesAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>{{ t("common.cancel") }}</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

  <ModalBase :aberto="modalSolicitarReversaoAberto" :mostrar-fechar="false" :titulo="t('history.sales.reversalModal.title')" @fechar="modalSolicitarReversaoAberto = false">
    <div class="space-y-4">
      <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
          <TriangleAlert :size="14" />
        </span>
        <div>
          <p class="font-semibold text-slate-900">{{ t("history.sales.reversalModal.question") }}</p>
          <p class="text-xs text-slate-600">{{ t("history.sales.reversalModal.referenceLabel") }} {{ vendaParaReversao?.referencia || vendaParaReversao?.id }}</p>
        </div>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("history.sales.reversalModal.reasonOptional") }}</label>
        <textarea v-model="motivoReversao" rows="2" class="rp-input" :placeholder="t('history.sales.reversalModal.reasonPlaceholder')" />
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
        <BotaoBase variante="sucesso" @click="solicitarReversao">
          <span class="inline-flex items-center gap-1.5">
            <Check :size="14" />
            <span>{{ t("history.sales.reversalModal.confirm") }}</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>
</template>
