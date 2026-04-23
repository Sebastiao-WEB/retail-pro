<script setup>
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSessaoStore } from "../store/useSessaoStore";

const route = useRoute();
const router = useRouter();
const sessaoStore = useSessaoStore();

const titulos = {
  pos: { titulo: "Ponto de Venda", subtitulo: "Venda rápida de balcão" },
  "historico-vendas": { titulo: "Histórico de Vendas", subtitulo: "Vendas do caixa e turno atual" },
  configuracoes: { titulo: "Configurações", subtitulo: "Preferências do sistema" },
};

const dataAtual = computed(() =>
  new Intl.DateTimeFormat("pt-MZ", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  }).format(new Date())
);

const paginaAtual = computed(() => titulos[route.name] || { titulo: "RetailPro POS", subtitulo: "Sistema de caixa" });
const utilizador = computed(() => sessaoStore.utilizador || "Operador Caixa");
const caixa = computed(() => sessaoStore.caixaAtribuido || "Sem caixa");

function sair() {
  sessaoStore.logout();
  router.push("/login");
}
</script>

<template>
  <header class="flex items-center justify-between border-b border-[var(--border)] bg-white px-6 py-3.5">
    <div>
      <h2 class="text-xl font-bold text-slate-900">{{ paginaAtual.titulo }}</h2>
      <p class="text-xs text-slate-500">{{ paginaAtual.subtitulo }}</p>
    </div>

    <div class="flex items-center gap-2">
      <div class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-500 md:flex">
        <span>⌕</span>
        <span>Pesquisar...</span>
      </div>

      <button class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50">◔</button>
      <button class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50">⇩</button>
      <RouterLink to="/pos" class="rounded-lg bg-[var(--gold)] px-3 py-1.5 text-xs font-semibold text-black hover:brightness-95">+ Nova Venda</RouterLink>

      <div class="ml-2 text-right">
        <p class="text-xs font-semibold text-slate-800">{{ utilizador }}</p>
        <p class="text-[11px] text-slate-500">{{ caixa }} · {{ dataAtual }}</p>
      </div>

      <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" @click="sair">
        Sair
      </button>
    </div>
  </header>
</template>
