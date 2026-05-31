<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import BotaoBase from "./BotaoBase.vue";
import ModalBase from "./ModalBase.vue";
import SeletorIdioma from "./SeletorIdioma.vue";
import { useSessaoStore } from "../store/useSessaoStore";
import { apiConfig, authApi, modoApiAtivo, temApiConfigurada } from "../api";
import { verificarConexaoBackend } from "../services/backendStatus";
import { intlLocale } from "../services/localeStorage.js";
import {
  Activity,
  CheckCircle2,
  Clock3,
  Gauge,
  Globe,
  Info,
  LogOut,
  RotateCcw,
  ServerCog,
  TriangleAlert,
  X,
} from "lucide-vue-next";

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const sessaoStore = useSessaoStore();

const dataAtual = computed(() =>
  new Intl.DateTimeFormat(intlLocale(locale.value), {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  }).format(new Date())
);

const paginaAtual = computed(() => {
  const chaves = {
    pos: { titulo: t("header.pages.pos.title"), subtitulo: t("header.pages.pos.subtitle") },
    "historico-vendas": { titulo: t("header.pages.historicoVendas.title"), subtitulo: t("header.pages.historicoVendas.subtitle") },
    "historico-fechos": { titulo: t("header.pages.historicoFechos.title"), subtitulo: t("header.pages.historicoFechos.subtitle") },
    configuracoes: { titulo: t("header.pages.configuracoes.title"), subtitulo: t("header.pages.configuracoes.subtitle") },
  };
  return chaves[route.name] || { titulo: t("header.defaultTitle"), subtitulo: t("header.defaultSubtitle") };
});
const utilizador = computed(() => sessaoStore.utilizador || t("common.cashierOperator"));
const caixa = computed(() => sessaoStore.caixaAtribuido || t("common.noRegister"));
const modalSairAberto = ref(false);
const modalStatusBackendAberto = ref(false);
const backendConectado = ref(false);
const verificandoBackend = ref(false);
const ultimoStatusBackend = ref(null);
const ultimaVerificacaoEm = ref("");
let timerVerificacao = null;

const mostrarStatusBackend = computed(() => modoApiAtivo());
const textoStatusBackend = computed(() => {
  if (!modoApiAtivo()) return t("header.backend.mock");
  if (!temApiConfigurada()) return t("header.backend.notConfigured");
  if (verificandoBackend.value) return t("header.backend.checking");
  return backendConectado.value ? t("header.backend.connected") : t("header.backend.disconnected");
});
const classeStatusBackend = computed(() => {
  if (!modoApiAtivo()) return "bg-slate-100 text-slate-600";
  if (!temApiConfigurada()) return "bg-amber-100 text-amber-800";
  if (verificandoBackend.value) return "bg-blue-100 text-blue-800";
  return backendConectado.value ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700";
});
const timeoutBackendMs = computed(() => Number(apiConfig.timeoutMs || 15000));
const endpointBackend = computed(() => ultimoStatusBackend.value?.endpoint || t("common.notAvailable"));
const ultimoHttpStatus = computed(() => ultimoStatusBackend.value?.statusHttp || t("common.notAvailable"));
const ultimaVerificacaoFormatada = computed(() => {
  if (!ultimaVerificacaoEm.value) return t("common.notAvailable");
  return new Date(ultimaVerificacaoEm.value).toLocaleString(intlLocale(locale.value));
});
const motivoDetalhadoBackend = computed(() => {
  const motivo = String(ultimoStatusBackend.value?.motivo || "");
  if (!motivo) return t("common.noData");
  if (motivo === "ok") return t("header.backend.reasonOk");
  if (motivo === "network") return t("header.backend.reasonNetwork");
  if (motivo === "sem_url") return t("header.backend.reasonNoUrl");
  if (motivo === "mock") return t("header.backend.reasonMock");
  if (motivo.startsWith("http_")) return t("header.backend.reasonHttp", { status: motivo.replace("http_", "") });
  return motivo;
});

async function atualizarStatusBackend() {
  if (!modoApiAtivo()) return;
  verificandoBackend.value = true;
  const status = await verificarConexaoBackend();
  ultimoStatusBackend.value = status;
  ultimaVerificacaoEm.value = new Date().toISOString();
  backendConectado.value = !!status.conectado;
  verificandoBackend.value = false;
}

