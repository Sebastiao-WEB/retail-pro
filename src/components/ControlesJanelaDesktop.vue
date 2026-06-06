<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { Minus, Square, SquareStack, X } from "lucide-vue-next";

const { t } = useI18n();

const visivel = computed(
  () => typeof window !== "undefined" && typeof window.api?.fecharJanela === "function"
);

const maximizada = ref(false);
let removerListenerEstado = null;

onMounted(async () => {
  if (!visivel.value) return;

  document.documentElement.classList.add("pos-desktop-frameless");

  try {
    const estado = await window.api?.estadoJanela?.();
    if (estado) maximizada.value = !!estado.maximized;
  } catch {
    // Ignora falha ao obter estado inicial.
  }

  removerListenerEstado = window.api?.onEstadoJanela?.((estado) => {
    maximizada.value = !!estado?.maximized;
  });
});

onUnmounted(() => {
  document.documentElement.classList.remove("pos-desktop-frameless");
  removerListenerEstado?.();
  removerListenerEstado = null;
});

function minimizar() {
  void window.api?.minimizarJanela?.();
}

function alternarMaximizar() {
  void window.api?.alternarMaximizarJanela?.();
}

function fechar() {
  void window.api?.fecharJanela?.();
}
</script>

<template>
  <header
    v-if="visivel"
    class="pos-desktop-titlebar fixed inset-x-0 top-0 z-[9999] flex h-8 select-none items-stretch border-b border-slate-200 bg-slate-100 text-slate-700"
  >
    <div class="flex min-w-0 flex-1 items-center px-3 text-xs font-semibold tracking-wide" style="-webkit-app-region: drag">
      RetailPro POS
    </div>
    <div class="flex shrink-0 items-stretch" style="-webkit-app-region: no-drag">
      <button
        type="button"
        class="inline-flex w-11 items-center justify-center hover:bg-slate-200"
        :title="t('desktopWindow.minimize')"
        :aria-label="t('desktopWindow.minimize')"
        @click="minimizar"
      >
        <Minus :size="14" :stroke-width="2.2" />
      </button>
      <button
        type="button"
        class="inline-flex w-11 items-center justify-center hover:bg-slate-200"
        :title="maximizada ? t('desktopWindow.restore') : t('desktopWindow.maximize')"
        :aria-label="maximizada ? t('desktopWindow.restore') : t('desktopWindow.maximize')"
        @click="alternarMaximizar"
      >
        <SquareStack v-if="maximizada" :size="13" :stroke-width="2.2" />
        <Square v-else :size="12" :stroke-width="2.2" />
      </button>
      <button
        type="button"
        class="inline-flex w-11 items-center justify-center hover:bg-red-600 hover:text-white"
        :title="t('desktopWindow.close')"
        :aria-label="t('desktopWindow.close')"
        @click="fechar"
      >
        <X :size="16" :stroke-width="2.2" />
      </button>
    </div>
  </header>
</template>
