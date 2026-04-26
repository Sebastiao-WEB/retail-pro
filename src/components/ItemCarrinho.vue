<script setup>
import BotaoBase from "./BotaoBase.vue";
import { formatarMoeda } from "../services/formatadores";
import { Minus, Plus, Trash2 } from "lucide-vue-next";

const props = defineProps({
  item: { type: Object, required: true },
});

const emit = defineEmits(["aumentar", "diminuir", "remover"]);
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white p-3">
    <div class="mb-2 flex items-start justify-between gap-2">
      <div>
        <p class="font-semibold text-slate-800">{{ props.item.nome }}</p>
        <p class="text-xs text-slate-500">{{ formatarMoeda(props.item.precoVenda) }} por unidade</p>
      </div>
      <button class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700" @click="emit('remover', props.item.produtoId)">
        <Trash2 :size="12" />
        <span>Remover</span>
      </button>
    </div>
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <BotaoBase variante="secundario" @click="emit('diminuir', props.item.produtoId)">
          <Minus :size="14" />
        </BotaoBase>
        <span class="min-w-8 text-center text-sm font-semibold">{{ props.item.quantidade }}</span>
        <BotaoBase variante="secundario" @click="emit('aumentar', props.item.produtoId)">
          <Plus :size="14" />
        </BotaoBase>
      </div>
      <p class="font-bold text-slate-900">{{ formatarMoeda(props.item.subtotal) }}</p>
    </div>
  </div>
</template>