onMounted(async () => {
  await atualizarStatusBackend();
  timerVerificacao = window.setInterval(atualizarStatusBackend, 15000);
});

onUnmounted(() => {
  if (timerVerificacao) window.clearInterval(timerVerificacao);
});

function sair() {
  modalSairAberto.value = true;
}

function abrirDetalhesBackend() {
  modalStatusBackendAberto.value = true;
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
      <button
        v-if="mostrarStatusBackend"
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold"
        :class="classeStatusBackend"
        :title="textoStatusBackend"
        @click="abrirDetalhesBackend"
      >
        <Info :size="12" />
        {{ textoStatusBackend }}
      </button>
      <SeletorIdioma />
      <div class="ml-2 text-right">
        <p class="text-xs font-semibold text-slate-800">{{ utilizador }}</p>
        <p class="text-[11px] text-slate-500">{{ caixa }} · {{ dataAtual }}</p>
      </div>

      <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" @click="sair">
        {{ t("header.logout") }}
      </button>
    </div>
  </header>

  <ModalBase :aberto="modalSairAberto" :mostrar-fechar="false" :titulo="t('header.logoutModal.title')" @fechar="modalSairAberto = false">
    <div class="space-y-4">
      <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
          <TriangleAlert :size="14" />
        </span>
        <div>
          <p class="font-semibold text-slate-900">{{ t("header.logoutModal.question") }}</p>
          <p class="text-xs text-slate-600">{{ t("header.logoutModal.description") }}</p>
        </div>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalSairAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>{{ t("common.cancel") }}</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" @click="confirmarSaida">
          <span class="inline-flex items-center gap-1.5">
            <LogOut :size="14" />
            <span>{{ t("header.logoutModal.confirm") }}</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>

  <ModalBase :aberto="modalStatusBackendAberto" :titulo="t('header.backendModal.title')" @fechar="modalStatusBackendAberto = false">
    <div class="space-y-3 text-sm text-slate-700">
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
        <p class="inline-flex items-center gap-2">
          <CheckCircle2 :size="14" class="text-emerald-600" />
          <span><strong>{{ t("header.backendModal.status") }}</strong> {{ textoStatusBackend }}</span>
        </p>
        <p class="mt-1 inline-flex items-center gap-2">
          <Info :size="14" class="text-blue-600" />
          <span><strong>{{ t("header.backendModal.detail") }}</strong> {{ motivoDetalhadoBackend }}</span>
        </p>
      </div>
      <div class="rounded-lg border border-slate-200 p-3">
        <p class="inline-flex items-center gap-2">
          <Globe :size="14" class="text-slate-600" />
          <span><strong>{{ t("header.backendModal.endpoint") }}</strong> {{ endpointBackend }}</span>
        </p>
        <p class="mt-1 inline-flex items-center gap-2">
          <Gauge :size="14" class="text-amber-600" />
          <span><strong>{{ t("header.backendModal.timeout") }}</strong> {{ timeoutBackendMs }} ms</span>
        </p>
        <p class="mt-1 inline-flex items-center gap-2">
          <Activity :size="14" class="text-indigo-600" />
          <span><strong>{{ t("header.backendModal.lastHttpStatus") }}</strong> {{ ultimoHttpStatus }}</span>
        </p>
        <p class="mt-1 inline-flex items-center gap-2">
          <Clock3 :size="14" class="text-slate-500" />
          <span><strong>{{ t("header.backendModal.lastCheck") }}</strong> {{ ultimaVerificacaoFormatada }}</span>
        </p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
        <p class="inline-flex items-center gap-2">
          <ServerCog :size="14" class="text-slate-700" />
          <span>{{ t("header.backendModal.description") }}</span>
        </p>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalStatusBackendAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <X :size="14" />
            <span>{{ t("common.close") }}</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="aviso" :disabled="verificandoBackend" @click="atualizarStatusBackend">
          <span class="inline-flex items-center gap-1.5">
            <RotateCcw :size="14" :class="verificandoBackend ? 'animate-spin' : ''" />
            <span>{{ verificandoBackend ? t("common.checking") : t("common.checkingNow") }}</span>
          </span>
        </BotaoBase>
      </div>
    </template>
  </ModalBase>
</template>
