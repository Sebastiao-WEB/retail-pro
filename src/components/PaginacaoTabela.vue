<script setup>
import { useI18n } from "vue-i18n";
import BotaoBase from "./BotaoBase.vue";

defineProps({
  paginaAtual: { type: Number, required: true },
  totalPaginas: { type: Number, required: true },
  desabilitado: { type: Boolean, default: false },
});

const emit = defineEmits(["mudar-pagina"]);

const { t } = useI18n();
</script>

<template>
  <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
    <p class="text-[11px] text-slate-500">{{ t("common.pageOf", { current: paginaAtual, total: totalPaginas }) }}</p>
    <div class="flex items-center gap-2">
      <BotaoBase
        variante="secundario"
        :disabled="desabilitado || paginaAtual <= 1"
        @click="emit('mudar-pagina', paginaAtual - 1)"
      >
        {{ t("common.previous") }}
      </BotaoBase>
      <BotaoBase
        variante="secundario"
        :disabled="desabilitado || paginaAtual >= totalPaginas"
        @click="emit('mudar-pagina', paginaAtual + 1)"
      >
        {{ t("common.next") }}
      </BotaoBase>
    </div>
  </div>
</template>
