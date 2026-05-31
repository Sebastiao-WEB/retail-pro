<script setup>
import { onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import BotaoBase from "./BotaoBase.vue";
import { authApi } from "../api";
import { temApiConfigurada } from "../api/config";
import { ApiError } from "../api/httpClient";
import { mostrarToastSwal } from "../services/toast";
import { LoaderCircle, Shield, ShieldCheck } from "lucide-vue-next";

const { t } = useI18n();

const apiDisponivel = temApiConfigurada();
const carregando = ref(false);
const activando = ref(false);
const confirmando = ref(false);
const desactivando = ref(false);
const carregandoQr = ref(false);
const carregandoRecovery = ref(false);

const enabled = ref(false);
const pending = ref(false);
const senha = ref("");
const codigoConfirmacao = ref("");
const qrSvg = ref("");
const recoveryCodes = ref([]);
const mostrarRecovery = ref(false);

async function carregarEstado() {
  if (!apiDisponivel) return;
  carregando.value = true;
  try {
    const estado = await authApi.twoFactorStatus();
    enabled.value = !!estado?.enabled;
    pending.value = !!estado?.pending;
    if (pending.value) {
      await carregarQr();
    }
  } catch (erro) {
    mostrarToastSwal(erro?.message || t("settings.security.loadFailed"), "error");
  } finally {
    carregando.value = false;
  }
}

async function carregarQr() {
  carregandoQr.value = true;
  try {
    const resposta = await authApi.twoFactorQrCode();
    qrSvg.value = resposta?.svg || "";
  } catch {
    qrSvg.value = "";
    mostrarToastSwal(t("settings.security.qrFailed"), "error");
  } finally {
    carregandoQr.value = false;
  }
}

async function activar() {
  if (!senha.value.trim()) {
    mostrarToastSwal(t("settings.security.passwordRequired"), "warning");
    return;
  }
  activando.value = true;
  try {
    await authApi.enableTwoFactor(senha.value);
    pending.value = true;
    enabled.value = false;
    senha.value = "";
    await carregarQr();
    mostrarToastSwal(t("settings.security.enableSuccess"), "success");
  } catch (erro) {
    tratarErro(erro, t("settings.security.enableFailed"));
  } finally {
    activando.value = false;
  }
}

async function confirmarActivacao() {
  if (!codigoConfirmacao.value.trim()) return;
  confirmando.value = true;
  try {
    await authApi.confirmTwoFactor(codigoConfirmacao.value.trim());
    pending.value = false;
    enabled.value = true;
    codigoConfirmacao.value = "";
    qrSvg.value = "";
    mostrarToastSwal(t("settings.security.confirmSuccess"), "success");
  } catch (erro) {
    tratarErro(erro, t("settings.security.confirmFailed"));
  } finally {
    confirmando.value = false;
  }
}

async function desactivar() {
  if (!senha.value.trim()) {
    mostrarToastSwal(t("settings.security.passwordRequired"), "warning");
    return;
  }
  if (!window.confirm(t("settings.security.disableConfirm"))) return;

  desactivando.value = true;
  try {
    await authApi.disableTwoFactor(senha.value);
    enabled.value = false;
    pending.value = false;
    senha.value = "";
    recoveryCodes.value = [];
    mostrarRecovery.value = false;
    qrSvg.value = "";
    mostrarToastSwal(t("settings.security.disableSuccess"), "success");
  } catch (erro) {
    tratarErro(erro, t("settings.security.disableFailed"));
  } finally {
    desactivando.value = false;
  }
}

async function mostrarCodigosRecuperacao() {
  if (!senha.value.trim()) {
    mostrarToastSwal(t("settings.security.passwordRequired"), "warning");
    return;
  }
  carregandoRecovery.value = true;
  try {
    const resposta = await authApi.fetchRecoveryCodes(senha.value);
    recoveryCodes.value = Array.isArray(resposta?.recovery_codes) ? resposta.recovery_codes : [];
    mostrarRecovery.value = recoveryCodes.value.length > 0;
    if (!mostrarRecovery.value) {
      mostrarToastSwal(t("settings.security.recoveryFailed"), "warning");
    }
  } catch (erro) {
    tratarErro(erro, t("settings.security.recoveryFailed"));
  } finally {
    carregandoRecovery.value = false;
  }
}

async function regenerarCodigos() {
  if (!senha.value.trim()) {
    mostrarToastSwal(t("settings.security.passwordRequired"), "warning");
    return;
  }
  carregandoRecovery.value = true;
  try {
    const resposta = await authApi.regenerateRecoveryCodes(senha.value);
    recoveryCodes.value = Array.isArray(resposta?.recovery_codes) ? resposta.recovery_codes : [];
    mostrarRecovery.value = recoveryCodes.value.length > 0;
    mostrarToastSwal(t("settings.security.regenerateSuccess"), "success");
  } catch (erro) {
    tratarErro(erro, t("settings.security.regenerateFailed"));
  } finally {
    carregandoRecovery.value = false;
  }
}

function tratarErro(erro, fallback) {
  const mensagem =
    erro instanceof ApiError && erro.payload?.errors?.password?.[0]
      ? erro.payload.errors.password[0]
      : erro?.message || fallback;
  mostrarToastSwal(mensagem, "error");
}

onMounted(() => {
  carregarEstado();
});
</script>

<template>
  <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="border-b border-slate-200 px-4 py-3">
      <h3 class="text-sm font-bold text-slate-900">{{ t("settings.security.title") }}</h3>
      <p class="text-xs text-slate-500">{{ t("settings.security.subtitle") }}</p>
    </div>

    <div class="space-y-4 p-4">
      <p v-if="!apiDisponivel" class="text-sm text-slate-500">{{ t("settings.security.apiOnly") }}</p>

      <template v-else>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
            <ShieldCheck v-if="enabled" :size="16" class="text-emerald-600" />
            <Shield v-else :size="16" class="text-slate-500" />
            <span>{{ t("settings.security.twoFactorHeading") }}</span>
          </div>
          <span
            v-if="carregando"
            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"
          >
            {{ t("common.loading") }}
          </span>
          <span
            v-else-if="enabled"
            class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
          >
            {{ t("settings.security.enabled") }}
          </span>
          <span
            v-else-if="pending"
            class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700"
          >
            {{ t("settings.security.pending") }}
          </span>
          <span v-else class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
            {{ t("settings.security.disabled") }}
          </span>
        </div>

        <p class="text-xs text-slate-500">{{ t("settings.security.description") }}</p>

        <div v-if="!enabled && !pending" class="space-y-3 border-t border-slate-100 pt-4">
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.security.passwordLabel") }}</label>
            <input v-model="senha" type="password" class="rp-input max-w-sm" :placeholder="t('settings.security.passwordPlaceholder')" />
          </div>
          <BotaoBase variante="aviso" :disabled="activando" @click="activar">
            <span class="inline-flex items-center gap-1.5">
              <LoaderCircle v-if="activando" class="animate-spin" :size="14" />
              <span>{{ activando ? t("settings.security.enabling") : t("settings.security.enable") }}</span>
            </span>
          </BotaoBase>
        </div>

        <div v-else-if="pending" class="space-y-3 border-t border-slate-100 pt-4">
          <p class="text-sm text-slate-600">{{ t("settings.security.scanQr") }}</p>
          <div
            v-if="carregandoQr"
            class="flex justify-center rounded-lg border border-slate-100 bg-slate-50 p-6 text-sm text-slate-500"
          >
            {{ t("common.loading") }}
          </div>
          <div
            v-else-if="qrSvg"
            class="flex justify-center rounded-lg border border-slate-100 bg-slate-50 p-4"
            v-html="qrSvg"
          />
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.security.confirmCodeLabel") }}</label>
            <input
              v-model="codigoConfirmacao"
              type="text"
              inputmode="numeric"
              maxlength="6"
              class="rp-input max-w-xs text-center tracking-[0.3em]"
              placeholder="000000"
            />
          </div>
          <BotaoBase variante="aviso" :disabled="confirmando || !codigoConfirmacao.trim()" @click="confirmarActivacao">
            <span class="inline-flex items-center gap-1.5">
              <LoaderCircle v-if="confirmando" class="animate-spin" :size="14" />
              <span>{{ confirmando ? t("settings.security.confirming") : t("settings.security.confirmEnable") }}</span>
            </span>
          </BotaoBase>
        </div>

        <div v-else class="space-y-3 border-t border-slate-100 pt-4">
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("settings.security.passwordLabel") }}</label>
            <input v-model="senha" type="password" class="rp-input max-w-sm" :placeholder="t('settings.security.passwordPlaceholder')" />
          </div>

          <div class="flex flex-wrap gap-2">
            <BotaoBase variante="secundario" :disabled="carregandoRecovery" @click="mostrarCodigosRecuperacao">
              {{ carregandoRecovery ? t("common.loading") : t("settings.security.showRecoveryCodes") }}
            </BotaoBase>
            <BotaoBase variante="secundario" :disabled="carregandoRecovery" @click="regenerarCodigos">
              {{ t("settings.security.regenerateRecoveryCodes") }}
            </BotaoBase>
          </div>

          <ul v-if="mostrarRecovery && recoveryCodes.length" class="grid gap-2 sm:grid-cols-2">
            <li
              v-for="code in recoveryCodes"
              :key="code"
              class="rounded border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700"
            >
              {{ code }}
            </li>
          </ul>
          <p v-if="mostrarRecovery" class="text-xs text-slate-500">{{ t("settings.security.recoveryHint") }}</p>

          <BotaoBase variante="perigo" :disabled="desactivando" @click="desactivar">
            <span class="inline-flex items-center gap-1.5">
              <LoaderCircle v-if="desactivando" class="animate-spin" :size="14" />
              <span>{{ desactivando ? t("settings.security.disabling") : t("settings.security.disable") }}</span>
            </span>
          </BotaoBase>
        </div>
      </template>
    </div>
  </article>
</template>
