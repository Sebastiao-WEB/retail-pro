<script setup>
import { onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import BotaoBase from "../../components/BotaoBase.vue";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";
import { mostrarToastSwal } from "../../services/toast";
import { enviarAbrirGaveta } from "../../services/talaoImpressao";
import { LoaderCircle, PanelBottomOpen, RefreshCcw, Save, X } from "lucide-vue-next";

const ICON_SIZE = 20;

const { t } = useI18n();
const configuracoes = useConfiguracaoStore();
const impressorasDisponiveis = ref([]);
const carregandoImpressoras = ref(false);
const testandoGaveta = ref(false);
const bloqueadoAdministrador = true;

onMounted(() => {
  configuracoes.hidratar();
  carregarDadosEmpresa();
  carregarImpressoras();
});

async function guardarConfiguracoes() {
  try {
    await configuracoes.salvarDadosEmpresaRemotos();
    mostrarToastSwal(t("settings.toast.saved"), "success");
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("settings.toast.saveFailed"), "error");
  }
}

function guardarConfiguracoesImpressao() {
  try {
    configuracoes.salvar();
    mostrarToastSwal(t("settings.toast.saved"), "success");
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("settings.toast.saveFailed"), "error");
  }
}

async function carregarDadosEmpresa() {
  try {
    await configuracoes.hidratarDadosEmpresaRemotos();
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("settings.toast.loadFailed"), "warning");
  }
}

async function carregarImpressoras() {
  const fallbackGlobal = Array.isArray(window.__IMPRESSORAS_INSTALADAS) ? window.__IMPRESSORAS_INSTALADAS : [];
  if (!window.api?.listarImpressoras && fallbackGlobal.length) {
    impressorasDisponiveis.value = fallbackGlobal.map((item) => item.displayName || item.name).filter(Boolean);
    if (!configuracoes.impressoraPadrao && impressorasDisponiveis.value.length) {
      configuracoes.definirImpressoraPadrao(impressorasDisponiveis.value[0]);
    }
    return;
  }
  if (!window.api?.listarImpressoras) {
    mostrarToastSwal(t("settings.toast.printersDesktopOnly"), "warning");
    impressorasDisponiveis.value = [];
    return;
  }
  carregandoImpressoras.value = true;
  try {
    const resposta = await window.api.listarImpressoras();
    const listaApi = Array.isArray(resposta) ? resposta : resposta?.printers || [];
    const lista = listaApi.length ? listaApi : fallbackGlobal;
    impressorasDisponiveis.value = lista.map((item) => item.displayName || item.name).filter(Boolean);
    if (!impressorasDisponiveis.value.length) {
      mostrarToastSwal(t("settings.toast.noPrintersFound"), "warning");
    }
    if (!configuracoes.impressoraPadrao && impressorasDisponiveis.value.length) {
      configuracoes.definirImpressoraPadrao(impressorasDisponiveis.value[0]);
    }
  } catch {
    mostrarToastSwal(t("settings.toast.listPrintersFailed"), "error");
    impressorasDisponiveis.value = [];
  } finally {
    carregandoImpressoras.value = false;
  }
}

async function testarGaveta() {
  if (testandoGaveta.value) return;
  if (!window.api?.abrirGaveta) {
    mostrarToastSwal(t("settings.toast.drawerDesktopOnly"), "warning");
    return;
  }
  if (!configuracoes.impressoraPadrao) {
    mostrarToastSwal(t("settings.toast.setPrinterFirst"), "warning");
    return;
  }
  testandoGaveta.value = true;
  try {
    const resultado = await enviarAbrirGaveta({ configuracao: configuracoes });
    if (!resultado?.ok) {
      mostrarToastSwal(resultado?.error || t("settings.toast.drawerPulseFailed"), "error");
      return;
    }
    mostrarToastSwal(t("settings.toast.drawerPulseSent"), "success");
  } finally {
    testandoGaveta.value = false;
  }
}
</script>

