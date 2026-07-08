<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { useSessaoStore } from "../store/useSessaoStore";
import { intlLocale } from "../services/localeStorage.js";
import SeletorIdioma from "./SeletorIdioma.vue";
import { Clock3, Cog, LayoutGrid, Receipt, Shield, ShoppingCart, UserRound, UtensilsCrossed } from "lucide-vue-next";
import logoRetailPro from "../assets/rp.png";

const { t, locale } = useI18n();
const route = useRoute();
const sessaoStore = useSessaoStore();
const dataHoraAtual = ref(new Date());
let temporizadorRelogio = null;

const secoes = computed(() => [
  {
    titulo: t("sidebar.main"),
    itens: [
      { nome: t("sidebar.salesHistory"), rota: "/historico-vendas", icon: Clock3 },
      { nome: t("sidebar.closingsHistory"), rota: "/historico-fechos", icon: Receipt },
    ],
  },
  {
    titulo: t("sidebar.system"),
    itens: [
      { nome: t("sidebar.security"), rota: "/seguranca", icon: Shield },
      { nome: t("sidebar.settings"), rota: "/configuracoes", icon: Cog },
    ],
  },
]);

const turnoStatus = computed(() => (sessaoStore.turnoAberto ? t("sidebar.shiftOpen") : t("sidebar.shiftClosed")));
const horarioDigital = computed(() =>
  dataHoraAtual.value.toLocaleTimeString(intlLocale(locale.value), { hour: "2-digit", minute: "2-digit", second: "2-digit" })
);
const nomeOperador = computed(() => sessaoStore.utilizador || t("common.operator"));
const caixaOperador = computed(() => sessaoStore.caixaAtribuido || t("common.noRegister"));
const posItens = computed(() => [
  { nome: t("sidebar.pointOfSale"), rota: { path: "/pos", query: { secao: "venda" } }, icon: ShoppingCart, secao: "venda" },
  { nome: t("sidebar.tables"), rota: "/mesas", icon: UtensilsCrossed, secao: "mesas" },
  { nome: t("sidebar.cashRegister"), rota: { path: "/pos", query: { secao: "caixa" } }, icon: LayoutGrid, secao: "caixa" },
]);

function classeItemPos(secao) {
  if (secao === "mesas") {
    return route.path === "/mesas"
      ? "mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition"
      : "mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white";
  }
  const rotaAtualPos = route.path === "/pos";
  const secaoAtual = route.query?.secao === "caixa" ? "caixa" : "venda";
  const ativo = rotaAtualPos && secaoAtual === secao;
  return ativo
    ? "mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition"
    : "mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white";
}

onMounted(() => {
  temporizadorRelogio = setInterval(() => {
    dataHoraAtual.value = new Date();
  }, 1000);
});

onBeforeUnmount(() => {
  if (temporizadorRelogio) clearInterval(temporizadorRelogio);
});
</script>

<template>
  <aside class="flex h-full w-64 flex-col bg-[var(--dark)] text-slate-100">
    <div class="border-b border-white/10 px-5 py-5">
      <div class="flex items-center gap-3">
        <img :src="logoRetailPro" :alt="t('sidebar.logoAlt')" class="h-9 w-9 rounded-lg object-contain" />
        <div>
          <h1 class="text-sm font-bold leading-tight">{{ t("common.brandName") }} <span class="text-[var(--gold)]">{{ t("common.brandPos") }}</span></h1>
          <p class="text-[10px] text-slate-400">{{ t("sidebar.edition") }}</p>
        </div>
      </div>
    </div>

    <nav class="flex-1 overflow-auto px-3 py-4">
      <div class="mb-5">
        <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ t("sidebar.posSection") }}</p>
        <RouterLink v-for="item in posItens" :key="item.secao" :to="item.rota" :class="classeItemPos(item.secao)">
          <component :is="item.icon" :size="14" class="w-4" />
          <span>{{ item.nome }}</span>
        </RouterLink>
        <div class="mt-2 rounded-lg border border-white/10 bg-[var(--dark-soft)] px-2.5 py-2 text-[12px]">
          <p class="text-slate-400">{{ t("sidebar.shiftStatus") }}</p>
          <p class="font-semibold" :class="sessaoStore.turnoAberto ? 'text-emerald-300' : 'text-red-300'">{{ turnoStatus }}</p>
        </div>
      </div>

      <div v-for="secao in secoes" :key="secao.titulo" class="mb-5">
        <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ secao.titulo }}</p>
        <RouterLink
          v-for="item in secao.itens"
          :key="item.rota"
          :to="item.rota"
          class="mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white"
          active-class="bg-[color:rgba(216,182,90,0.16)] text-[var(--gold)]"
        >
          <component :is="item.icon" :size="14" class="w-4" />
          <span>{{ item.nome }}</span>
        </RouterLink>
      </div>
    </nav>

    <div class="border-t border-white/10 px-4 py-3 space-y-2">
      <SeletorIdioma />
      <div class="rounded-lg px-2 py-2 hover:bg-[var(--dark-soft)]">
        <div class="flex items-center gap-2">
          <div class="flex h-7 w-7 items-center justify-center rounded-md bg-[var(--gold)] text-black">
            <UserRound :size="13" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-semibold text-slate-200">{{ nomeOperador }}</p>
            <p class="truncate text-[10px] text-slate-400">{{ caixaOperador }} · {{ t("sidebar.cashierRole") }}</p>
          </div>
        </div>
        <div class="mt-2 rounded-md border border-white/10 bg-black/40 px-2 py-1 text-center text-[11px] font-bold tracking-wide text-cyan-300">
          {{ horarioDigital }}
        </div>
      </div>
    </div>
  </aside>
</template>
