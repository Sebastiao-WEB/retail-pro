<script setup>
import BotaoBase from "./BotaoBase.vue";
import { formatarMoeda } from "../services/formatadores";

const props = defineProps({
  produto: { type: Object, required: true },
});

const emit = defineEmits(["adicionar"]);
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="mb-3">
      <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ props.produto.categoria }}</p>
      <h4 class="font-semibold text-slate-800">{{ props.produto.nome }}</h4>
      <p class="text-xs text-slate-500">Código: {{ props.produto.codigoBarras }}</p>
    </div>
    <div class="mb-4 flex items-center justify-between">
      <span class="text-lg font-bold text-slate-900">{{ formatarMoeda(props.produto.precoVenda) }}</span>
      <span
        class="rounded-full px-3 py-1 text-xs font-semibold"
        :class="props.produto.stock <= 10 ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'"
      >
        Stock: {{ props.produto.stock }}
      </span>
    </div>
    <BotaoBase bloco variante="aviso" @click="emit('adicionar', props.produto)">Adicionar</BotaoBase>
  </div>
</template>