<template>
  <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_300px]">
    <div class="space-y-4">
      <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-900">{{ t("settings.company.title") }}</h3>
          <p class="text-xs text-slate-500">{{ t("settings.company.subtitle") }}</p>
        </div>
        <div class="space-y-3 p-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.company.name") }}</label>
              <input v-model="configuracoes.nomeEmpresa" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.company.nuit") }}</label>
              <input v-model="configuracoes.nif" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.company.email") }}</label>
              <input v-model="configuracoes.email" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.company.phone") }}</label>
              <input v-model="configuracoes.telefone" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.company.address") }}</label>
            <input v-model="configuracoes.endereco" class="rp-input" :disabled="bloqueadoAdministrador" />
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.company.bank") }}</label>
              <input v-model="configuracoes.banco" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.company.iban") }}</label>
              <input v-model="configuracoes.iban" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
            <BotaoBase variante="secundario" :disabled="bloqueadoAdministrador">
              <span class="inline-flex items-center gap-1.5">
                <X :size="14" />
                <span>{{ t("common.cancel") }}</span>
              </span>
            </BotaoBase>
            <BotaoBase variante="aviso" :disabled="bloqueadoAdministrador" @click="guardarConfiguracoes">
              <span class="inline-flex items-center gap-1.5">
                <Save :size="14" />
                <span>{{ t("common.saveChanges") }}</span>
              </span>
            </BotaoBase>
          </div>
        </div>
      </article>

    </div>

    <div class="space-y-4">
      <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-900">{{ t("settings.printing.title") }}</h3>
          <p class="text-xs text-slate-500">{{ t("settings.printing.subtitle") }}</p>
        </div>
        <div class="space-y-3 p-4">
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.printing.defaultPrinter") }}</label>
            <select :value="configuracoes.impressoraPadrao" class="rp-input" @change="configuracoes.definirImpressoraPadrao($event.target.value)">
              <option v-if="carregandoImpressoras" value="">{{ t("settings.printing.loadingPrinters") }}</option>
              <option v-else-if="!impressorasDisponiveis.length" value="">{{ t("settings.printing.noPrinters") }}</option>
              <option v-for="impressora in impressorasDisponiveis" :key="impressora" :value="impressora">
                {{ impressora }}
              </option>
            </select>
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.printing.copies") }}</label>
              <input
                :value="configuracoes.copiasImpressao"
                type="number"
                min="1"
                max="5"
                class="rp-input"
                @input="configuracoes.definirCopiasImpressao($event.target.value)"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.printing.receiptWidth") }}</label>
              <select :value="configuracoes.larguraTalao" class="rp-input" @change="configuracoes.definirLarguraTalao($event.target.value)">
                <option value="80mm">{{ t("settings.printing.width80") }}</option>
                <option value="58mm">{{ t("settings.printing.width58") }}</option>
              </select>
            </div>
          </div>
          <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700">
            <span>{{ t("settings.printing.autoCut") }}</span>
            <input
              :checked="configuracoes.corteAutomatico"
              type="checkbox"
              class="h-4 w-4 accent-amber-500"
              @change="configuracoes.definirCorteAutomatico($event.target.checked)"
            />
          </label>
          <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700">
            <div>
              <p class="font-semibold text-slate-700">{{ t("settings.printing.openDrawerOnCash") }}</p>
              <p class="text-[11px] text-slate-500">{{ t("settings.printing.openDrawerHint") }}</p>
            </div>
            <input
              :checked="configuracoes.abrirGavetaAutomatico"
              type="checkbox"
              class="h-4 w-4 accent-amber-500"
              @change="configuracoes.definirAbrirGavetaAutomatico($event.target.checked)"
            />
          </label>
          <div v-if="configuracoes.abrirGavetaAutomatico">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.printing.drawerPort") }}</label>
            <select :value="configuracoes.gavetaPin" class="rp-input" @change="configuracoes.definirGavetaPin($event.target.value)">
              <option :value="0">{{ t("settings.printing.drawerPin2") }}</option>
              <option :value="1">{{ t("settings.printing.drawerPin5") }}</option>
            </select>
          </div>
          <div class="flex justify-end gap-2">
            <BotaoBase
              variante="secundario"
              class="!px-3 !py-2.5"
              :disabled="testandoGaveta"
              :title="testandoGaveta ? t('common.testing') : t('settings.printing.testDrawer')"
              :aria-label="testandoGaveta ? t('common.testing') : t('settings.printing.testDrawer')"
              @click="testarGaveta"
            >
              <LoaderCircle v-if="testandoGaveta" class="animate-spin" :size="ICON_SIZE" :stroke-width="2.2" />
              <PanelBottomOpen v-else :size="ICON_SIZE" :stroke-width="2.2" />
            </BotaoBase>
            <BotaoBase
              variante="secundario"
              class="!px-3 !py-2.5"
              :disabled="carregandoImpressoras"
              :title="t('common.refreshList')"
              :aria-label="t('common.refreshList')"
              @click="carregarImpressoras"
            >
              <RefreshCcw :class="{ 'animate-spin': carregandoImpressoras }" :size="ICON_SIZE" :stroke-width="2.2" />
            </BotaoBase>
            <BotaoBase
              variante="aviso"
              class="!px-3 !py-2.5"
              :title="t('settings.printing.savePrinting')"
              :aria-label="t('settings.printing.savePrinting')"
              @click="guardarConfiguracoesImpressao"
            >
              <Save :size="ICON_SIZE" :stroke-width="2.2" />
            </BotaoBase>
          </div>
          <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700">
            <div>
              <p class="font-semibold text-slate-700">{{ t("settings.printing.alertSound") }}</p>
              <p class="text-[11px] text-slate-500">{{ t("settings.printing.alertSoundHint") }}</p>
            </div>
            <input
              :checked="configuracoes.somToastsAtivo"
              type="checkbox"
              class="h-4 w-4 accent-amber-500"
              @change="configuracoes.definirSomToastsAtivo($event.target.checked)"
            />
          </label>
        </div>
      </article>
    </div>
  </section>
</template>
