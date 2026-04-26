<script setup>
import { computed, onMounted, ref } from "vue";
import ModalBase from "../../components/ModalBase.vue";
import BotaoBase from "../../components/BotaoBase.vue";
import { useVendaStore } from "../../store/useVendaStore";
import { useSessaoStore } from "../../store/useSessaoStore";
import { BadgeDollarSign, ChartNoAxesCombined, CircleDollarSign, TriangleAlert, Users, X } from "lucide-vue-next";

const vendaStore = useVendaStore();
const sessaoStore = useSessaoStore();

onMounted(() => {
  vendaStore.carregarHistorico();
});

const solicitacoesPendentes = computed(() => vendaStore.solicitacoesPendentes);
const modalDecisaoAberto = ref(false);
const acaoPendente = ref(null);

function formatarData(valor) {
  return new Date(valor).toLocaleString("pt-MZ");
}

function abrirConfirmacao(idSolicitacao, tipo) {
  acaoPendente.value = { idSolicitacao, tipo };
  modalDecisaoAberto.value = true;
}

function confirmarDecisao() {
  if (!acaoPendente.value) return;
  if (acaoPendente.value.tipo === "aprovar") {
    vendaStore.aprovarReversao(acaoPendente.value.idSolicitacao, sessaoStore.utilizador || "Gerente");
  } else {
    vendaStore.cancelarReversao(acaoPendente.value.idSolicitacao, sessaoStore.utilizador || "Gerente");
  }
  modalDecisaoAberto.value = false;
  acaoPendente.value = null;
}

const cartoes = [
  { titulo: "Total Facturado", valor: "847.320 MT", detalhe: "+18.4% vs anterior", cor: "border-t-[3px] border-t-amber-500", texto: "text-emerald-600", icon: ChartNoAxesCombined },
  { titulo: "Valor Recebido", valor: "612.800 MT", detalhe: "72% da meta mensal", cor: "border-t-[3px] border-t-emerald-500", texto: "text-emerald-600", icon: BadgeDollarSign },
  { titulo: "Em Dívida", valor: "234.520 MT", detalhe: "3 facturas vencidas", cor: "border-t-[3px] border-t-red-500", texto: "text-red-600", icon: CircleDollarSign },
  { titulo: "Clientes Activos", valor: "48", detalhe: "+3 novos este mês", cor: "border-t-[3px] border-t-blue-500", texto: "text-emerald-600", icon: Users },
];

const ultimasFacturas = [
  { numero: "FT 2026/0042", cliente: "Telecom MZ Lda", data: "12 Abr 2026", valor: "145.800 MT", estado: "Paga" },
  { numero: "FT 2026/0041", cliente: "Construtora Maputo", data: "10 Abr 2026", valor: "89.500 MT", estado: "Pendente" },
  { numero: "FT 2026/0040", cliente: "Agro Niassa Lda", data: "8 Abr 2026", valor: "212.000 MT", estado: "Vencida" },
  { numero: "FT 2026/0039", cliente: "Hotel Cardoso", data: "5 Abr 2026", valor: "67.250 MT", estado: "Paga" },
  { numero: "FT 2026/0038", cliente: "Shoprite MZ", data: "2 Abr 2026", valor: "333.770 MT", estado: "Vencida" },
];

const barrasMensais = [
  { mes: "Fev", altura: "h-10" },
  { mes: "Mar", altura: "h-14" },
  { mes: "Abr", altura: "h-8" },
  { mes: "Mai", altura: "h-16" },
  { mes: "Jun", altura: "h-11" },
  { mes: "Jul", altura: "h-[72px]" },
  { mes: "Ago", altura: "h-[56px]" },
  { mes: "Set", altura: "h-20", ativo: true },
];

const actividades = [
  { cor: "bg-emerald-500", texto: "Pagamento recebido — Telecom MZ", tempo: "há 2h" },
  { cor: "bg-amber-500", texto: "Factura FT2026/0042 emitida", tempo: "hoje" },
  { cor: "bg-red-500", texto: "Factura FT2026/0038 vencida", tempo: "ontem" },
  { cor: "bg-blue-500", texto: "Novo cliente Hotel Cardoso", tempo: "7 Abr" },
];
</script>

