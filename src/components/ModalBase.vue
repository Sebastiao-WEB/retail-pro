<script setup>
const props = defineProps({
  aberto: { type: Boolean, default: false },
  titulo: { type: String, default: "" },
  mostrarFechar: { type: Boolean, default: true },
});

const emit = defineEmits(["fechar"]);
</script>

<template>
  <div v-if="props.aberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
    <div class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white shadow-2xl">
      <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <h3 class="text-lg font-bold text-slate-900">{{ props.titulo }}</h3>
        <button v-if="props.mostrarFechar" class="text-sm text-slate-500 hover:text-slate-900" @click="emit('fechar')">Fechar</button>
      </div>
      <div class="max-h-[70vh] overflow-auto p-5">
        <slot />
      </div>
    </div>
  </div>
</template>
