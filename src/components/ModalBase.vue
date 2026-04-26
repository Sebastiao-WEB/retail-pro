<script setup>
const props = defineProps({
  aberto: { type: Boolean, default: false },
  titulo: { type: String, default: "" },
  mostrarFechar: { type: Boolean, default: true },
});

const emit = defineEmits(["fechar"]);
</script>

<template>
  <Teleport to="body">
    <div v-if="props.aberto" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/60 p-4">
      <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="border-b border-slate-200 px-5 py-4">
          <h3 class="text-lg font-bold text-slate-900">{{ props.titulo }}</h3>
        </div>
        <div class="max-h-[70vh] overflow-auto p-5">
          <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-slate-200 px-5 py-3">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
