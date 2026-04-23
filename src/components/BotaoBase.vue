<script setup>
const props = defineProps({
  tipo: { type: String, default: "button" },
  variante: { type: String, default: "primario" },
  bloco: { type: Boolean, default: false },
});

const mapaVariantes = {
  primario: "bg-slate-900 hover:bg-black text-white",
  secundario: "bg-white hover:bg-slate-50 text-slate-700 border border-slate-300",
  sucesso: "bg-emerald-600 hover:bg-emerald-700 text-white",
  perigo: "bg-red-600 hover:bg-red-700 text-white",
  aviso: "bg-[var(--gold)] hover:brightness-95 text-black",
};
</script>

<template>
  <button
    :type="props.tipo"
    class="rounded-lg px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
    :class="[mapaVariantes[props.variante] || mapaVariantes.primario, props.bloco ? 'w-full' : '']"
  >
    <slot />
  </button>
</template>
