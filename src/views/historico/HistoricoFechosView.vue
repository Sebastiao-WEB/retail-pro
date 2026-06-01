<script setup>
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import BotaoBase from "../../components/BotaoBase.vue";
import PaginacaoTabela from "../../components/PaginacaoTabela.vue";
import { useSessaoStore } from "../../store/useSessaoStore";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";
import { temApiConfigurada } from "../../api";
import { carregarHistoricoFechosIntegrado } from "../../services/integracaoApi";
import { enviarRelatorioFechoParaImpressao } from "../../services/relatorioFechoImpressao";
import { mostrarToastSwal } from "../../services/toast";
import { intlLocale } from "../../services/localeStorage.js";
import { LoaderCircle, Printer, RotateCcw } from "lucide-vue-next";

const ITENS_POR_PAGINA = 10;

const { t, locale } = useI18n();
const sessaoStore = useSessaoStore();
const configuracaoStore = useConfiguracaoStore();
const imprimindoAgora = ref(false);
const carregando = ref(false);
const paginaAtual = ref(1);
const totalPaginas = ref(1);
const totalFechos = ref(0);
const fechos = ref([]);

const subtituloPagina = computed(() =>
  t("history.closings.subtitle", { total: totalFechos.value, perPage: ITENS_POR_PAGINA })
);

function formatarMT(valor) {
  return `${new Intl.NumberFormat(intlLocale(locale.value), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(valor || 0))} MT`;
}

function formatarData(valor) {
  if (!valor) return t("common.dash");
  return new Date(valor).toLocaleString(intlLocale(locale.value));
}

async function carregarPagina(pagina = 1) {
  carregando.value = true;
  paginaAtual.value = pagina;
  fechos.value = [];
  try {
    sessaoStore.hidratar();
    if (!temApiConfigurada()) {
      totalFechos.value = 0;
      totalPaginas.value = 1;
      return;
    }
    if (!sessaoStore.registerId && !sessaoStore.caixaAtribuido) {
      throw new Error(t("history.closings.toast.noRegister"));
    }

    const resposta = await carregarHistoricoFechosIntegrado({
      register_id: sessaoStore.registerId || undefined,
      status: "CLOSED",
      page: pagina,
      per_page: ITENS_POR_PAGINA,
    });
    fechos.value = resposta.fechos || [];
    totalPaginas.value = Math.max(1, Number(resposta.meta?.last_page || 1));
    totalFechos.value = Number(resposta.meta?.total || fechos.value.length);
  } catch (erro) {
    totalFechos.value = 0;
    totalPaginas.value = 1;
    mostrarToastSwal(erro?.message || t("history.closings.toast.loadFailed"), "error");
  } finally {
    carregando.value = false;
  }
}

function irParaPagina(pagina) {
  const destino = Math.min(Math.max(1, pagina), totalPaginas.value);
  carregarPagina(destino);
}

async function reimprimirFecho(fecho) {
  if (imprimindoAgora.value) return;
  if (!window.api?.imprimirRelatorioFecho) {
    mostrarToastSwal(t("history.closings.toast.reprintDesktopOnly"), "error");
    return;
  }
  if (!configuracaoStore.impressoraPadrao) {
    mostrarToastSwal(t("history.closings.toast.setPrinterFirst"), "error");
    return;
  }

  imprimindoAgora.value = true;
  try {
    const resultado = await enviarRelatorioFechoParaImpressao({
      relatorio: fecho,
      configuracao: configuracaoStore,
      opcoes: {
        segundaVia: true,
        copies: 1,
        corteAutomatico: !!configuracaoStore.corteAutomatico,
      },
    });
    if (!resultado?.ok) {
      mostrarToastSwal(resultado?.error || t("history.closings.toast.reprintFailed"), "error");
      return;
    }
    mostrarToastSwal(t("history.closings.toast.reprintSuccess", { printer: configuracaoStore.impressoraPadrao }), "success");
  } finally {
    imprimindoAgora.value = false;
  }
}

