<script setup>
import { reactive } from "vue";
import { useRouter } from "vue-router";
import BotaoBase from "../../components/BotaoBase.vue";
import { useSessaoStore } from "../../store/useSessaoStore";

const router = useRouter();
const sessaoStore = useSessaoStore();

const form = reactive({
  username: "",
  senha: "",
  codigo: "",
  caixa: "Caixa 01",
});

function entrar() {
  if (!form.username.trim()) return;
  sessaoStore.login({
    username: form.username.trim(),
    caixa: form.caixa,
  });
  router.push("/pos");
}
</script>

<template>
  <section class="flex min-h-screen items-center justify-center bg-[var(--bg-app)] p-6">
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="mb-5 text-center">
        <h1 class="text-2xl font-bold text-slate-900">RetailPro POS</h1>
        <p class="text-sm text-slate-500">Início de turno do caixa</p>
      </div>

      <form class="space-y-3" @submit.prevent="entrar">
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Utilizador</label>
          <input v-model="form.username" class="rp-input" placeholder="username" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Senha / PIN</label>
          <input v-model="form.senha" type="password" class="rp-input" placeholder="••••••" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Código do operador (opcional)</label>
          <input v-model="form.codigo" class="rp-input" placeholder="Código ou cartão" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold text-slate-600">Caixa atribuído</label>
          <select v-model="form.caixa" class="rp-input">
            <option>Caixa 01</option>
            <option>Caixa 02</option>
            <option>Caixa 03</option>
          </select>
        </div>
        <BotaoBase tipo="submit" bloco variante="aviso">Entrar no sistema</BotaoBase>
      </form>
    </div>
  </section>
</template>
