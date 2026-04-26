<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import BotaoBase from "./BotaoBase.vue";
import ModalBase from "./ModalBase.vue";
import { useSessaoStore } from "../store/useSessaoStore";
import { authApi, temApiConfigurada } from "../api";
import { LogOut, TriangleAlert, X } from "lucide-vue-next";

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
const modalSairAberto = ref(false);

function sair() {
  modalSairAberto.value = true;
}

async function confirmarSaida() {
  if (temApiConfigurada()) {
    try {
      await authApi.logout();
    } catch {
      // logout remoto é best effort; sessão local precisa encerrar sempre
    }
  }
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
      <div class="ml-2 text-right">
        <p class="text-xs font-semibold text-slate-800">{{ utilizador }}</p>
        <p class="text-[11px] text-slate-500">{{ caixa }} · {{ dataAtual }}</p>
      </div>

      <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" @click="sair">
        Sair
      </button>
    </div>
  </header>

  <ModalBase :aberto="modalSairAberto" :mostrar-fechar="false" titulo="Confirmar saída" @fechar="modalSairAberto = false">
    <div class="space-y-4">
      <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
          <TriangleAlert :size="14" />
        </span>
        <div>
          <p class="font-semibold text-slate-900">Realmente deseja terminar a sessão?</p>
          <p class="text-xs text-slate-600">A sessão do operador será encerrada e voltará para o login.</p>
        </div>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalSairAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" @click="confirmarSaida">
          <span class="inline-flex items-center gap-1.5">
            <LogOut :size="14" />
            <span>Terminar sessão</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>
</template>
