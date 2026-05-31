<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { LOCALE_LABELS, SUPPORTED_LOCALES, setStoredLocale } from '../services/localeStorage.js';

const { locale } = useI18n();

const localeAtual = computed(() => locale.value);

function alternarIdioma(codigo) {
  if (localeAtual.value === codigo) return;
  setStoredLocale(codigo);
  locale.value = codigo;
  document.documentElement.lang = codigo === 'so_SO' ? 'so' : 'pt-MZ';
}
</script>

<template>
  <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-0.5">
    <button
      v-for="codigo in SUPPORTED_LOCALES"
      :key="codigo"
      type="button"
      :title="LOCALE_LABELS[codigo]"
      :class="[
        'rounded-md px-2 py-1 text-[11px] font-semibold transition',
        localeAtual === codigo ? 'bg-[var(--gold)] text-black' : 'text-slate-600 hover:bg-slate-50',
      ]"
      @click="alternarIdioma(codigo)"
    >
      {{ codigo === 'pt_MZ' ? 'PT' : 'SO' }}
    </button>
  </div>
</template>
