<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import BotaoBase from "../../components/BotaoBase.vue";
import SeletorIdioma from "../../components/SeletorIdioma.vue";
import { useSessaoStore } from "../../store/useSessaoStore";
import { useProdutoStore } from "../../store/useProdutoStore";
import { authApi, modoApiAtivo, temApiConfigurada } from "../../api";
import { ApiError } from "../../api/httpClient";
import { mostrarToastSwal } from "../../services/toast";
import { LogIn, LoaderCircle, ShieldCheck } from "lucide-vue-next";
import logoRetailPro from "../../assets/rp.png";

const { t } = useI18n();
const router = useRouter();
const sessaoStore = useSessaoStore();
const produtoStore = useProdutoStore();
const carregando = ref(false);
const caixasDisponiveis = ref([]);
const aguardandoSelecaoCaixa = ref(false);
const aguardandoDoisFactores = ref(false);
const twoFactorToken = ref("");
const modoDoisFactores = ref("code");

const form = reactive({
  username: "",
  senha: "",
  codigo: "",
  caixa: t("login.register01"),
  codigoDoisFactores: "",
  codigoRecuperacao: "",
});

const apiMode = modoApiAtivo();

function extrairSourceLocation(user) {
  const fonte =
    user?.source_location ||
    user?.stock_location ||
    user?.default_stock_location ||
    user?.register?.source_location ||
    user?.register?.stock_location ||
    null;

  return {
    id: fonte?.id ?? null,
    codigo: fonte?.code || fonte?.codigo || "",
    nome: fonte?.name || fonte?.nome || "",
  };
}

async function concluirLogin(resposta) {
  const user = resposta?.user || {};
  const token = resposta?.access_token || "";
  if (!token) throw new Error("Token JWT não recebido da API.");
  const sourceLocation = extrairSourceLocation(user);
  sessaoStore.login({
    username: user.name || form.username.trim(),
    userId: user.id ?? null,
    caixa: user.register?.name || user.caixa_atribuido || form.caixa,
    perfil: user.role || "CASHIER",
    token,
    refreshToken: resposta?.refresh_token || "",
    registerId: user.register?.id ?? null,
    registerCodigo: user.register?.code || user.register?.codigo || "",
    sourceLocationId: sourceLocation.id,
    sourceLocationCodigo: sourceLocation.codigo,
    sourceLocationNome: sourceLocation.nome,
  });

  if (temApiConfigurada()) {
    try {
      await produtoStore.carregarInventarioPosLogin({});
    } catch (erro) {
      const cache = produtoStore.carregarCatalogoDeCache({});
      if (!cache) {
        throw erro;
      }
      mostrarToastSwal(t("login.toast.inventoryFromCache"), "warning");
    }
  }

  reiniciarEstadoLogin();
  router.push("/pos");
}

function reiniciarEstadoLogin() {
  aguardandoSelecaoCaixa.value = false;
  aguardandoDoisFactores.value = false;
  twoFactorToken.value = "";
  modoDoisFactores.value = "code";
  caixasDisponiveis.value = [];
  form.codigoDoisFactores = "";
  form.codigoRecuperacao = "";
}

function voltarAoLogin() {
  reiniciarEstadoLogin();
}

function activarDesafioDoisFactores(payload) {
  twoFactorToken.value = String(payload?.two_factor_token || "");
  aguardandoDoisFactores.value = Boolean(twoFactorToken.value);
  aguardandoSelecaoCaixa.value = false;
  modoDoisFactores.value = "code";
  form.codigoDoisFactores = "";
  form.codigoRecuperacao = "";
  if (aguardandoDoisFactores.value) {
    mostrarToastSwal(t("login.toast.twoFactorRequired"), "info");
  }
}

async function confirmarDoisFactores() {
  if (!twoFactorToken.value) return;

  carregando.value = true;
  try {
    const resposta = await authApi.twoFactorChallenge({
      twoFactorToken: twoFactorToken.value,
      code: modoDoisFactores.value === "code" ? form.codigoDoisFactores.trim() : null,
      recoveryCode: modoDoisFactores.value === "recovery" ? form.codigoRecuperacao.trim() : null,
    });
    await concluirLogin(resposta);
  } catch (erro) {
    if (erro instanceof ApiError && erro.payload?.two_factor_expired) {
      voltarAoLogin();
      mostrarToastSwal(t("login.toast.twoFactorExpired"), "error");
      return;
    }
    if (erro instanceof ApiError && erro.payload?.invalid_two_factor_code) {
      mostrarToastSwal(t("login.toast.twoFactorInvalid"), "error");
      return;
    }

    const mensagemOriginal = String(erro?.message || "");
    const mensagem =
      mensagemOriginal.toLowerCase().includes("failed to fetch")
        ? t("login.toast.connectionFailed")
        : mensagemOriginal || t("login.toast.authFailed");
    mostrarToastSwal(mensagem, "error");
  } finally {
    carregando.value = false;
  }
}

