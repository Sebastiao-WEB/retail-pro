<script setup>
import { onMounted, ref } from "vue";
import BotaoBase from "../../components/BotaoBase.vue";
import { useSessaoStore } from "../../store/useSessaoStore";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";
import { temApiConfigurada } from "../../api";
import { carregarHistoricoFechosIntegrado } from "../../services/integracaoApi";
import { enviarRelatorioFechoParaImpressao } from "../../services/relatorioFechoImpressao";
import { mostrarToastSwal } from "../../services/toast";
import { LoaderCircle, Printer, RotateCcw } from "lucide-vue-next";

const ITENS_POR_PAGINA = 10;

const sessaoStore = useSessaoStore();
const configuracaoStore = useConfiguracaoStore();
const imprimindoAgora = ref(false);
const carregando = ref(false);
const paginaAtual = ref(1);
const totalPaginas = ref(1);
const totalFechos = ref(0);
const fechos = ref([]);

function formatarMT(valor) {
  return `${new Intl.NumberFormat("pt-MZ", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(valor || 0))} MT`;
}

function formatarData(valor) {
  if (!valor) return "—";
  return new Date(valor).toLocaleString("pt-MZ");
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
      throw new Error("Operador sem caixa atribuído para consultar histórico no backend.");
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
    mostrarToastSwal(erro?.message || "Falha ao carregar histórico de fechos de caixa.", "error");
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
    mostrarToastSwal("Reimpressão disponível apenas na versão desktop (Electron).", "error");
    return;
  }
  if (!configuracaoStore.impressoraPadrao) {
    mostrarToastSwal("Defina a impressora padrão em Configurações para reimprimir.", "error");
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
      mostrarToastSwal(resultado?.error || "Falha ao reimprimir relatório de fecho.", "error");
      return;
    }
    mostrarToastSwal(`2.ª via do relatório enviada para ${configuracaoStore.impressoraPadrao}.`, "success");
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
          <h3 class="text-base font-bold text-slate-900">Histórico de fechos de caixa</h3>
          <p class="text-xs text-slate-500">
            Caixa: <strong>{{ sessaoStore.caixaAtribuido || "Sem caixa" }}</strong>
            <span v-if="sessaoStore.registerCodigo">({{ sessaoStore.registerCodigo }})</span>
            · apenas fechos deste caixa · {{ totalFechos }} fecho(s) · {{ ITENS_POR_PAGINA }} por página
          </p>
        </div>
        <BotaoBase variante="secundario" :disabled="carregando" @click="carregarPagina(paginaAtual)">
          <span class="inline-flex items-center gap-1.5">
            <RotateCcw :size="14" :class="carregando ? 'animate-spin' : ''" />
            <span>Actualizar</span>
          </span>
        </BotaoBase>
      </div>

      <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-3 py-2">Fechado em</th>
              <th class="px-3 py-2">Operador</th>
              <th class="px-3 py-2">Abertura</th>
              <th class="px-3 py-2">Total vendido</th>
              <th class="px-3 py-2">Dinheiro real</th>
              <th class="px-3 py-2">Diferença</th>
              <th class="px-3 py-2">Trans.</th>
              <th class="px-3 py-2 text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="carregando">
              <td colspan="8" class="px-3 py-10 text-center text-xs text-slate-500">
                <span class="inline-flex items-center gap-2">
                  <LoaderCircle class="animate-spin" :size="14" />
                  A carregar histórico do backend...
                </span>
              </td>
            </tr>
            <tr v-else-if="!fechos.length">
              <td colspan="8" class="px-3 py-10 text-center text-xs text-slate-500">
                {{
                  temApiConfigurada()
                    ? "Nenhum fecho de caixa registado no backend."
                    : "Histórico disponível apenas com backend API configurado."
                }}
              </td>
            </tr>
            <tr
              v-for="(fecho, indice) in fechos"
              :key="fecho.cashSessionId || `${fecho.fechadoEm}-${indice}`"
              class="border-t border-slate-100 text-[12px] hover:bg-slate-50"
            >
              <td class="px-3 py-2 text-slate-600">{{ formatarData(fecho.fechadoEm) }}</td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ fecho.utilizador || "—" }}</td>
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
                    title="Reimprimir 2.ª via do relatório"
                    aria-label="Reimprimir 2.ª via do relatório"
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

      <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
        <p class="text-[11px] text-slate-500">Página {{ paginaAtual }} de {{ totalPaginas }}</p>
        <div class="flex items-center gap-2">
          <BotaoBase variante="secundario" :disabled="carregando || paginaAtual <= 1" @click="irParaPagina(paginaAtual - 1)">
            Anterior
          </BotaoBase>
          <BotaoBase
            variante="secundario"
            :disabled="carregando || paginaAtual >= totalPaginas"
            @click="irParaPagina(paginaAtual + 1)"
          >
            Próxima
          </BotaoBase>
        </div>
      </div>
    </div>
  </section>
</template>