onMounted(async () => {
  configuracaoStore.hidratar();
  if (temApiConfigurada()) {
    try {
      await configuracaoStore.hidratarDadosEmpresaRemotos();
    } catch {
      // Mantém dados locais quando a API falhar.
    }
  }
  await carregarPagina(1);
});
</script>

<template>
  <section class="space-y-4">
    <div class="rp-card p-4">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
        <div>
          <h3 class="text-base font-bold text-slate-900">{{ t("history.closings.title") }}</h3>
          <p class="text-xs text-slate-500">
            {{ t("common.register") }}: <strong>{{ sessaoStore.caixaAtribuido || t("common.noRegister") }}</strong>
            <span v-if="sessaoStore.registerCodigo">({{ sessaoStore.registerCodigo }})</span>
            · {{ subtituloPagina }}
          </p>
        </div>
        <BotaoBase variante="secundario" :disabled="carregando" @click="carregarPagina(paginaAtual)">
          <span class="inline-flex items-center gap-1.5">
            <RotateCcw :size="14" :class="carregando ? 'animate-spin' : ''" />
            <span>{{ t("common.refresh") }}</span>
          </span>
        </BotaoBase>
      </div>

      <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-3 py-2">{{ t("history.closings.closedAt") }}</th>
              <th class="px-3 py-2">{{ t("common.operator") }}</th>
              <th class="px-3 py-2">{{ t("history.closings.opening") }}</th>
              <th class="px-3 py-2">{{ t("history.closings.totalSold") }}</th>
              <th class="px-3 py-2">{{ t("history.closings.actualCash") }}</th>
              <th class="px-3 py-2">{{ t("history.closings.difference") }}</th>
              <th class="px-3 py-2">{{ t("common.transactions") }}</th>
              <th class="px-3 py-2 text-right">{{ t("common.actions") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="carregando">
              <td colspan="8" class="px-3 py-10 text-center text-xs text-slate-500">
                <span class="inline-flex items-center gap-2">
                  <LoaderCircle class="animate-spin" :size="14" />
                  {{ t("history.closings.loading") }}
                </span>
              </td>
            </tr>
            <tr v-else-if="!fechos.length">
              <td colspan="8" class="px-3 py-10 text-center text-xs text-slate-500">
                {{
                  temApiConfigurada()
                    ? t("history.closings.emptyWithApi")
                    : t("history.closings.emptyNoApi")
                }}
              </td>
            </tr>
            <tr
              v-for="(fecho, indice) in fechos"
              :key="fecho.cashSessionId || `${fecho.fechadoEm}-${indice}`"
              class="border-t border-slate-100 text-[12px] hover:bg-slate-50"
            >
              <td class="px-3 py-2 text-slate-600">{{ formatarData(fecho.fechadoEm) }}</td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ fecho.utilizador || t("common.dash") }}</td>
              <td class="px-3 py-2 text-slate-600">{{ formatarData(fecho.aberturaEm) }}</td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ formatarMT(fecho.totalVendido) }}</td>
              <td class="px-3 py-2 text-slate-600">{{ formatarMT(fecho.dinheiroReal) }}</td>
              <td class="px-3 py-2">
                <span
                  class="font-semibold"
                  :class="
                    Number(fecho.diferenca || 0) === 0
                      ? 'text-emerald-700'
                      : Number(fecho.diferenca || 0) < 0
                        ? 'text-red-700'
                        : 'text-amber-700'
                  "
                >
                  {{ formatarMT(fecho.diferenca) }}
                </span>
              </td>
              <td class="px-3 py-2 text-slate-600">{{ fecho.totalTransacoes ?? 0 }}</td>
              <td class="px-3 py-2">
                <div class="flex justify-end">
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--gold)] text-black hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-50"
                    :title="t('history.closings.reprintTitle')"
                    :aria-label="t('history.closings.reprintTitle')"
                    :disabled="imprimindoAgora"
                    @click="reimprimirFecho(fecho)"
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
        v-if="totalFechos > 0"
        :pagina-atual="paginaAtual"
        :total-paginas="totalPaginas"
        :desabilitado="carregando"
        @mudar-pagina="irParaPagina"
      />
    </div>
  </section>
</template>
