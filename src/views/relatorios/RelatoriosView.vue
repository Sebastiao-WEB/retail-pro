<script setup>
import { computed } from "vue";
import { useVendaStore } from "../../store/useVendaStore";
import { formatarMoeda } from "../../services/formatadores";

const vendaStore = useVendaStore();

const resumo = computed(() => {
  const receita = vendaStore.receitaTotal;
  const custoEstimado = receita * 0.68;
  return {
    receita,
    lucro: receita - custoEstimado,
    totalVendas: vendaStore.vendas.length,
  };
});

const produtosMaisVendidos = computed(() => {
  const mapa = new Map();
  vendaStore.vendas.forEach((venda) => {
    venda.itens.forEach((item) => {
      const atual = mapa.get(item.nome) || { nome: item.nome, quantidade: 0, total: 0 };
      atual.quantidade += item.quantidade;
      atual.total += item.subtotal;
      mapa.set(item.nome, atual);
    });
  });
  return [...mapa.values()].sort((a, b) => b.quantidade - a.quantidade).slice(0, 5);
});
</script>

<template>
  <section class="space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <article class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-sm text-slate-500">Receita total</p>
        <h3 class="text-2xl font-black text-emerald-700">{{ formatarMoeda(resumo.receita) }}</h3>
      </article>
      <article class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-sm text-slate-500">Total de vendas</p>
        <h3 class="text-2xl font-black text-slate-900">{{ resumo.totalVendas }}</h3>
      </article>
      <article class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-sm text-slate-500">Lucro estimado (mock)</p>
        <h3 class="text-2xl font-black text-blue-700">{{ formatarMoeda(resumo.lucro) }}</h3>
      </article>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
      <h3 class="mb-4 text-lg font-bold text-slate-900">Produtos mais vendidos</h3>
      <ul class="space-y-3">
        <li
          v-for="produto in produtosMaisVendidos"
          :key="produto.nome"
          class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3"
        >
          <div>
            <p class="font-semibold text-slate-800">{{ produto.nome }}</p>
            <p class="text-xs text-slate-500">Quantidade: {{ produto.quantidade }}</p>
          </div>
          <p class="font-bold text-slate-900">{{ formatarMoeda(produto.total) }}</p>
        </li>
      </ul>
    </div>
  </section>
</template>
