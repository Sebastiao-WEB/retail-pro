<script setup>
import { computed, ref, useAttrs } from "vue";
import { LoaderCircle } from "lucide-vue-next";

const props = defineProps({
  tipo: { type: String, default: "button" },
  variante: { type: String, default: "primario" },
  bloco: { type: Boolean, default: false },
  carregando: { type: Boolean, default: false },
  autoCarregarClique: { type: Boolean, default: true },
});
const attrs = useAttrs();
const carregandoInterno = ref(false);

const estaCarregando = computed(() => props.carregando || carregandoInterno.value);
const estaDesativado = computed(() => !!attrs.disabled || estaCarregando.value);
const attrsBotao = computed(() => {
  const { onClick, disabled, class: classe, ...resto } = attrs;
  return resto;
});

const mapaVariantes = {
  primario: "bg-slate-900 hover:bg-black text-white",
  secundario: "bg-white hover:bg-slate-50 text-slate-700 border border-slate-300",
  sucesso: "bg-emerald-600 hover:bg-emerald-700 text-white",
  perigo: "bg-red-600 hover:bg-red-700 text-white",
  aviso: "bg-[var(--gold)] hover:brightness-95 text-black",
};

function normalizarHandlersClique(handlers) {
  if (!handlers) return [];
  return Array.isArray(handlers) ? handlers.filter((item) => typeof item === "function") : [handlers];
}

async function aoClicar(evento) {
  if (!props.autoCarregarClique || estaCarregando.value) return;

  const handlers = normalizarHandlersClique(attrs.onClick);
  if (!handlers.length) return;

  const retornos = handlers.map((handler) => {
    try {
      return handler(evento);
    } catch (erro) {
      return Promise.reject(erro);
    }
  });

  const promessas = retornos.filter((resultado) => resultado && typeof resultado.then === "function");
  if (!promessas.length) return;

  carregandoInterno.value = true;
  await Promise.allSettled(promessas);
  carregandoInterno.value = false;
}
</script>

<template>
  <button
    v-bind="attrsBotao"
    :type="props.tipo"
    :disabled="estaDesativado"
    data-rp-managed="1"
    class="rounded-lg px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
    :class="[mapaVariantes[props.variante] || mapaVariantes.primario, props.bloco ? 'w-full' : '', attrs.class]"
    @click="aoClicar"
  >
    <span v-if="estaCarregando" class="inline-flex items-center justify-center">
      <LoaderCircle :size="14" class="animate-spin" />
    </span>
    <slot v-else />
  </button>
</template>
