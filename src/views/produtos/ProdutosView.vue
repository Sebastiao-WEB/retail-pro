<script setup>
import { computed, ref } from "vue";
import TabelaBase from "../../components/TabelaBase.vue";
import { useProdutoStore } from "../../store/useProdutoStore";
import { formatarMoeda } from "../../services/formatadores";
import { Download, Filter } from "lucide-vue-next";

const produtoStore = useProdutoStore();
const pesquisa = ref("");

const colunas = [
  { chave: "nome", rotulo: "Produto" },
  { chave: "codigoBarras", rotulo: "Código" },
  { chave: "categoria", rotulo: "Categoria" },
  { chave: "precoCompra", rotulo: "Preço compra" },
  { chave: "precoVendaComIva", rotulo: "Preço + IVA" },
  { chave: "ivaResumo", rotulo: "IVA" },
  { chave: "stock", rotulo: "Stock" },
];

const linhas = computed(() =>
  produtoStore.produtos.filter((produto) =>
    `${produto.nome} ${produto.categoria}`.toLowerCase().includes(pesquisa.value.toLowerCase())
  )
);
</script>

<template>
  <section class="space-y-4">
    <TabelaBase :colunas="colunas" :linhas="linhas" :resumo-rodape="`Mostrando ${linhas.length} registos de produtos`">
      <template #filtros>
        <input v-model="pesquisa" class="rp-input !max-w-xs !py-1.5 !text-[11px]" placeholder="Pesquisar produto..." />
        <button class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-50">
          <Filter :size="12" />
          <span>Filtrar</span>
        </button>
        <button class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-50">
          <Download :size="12" />
          <span>Exportar</span>
        </button>
      </template>
      <template #coluna-precoVendaComIva="{ linha }">
        <div class="leading-tight">
          <p class="font-semibold">{{ formatarMoeda(linha.precoVendaComIva ?? linha.precoVenda) }}</p>
          <p class="text-[10px] text-slate-500">Venda base: {{ formatarMoeda(linha.precoVenda) }}</p>
        </div>
      </template>
      <template #coluna-precoCompra="{ linha }">
        <span class="font-semibold text-slate-700">{{ formatarMoeda(linha.precoCompra || 0) }}</span>
      </template>
      <template #coluna-ivaResumo="{ linha }">
        <div class="leading-tight">
          <p v-if="linha.ivaTipo === 'isento'" class="font-semibold text-emerald-700">Isento</p>
          <p v-else-if="linha.ivaTipo === 'monetario'" class="font-semibold text-slate-700">
            {{ formatarMoeda(linha.ivaValor || 0) }}
          </p>
          <p v-else class="font-semibold text-slate-700">{{ Number(linha.ivaValor || linha.ivaPercentual || 0) }}%</p>
          <p class="text-[10px] text-slate-500 capitalize">{{ linha.ivaTipo || "percentual" }}</p>
        </div>
      </template>
      <template #coluna-stock="{ linha }">
        <span :class="linha.stock <= 10 ? 'font-bold text-red-600' : 'text-slate-700'">{{ linha.stock }}</span>
      </template>
    </TabelaBase>
  </section>
</template>
