<script setup>
import { computed, ref } from "vue";
import { useVendaStore } from "../../store/useVendaStore";
import TabelaBase from "../../components/TabelaBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import BotaoBase from "../../components/BotaoBase.vue";
import { formatarData, formatarMoeda } from "../../services/formatadores";
import { ArrowDownToLine, Eye, Pencil, X } from "lucide-vue-next";

const vendaStore = useVendaStore();
const filtroData = ref("");
const vendaSelecionada = ref(null);

const colunas = [
  { chave: "id", rotulo: "Nº Venda" },
  { chave: "cliente", rotulo: "Cliente" },
  { chave: "data", rotulo: "Data" },
  { chave: "total", rotulo: "Total" },
  { chave: "metodoPagamento", rotulo: "Pagamento" },
];

const vendasFiltradas = computed(() => {
  if (!filtroData.value) return vendaStore.vendas;
  return vendaStore.vendas.filter((venda) => venda.data.startsWith(filtroData.value));
});
</script>

<template>
  <section class="space-y-4">
    <TabelaBase :colunas="colunas" :linhas="vendasFiltradas" resumo-rodape="Mostrando 6 de 12 registos">
      <template #tabs>
        <button class="border-b-2 border-slate-900 pb-1 text-[11px] font-semibold text-slate-900">Todas (12)</button>
        <button class="pb-1 text-[11px] text-slate-500 hover:text-slate-700">Pagas (7)</button>
        <button class="pb-1 text-[11px] text-slate-500 hover:text-slate-700">Pendentes (2)</button>
        <button class="pb-1 text-[11px] text-slate-500 hover:text-slate-700">Vencidas (3)</button>
        <button class="pb-1 text-[11px] text-slate-500 hover:text-slate-700">Rascunhos (1)</button>
      </template>
      <template #filtros>
        <button class="rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">Todos</button>
        <button class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] text-slate-500">Este mês</button>
        <button class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] text-slate-500">Último trimestre</button>
        <button class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] text-slate-500">2026</button>
        <span class="ml-auto flex items-center gap-2">
          <input v-model="filtroData" type="date" class="rp-input !w-[150px] !py-1.5 !text-[11px]" />
          <button class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-500">Filtrar</button>
          <button class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-500">Exportar</button>
        </span>
      </template>
      <template #coluna-data="{ linha }">{{ formatarData(linha.data) }}</template>
      <template #coluna-total="{ linha }">{{ formatarMoeda(linha.total) }}</template>
      <template #acoes="{ linha }">
        <div class="flex justify-end gap-1">
          <button class="flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 text-[11px] text-slate-500 hover:bg-slate-50" @click="vendaSelecionada = linha">
            <Eye :size="12" />
          </button>
          <button class="flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 text-[11px] text-slate-500 hover:bg-slate-50"><Pencil :size="12" /></button>
          <button class="flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 text-[11px] text-slate-500 hover:bg-slate-50"><ArrowDownToLine :size="12" /></button>
        </div>
      </template>
    </TabelaBase>
  </section>

  <ModalBase :aberto="!!vendaSelecionada" :mostrar-fechar="false" titulo="Detalhes da venda" @fechar="vendaSelecionada = null">
    <div v-if="vendaSelecionada" class="space-y-3">
      <p><strong>Cliente:</strong> {{ vendaSelecionada.cliente }}</p>
      <p><strong>Data:</strong> {{ formatarData(vendaSelecionada.data) }}</p>
      <p><strong>Método:</strong> {{ vendaSelecionada.metodoPagamento }}</p>
      <div class="rounded-lg bg-slate-50 p-3">
        <p class="mb-2 text-sm font-semibold text-slate-700">Itens:</p>
        <ul class="space-y-2 text-sm">
          <li v-for="item in vendaSelecionada.itens" :key="`${vendaSelecionada.id}-${item.produtoId}`" class="flex justify-between">
            <span>{{ item.nome }} x{{ item.quantidade }}</span>
            <span>{{ formatarMoeda(item.subtotal) }}</span>
          </li>
        </ul>
      </div>
      <p class="text-lg font-bold text-emerald-700">Total: {{ formatarMoeda(vendaSelecionada.total) }}</p>
    </div>
    <template #footer>
      <div class="flex justify-end">
        <BotaoBase variante="perigo" @click="vendaSelecionada = null">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>Cancelar</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>
</template>