<template>
  <section class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
      <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
        <div>
          <h3 class="text-sm font-bold text-slate-900">Solicitações de reversão</h3>
          <p class="text-xs text-slate-500">Aprovação do gerente</p>
        </div>
      </div>
      <div class="overflow-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-2.5">Referência</th>
              <th class="px-4 py-2.5">Solicitado por</th>
              <th class="px-4 py-2.5">Data</th>
              <th class="px-4 py-2.5">Motivo</th>
              <th class="px-4 py-2.5 text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!solicitacoesPendentes.length">
              <td colspan="5" class="px-4 py-6 text-center text-xs text-slate-500">Sem solicitações pendentes.</td>
            </tr>
            <tr v-for="item in solicitacoesPendentes" :key="item.id" class="border-t border-slate-100">
              <td class="px-4 py-2.5 text-xs font-semibold text-slate-700">{{ item.referencia }}</td>
              <td class="px-4 py-2.5 text-xs text-slate-700">{{ item.solicitadoPor }}</td>
              <td class="px-4 py-2.5 text-xs text-slate-600">{{ formatarData(item.dataSolicitacao) }}</td>
              <td class="px-4 py-2.5 text-xs text-slate-600">{{ item.motivo || "-" }}</td>
              <td class="px-4 py-2.5">
                <div class="flex justify-end gap-2">
                  <button class="rounded-md bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-100" @click="abrirConfirmacao(item.id, 'cancelar')">
                    Cancelar
                  </button>
                  <button class="rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700" @click="abrirConfirmacao(item.id, 'aprovar')">
                    Confirmar
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-3 xl:grid-cols-4">
      <article
        v-for="cartao in cartoes"
        :key="cartao.titulo"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.03)]"
        :class="cartao.cor"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ cartao.titulo }}</p>
            <h3 class="mt-1 font-serif text-[33px] leading-none font-bold text-slate-900">{{ cartao.valor }}</h3>
            <p class="mt-1 text-xs" :class="cartao.texto">{{ cartao.detalhe }}</p>
          </div>
          <component :is="cartao.icon" :size="30" class="text-slate-200" />
        </div>
      </article>
    </div>

    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[1fr_290px]">
      <div class="rounded-xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <div>
            <h3 class="text-sm font-bold text-slate-900">Últimas Facturas</h3>
            <p class="text-xs text-slate-500">Documentos recentes</p>
          </div>
          <button class="rounded-lg border border-slate-200 px-2 py-1 text-xs text-slate-500 hover:bg-slate-50">Ver tudo</button>
        </div>

        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-2.5">Factura</th>
                <th class="px-4 py-2.5">Cliente</th>
                <th class="px-4 py-2.5">Valor</th>
                <th class="px-4 py-2.5">Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="factura in ultimasFacturas" :key="factura.numero" class="border-t border-slate-100">
                <td class="px-4 py-2.5 text-xs font-semibold text-slate-700">{{ factura.numero }}</td>
                <td class="px-4 py-2.5">
                  <p class="text-xs font-semibold text-slate-800">{{ factura.cliente }}</p>
                  <p class="text-[11px] text-slate-500">{{ factura.data }}</p>
                </td>
                <td class="px-4 py-2.5 text-xs font-semibold text-slate-800">{{ factura.valor }}</td>
                <td class="px-4 py-2.5">
                  <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="
                      factura.estado === 'Paga'
                        ? 'bg-emerald-100 text-emerald-700'
                        : factura.estado === 'Pendente'
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-red-100 text-red-700'
                    "
                  >
                    {{ factura.estado }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-3">
        <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
          <h4 class="mb-3 text-sm font-semibold text-slate-800">Facturação Mensal</h4>
          <div class="flex h-24 items-end gap-1">
            <div
              v-for="barra in barrasMensais"
              :key="barra.mes"
              class="flex-1 rounded-t-[4px]"
              :class="[barra.altura, barra.ativo ? 'bg-amber-500' : 'bg-slate-200']"
            />
          </div>
          <div class="mt-2 flex items-center justify-between text-[10px] text-slate-500">
            <span v-for="barra in barrasMensais" :key="`lbl-${barra.mes}`">{{ barra.mes }}</span>
          </div>
        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
          <h4 class="mb-3 text-sm font-semibold text-slate-800">Meta de Cobranças</h4>
          <div class="space-y-3">
            <div>
              <div class="mb-1 flex items-center justify-between text-xs">
                <span class="text-slate-500">Cobrado: <strong class="text-slate-900">612.800 MT</strong></span>
                <strong class="text-emerald-600">72%</strong>
              </div>
              <div class="h-2 rounded-full bg-slate-200">
                <div class="h-2 w-[72%] rounded-full bg-emerald-500" />
              </div>
            </div>
            <div>
              <div class="mb-1 flex items-center justify-between text-xs">
                <span class="text-slate-500">Em dívida: <strong class="text-slate-900">234.520 MT</strong></span>
                <strong class="text-red-600">28%</strong>
              </div>
              <div class="h-2 rounded-full bg-slate-200">
                <div class="h-2 w-[28%] rounded-full bg-red-500" />
              </div>
            </div>
          </div>
        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
          <h4 class="mb-2 text-sm font-semibold text-slate-800">Actividade Recente</h4>
          <div class="space-y-2">
            <div v-for="item in actividades" :key="item.texto" class="flex items-start gap-2 border-b border-slate-100 pb-2 last:border-none last:pb-0">
              <span class="mt-1 h-2 w-2 rounded-full" :class="item.cor" />
              <div>
                <p class="text-xs text-slate-700">{{ item.texto }}</p>
                <p class="text-[11px] text-slate-500">{{ item.tempo }}</p>
              </div>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <ModalBase :aberto="modalDecisaoAberto" :mostrar-fechar="false" titulo="Confirmar decisão" @fechar="modalDecisaoAberto = false">
    <div class="space-y-4">
      <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
          <TriangleAlert :size="14" />
        </span>
        <p>
          {{ acaoPendente?.tipo === "aprovar" ? "Confirma a reversão desta venda?" : "Cancelar solicitação de reversão?" }}
        </p>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalDecisaoAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase :variante="acaoPendente?.tipo === 'aprovar' ? 'sucesso' : 'aviso'" @click="confirmarDecisao">
          Confirmar
        </BotaoBase>
      </div>
    </template>
  </ModalBase>
</template>
