<script setup>
import { computed } from "vue";
import { useProdutoStore } from "../../store/useProdutoStore";
import TabelaBase from "../../components/TabelaBase.vue";
import { AlertTriangle, Boxes, Clock3 } from "lucide-vue-next";

const produtoStore = useProdutoStore();

const colunas = [
  { chave: "nome", rotulo: "Produto" },
  { chave: "categoria", rotulo: "Categoria" },
  { chave: "stock", rotulo: "Stock Atual" },
  { chave: "reposicao", rotulo: "Reposição" },
];

const linhas = computed(() =>
  produtoStore.produtos.map((produto) => ({
    ...produto,
    reposicao: produto.stock <= 10 ? "Repor com urgência" : produto.stock <= 25 ? "Repor em breve" : "Stock saudável",
  }))
);
</script>

<template>
  <section class="space-y-4">
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      Produtos com stock baixo: <strong>{{ produtoStore.produtosComStockBaixo.length }}</strong>
    </div>
    <TabelaBase :colunas="colunas" :linhas="linhas" :resumo-rodape="`Mostrando ${linhas.length} produtos em stock`">
      <template #filtros>
        <button class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">
          <Boxes :size="11" />
          <span>Todos</span>
        </button>
        <button class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700">
          <AlertTriangle :size="11" />
          <span>Stock baixo</span>
        </button>
        <button class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">
          <Clock3 :size="11" />
          <span>Reposição breve</span>
        </button>
      </template>
      <template #coluna-stock="{ linha }">
        <span :class="linha.stock <= 10 ? 'font-bold text-red-600' : 'text-slate-700'">{{ linha.stock }}</span>
      </template>
      <template #coluna-reposicao="{ linha }">
        <span
          class="rounded-full px-3 py-1 text-xs font-semibold"
          :class="
            linha.stock <= 10
              ? 'bg-red-100 text-red-700'
              : linha.stock <= 25
                ? 'bg-amber-100 text-amber-700'
                : 'bg-emerald-100 text-emerald-700'
          "
        >
          {{ linha.reposicao }}
        </span>
      </template>
    </TabelaBase>
  </section>
</template>