async function entrar() {
  if (aguardandoDoisFactores.value) {
    return confirmarDoisFactores();
  }

  if (!form.username.trim()) return;

  if (!temApiConfigurada()) {
    if (modoApiAtivo()) {
      mostrarToastSwal(t("login.toast.apiModeNoUrl"), "error");
      return;
    }
    sessaoStore.login({
      username: form.username.trim(),
      caixa: form.caixa,
      perfil: "CASHIER",
    });
    router.push("/pos");
    return;
  }

  carregando.value = true;
  try {
    const resposta = await authApi.login({
      username: form.username.trim(),
      password: form.senha,
      registerCode: form.codigo.trim() || null,
    });
    await concluirLogin(resposta);
  } catch (erro) {
    if (erro instanceof ApiError && erro.payload?.requires_two_factor) {
      activarDesafioDoisFactores(erro.payload);
      carregando.value = false;
      return;
    }

    if (erro instanceof ApiError && erro.payload?.requires_register_selection) {
      caixasDisponiveis.value = Array.isArray(erro.payload.registers) ? erro.payload.registers : [];
      aguardandoSelecaoCaixa.value = caixasDisponiveis.value.length > 0;
      if (caixasDisponiveis.value.length === 1) {
        form.codigo = caixasDisponiveis.value[0].code || caixasDisponiveis.value[0].name || "";
        carregando.value = false;
        return entrar();
      }
      if (aguardandoSelecaoCaixa.value) {
        mostrarToastSwal(t("login.toast.selectRegister"), "info");
        carregando.value = false;
        return;
      }
    }

    const mensagemOriginal = String(erro?.message || "");
    const mensagem =
      mensagemOriginal.toLowerCase().includes("failed to fetch")
        ? t("login.toast.connectionFailed")
        : mensagemOriginal || t("login.toast.authFailed");
    mostrarToastSwal(mensagem, "error");
  } finally {
    carregando.value = false;
  }
}
</script>

<template>
  <section class="relative flex min-h-screen items-center justify-center bg-[var(--bg-app)] p-6">
    <div class="pos-login-top absolute right-6 top-6">
      <SeletorIdioma />
    </div>

    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="mb-[-20px] text-center">
        <img :src="logoRetailPro" :alt="t('login.logoAlt')" class="mx-auto h-45 w-auto object-contain" />
      </div>

      <div v-if="aguardandoDoisFactores" class="mb-4 text-center">
        <h1 class="text-base font-semibold text-slate-800">{{ t("login.twoFactor.heading") }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ t("login.twoFactor.description") }}</p>
      </div>

      <form class="space-y-3" @submit.prevent="entrar">
        <template v-if="!aguardandoDoisFactores">
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("login.username") }}</label>
            <input v-model="form.username" class="rp-input" :placeholder="t('login.usernamePlaceholder')" :disabled="aguardandoSelecaoCaixa" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("login.password") }}</label>
            <input v-model="form.senha" type="password" class="rp-input" :placeholder="t('login.passwordPlaceholder')" :disabled="aguardandoSelecaoCaixa" />
          </div>
          <div v-if="apiMode && aguardandoSelecaoCaixa">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("login.registerToOperate") }}</label>
            <select v-model="form.codigo" class="rp-input">
              <option value="">{{ t("login.selectRegister") }}</option>
              <option v-for="caixa in caixasDisponiveis" :key="caixa.id" :value="caixa.code">
                {{ caixa.code }} — {{ caixa.name }}
              </option>
            </select>
          </div>
          <div v-else-if="apiMode">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("login.registerCode") }}</label>
            <input
              v-model="form.codigo"
              class="rp-input"
              :placeholder="t('login.registerCodePlaceholder')"
            />
          </div>
          <div v-if="!apiMode">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("login.assignedRegister") }}</label>
            <select v-model="form.caixa" class="rp-input">
              <option>{{ t("login.register01") }}</option>
              <option>{{ t("login.register02") }}</option>
              <option>{{ t("login.register03") }}</option>
            </select>
          </div>
        </template>

        <template v-else>
          <div v-if="modoDoisFactores === 'code'">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("login.twoFactor.codeLabel") }}</label>
            <input
              v-model="form.codigoDoisFactores"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="6"
              class="rp-input text-center tracking-[0.3em]"
              placeholder="000000"
            />
          </div>
          <div v-else>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ t("login.twoFactor.recoveryLabel") }}</label>
            <input
              v-model="form.codigoRecuperacao"
              type="text"
              autocomplete="one-off-code"
              class="rp-input"
              :placeholder="t('login.twoFactor.recoveryPlaceholder')"
            />
          </div>

          <div class="flex flex-col gap-2 text-center text-xs">
            <button
              v-if="modoDoisFactores === 'code'"
              type="button"
              class="font-semibold text-slate-600 hover:text-slate-800"
              @click="modoDoisFactores = 'recovery'"
            >
              {{ t("login.twoFactor.useRecovery") }}
            </button>
            <button
              v-else
              type="button"
              class="font-semibold text-slate-600 hover:text-slate-800"
              @click="modoDoisFactores = 'code'"
            >
              {{ t("login.twoFactor.useCode") }}
            </button>
            <button type="button" class="text-slate-500 hover:text-slate-700" @click="voltarAoLogin">
              {{ t("login.twoFactor.backToLogin") }}
            </button>
          </div>
        </template>

        <BotaoBase
          tipo="submit"
          bloco
          variante="aviso"
          :disabled="carregando || (aguardandoSelecaoCaixa && !form.codigo) || (aguardandoDoisFactores && modoDoisFactores === 'code' && !form.codigoDoisFactores.trim()) || (aguardandoDoisFactores && modoDoisFactores === 'recovery' && !form.codigoRecuperacao.trim())"
        >
          <span class="inline-flex items-center gap-1.5">
            <LoaderCircle v-if="carregando" class="animate-spin" :size="14" />
            <ShieldCheck v-else-if="aguardandoDoisFactores" :size="14" />
            <LogIn v-else :size="14" />
            <span>{{
              carregando
                ? aguardandoDoisFactores
                  ? t("login.twoFactor.verifying")
                  : temApiConfigurada()
                    ? t("login.loadingInventory")
                    : t("login.authenticating")
                : aguardandoDoisFactores
                  ? t("login.twoFactor.submit")
                  : aguardandoSelecaoCaixa
                    ? t("login.confirmRegister")
                    : t("login.enterSystem")
            }}</span>
          </span>
        </BotaoBase>
      </form>
    </div>
  </section>
</template>
