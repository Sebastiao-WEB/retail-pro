<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import BotaoBase from "../../components/BotaoBase.vue";
import { useSessaoStore } from "../../store/useSessaoStore";
import { authApi, modoApiAtivo, temApiConfigurada } from "../../api";
import { ApiError } from "../../api/httpClient";
import { mostrarToastSwal } from "../../services/toast";
import { LogIn, LoaderCircle } from "lucide-vue-next";
import logoRetailPro from "../../assets/rp.png";

const router = useRouter();
const sessaoStore = useSessaoStore();
const carregando = ref(false);
const caixasDisponiveis = ref([]);
const aguardandoSelecaoCaixa = ref(false);

const form = reactive({
  username: "",
  senha: "",
  codigo: "",
  caixa: "Caixa 01",
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

function concluirLogin(resposta) {
  const user = resposta?.user || {};
  const token = resposta?.access_token || "";
  if (!token) throw new Error("Token JWT não recebido da API.");
  const sourceLocation = extrairSourceLocation(user);
  sessaoStore.login({
    username: user.name || form.username.trim(),
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
  aguardandoSelecaoCaixa.value = false;
  caixasDisponiveis.value = [];
  router.push("/pos");
}

async function entrar() {
  if (!form.username.trim()) return;

  if (!temApiConfigurada()) {
    if (modoApiAtivo()) {
      mostrarToastSwal("Modo API ativo, mas VITE_API_URL não está configurado.", "error");
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
    concluirLogin(resposta);
  } catch (erro) {
    if (erro instanceof ApiError && erro.payload?.requires_register_selection) {
      caixasDisponiveis.value = Array.isArray(erro.payload.registers) ? erro.payload.registers : [];
      aguardandoSelecaoCaixa.value = caixasDisponiveis.value.length > 0;
      if (caixasDisponiveis.value.length === 1) {
        form.codigo = caixasDisponiveis.value[0].code || caixasDisponiveis.value[0].name || "";
        carregando.value = false;
        return entrar();
      }
      if (aguardandoSelecaoCaixa.value) {
        mostrarToastSwal("Seleccione o caixa em que vai operar.", "info");
        carregando.value = false;
        return;
      }
    }

    const mensagemOriginal = String(erro?.message || "");
    const mensagem =
      mensagemOriginal.toLowerCase().includes("failed to fetch")
        ? "Falha de conexão com o servidor."
        : mensagemOriginal || "Falha ao autenticar com o backend.";
    mostrarToastSwal(mensagem, "error");
  } finally {
    carregando.value = false;
  }
}
</script>

<template>
  <section class="flex min-h-screen items-center justify-center bg-[var(--bg-app)] p-6">
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="mb-[-20px] text-center">
        <img :src="logoRetailPro" alt="RetailPro POS" class="mx-auto h-45 w-auto object-contain" />
      </div>

      <form class="space-y-3" @submit.prevent="entrar">
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Utilizador</label>
          <input v-model="form.username" class="rp-input" placeholder="username" :disabled="aguardandoSelecaoCaixa" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Senha / PIN</label>
          <input v-model="form.senha" type="password" class="rp-input" placeholder="••••••" :disabled="aguardandoSelecaoCaixa" />
        </div>
        <div v-if="apiMode && aguardandoSelecaoCaixa">
          <label class="mb-1 block text-xs font-semibold text-slate-600">Caixa para operar</label>
          <select v-model="form.codigo" class="rp-input">
            <option value="">Seleccione o caixa...</option>
            <option v-for="caixa in caixasDisponiveis" :key="caixa.id" :value="caixa.code">
              {{ caixa.code }} — {{ caixa.name }}
            </option>
          </select>
        </div>
        <div v-else-if="apiMode">
          <label class="mb-1 block text-xs font-semibold text-slate-600">Código do caixa (se tiver mais do que um)</label>
          <input
            v-model="form.codigo"
            class="rp-input"
            placeholder="Ex: CX-01 ou Caixa 01"
          />
        </div>
        <div v-if="!apiMode">
          <label class="mb-1 block text-xs font-semibold text-slate-600">Caixa atribuído</label>
          <select v-model="form.caixa" class="rp-input">
            <option>Caixa 01</option>
            <option>Caixa 02</option>
            <option>Caixa 03</option>
          </select>
        </div>
        <BotaoBase tipo="submit" bloco variante="aviso" :disabled="carregando || (aguardandoSelecaoCaixa && !form.codigo)">
          <span class="inline-flex items-center gap-1.5">
            <LoaderCircle v-if="carregando" class="animate-spin" :size="14" />
            <LogIn v-else :size="14" />
            <span>{{ carregando ? "A autenticar..." : aguardandoSelecaoCaixa ? "Confirmar caixa" : "Entrar no sistema" }}</span>
          </span>
        </BotaoBase>
      </form>
    </div>
  </section>
</template>
