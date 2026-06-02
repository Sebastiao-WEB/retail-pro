<script setup>
import { computed, onMounted, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import { X } from "lucide-vue-next";

const { t } = useI18n();

const visivel = computed(
  () => typeof window !== "undefined" && typeof window.api?.fecharJanela === "function"
);

onMounted(() => {
  if (visivel.value) {
    document.documentElement.classList.add("pos-desktop-frameless");
  }
});

onUnmounted(() => {
  document.documentElement.classList.remove("pos-desktop-frameless");
});

function fechar() {
  void window.api?.fecharJanela?.();
}
</script>

<template>
  <div
    v-if="visivel"
    class="fixed right-0 top-0 z-[9999] flex h-9 items-stretch border-b border-l border-slate-200 bg-white"
    style="-webkit-app-region: no-drag"
  >
    <button
      type="button"
      class="inline-flex w-11 items-center justify-center text-slate-600 hover:bg-red-600 hover:text-white"
      :title="t('desktopWindow.close')"
      :aria-label="t('desktopWindow.close')"
      @click="fechar"
    >
      <X :size="16" :stroke-width="2.2" />
    </button>
  </div>
</template>
