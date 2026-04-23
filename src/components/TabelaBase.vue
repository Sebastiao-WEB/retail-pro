<script setup>
const props = defineProps({
  colunas: { type: Array, default: () => [] },
  linhas: { type: Array, default: () => [] },
  vazioTexto: { type: String, default: "Sem registos para apresentar." },
  mostrarPaginacao: { type: Boolean, default: true },
  resumoRodape: { type: String, default: "" },
  paginaAtual: { type: Number, default: 1 },
  totalPaginas: { type: Number, default: 2 },
});
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-[var(--border)] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
    <div v-if="$slots.tabs" class="border-b border-slate-200 bg-white px-4 py-2">
      <div class="flex flex-wrap items-center gap-4">
        <slot name="tabs" />
      </div>
    </div>

    <div v-if="$slots.filtros" class="border-b border-slate-200 bg-slate-50 px-4 py-2">
      <div class="flex flex-wrap items-center gap-2">
        <slot name="filtros" />
      </div>
    </div>

    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th v-for="coluna in colunas" :key="coluna.chave" class="px-4 py-2.5">{{ coluna.rotulo }}</th>
            <th v-if="$slots.acoes" class="px-4 py-2.5 text-right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="linhas.length === 0">
            <td :colspan="colunas.length + ($slots.acoes ? 1 : 0)" class="px-4 py-8 text-center text-xs text-slate-500">
              {{ vazioTexto }}
            </td>
          </tr>
          <tr v-for="(linha, indice) in linhas" :key="linha.id || indice" class="border-t border-slate-100 text-slate-700 hover:bg-slate-50">
            <td v-for="coluna in colunas" :key="coluna.chave" class="px-4 py-2.5 text-[12px]">
              <slot :name="`coluna-${coluna.chave}`" :linha="linha">
                {{ linha[coluna.chave] }}
              </slot>
            </td>
            <td v-if="$slots.acoes" class="px-4 py-2.5">
              <slot name="acoes" :linha="linha" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="props.mostrarPaginacao" class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-2.5">
      <span class="text-[11px] text-slate-500">
        {{ props.resumoRodape || `Mostrando ${props.linhas.length} registos` }}
      </span>
      <div class="flex items-center gap-1.5 text-[11px]">
        <button class="rounded-md border border-slate-200 px-2 py-1 text-slate-500 hover:bg-slate-50">Anterior</button>
        <button
          v-for="pagina in props.totalPaginas"
          :key="pagina"
          class="rounded-md px-2 py-1"
          :class="pagina === props.paginaAtual ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-500 hover:bg-slate-50'"
        >
          {{ pagina }}
        </button>
        <button class="rounded-md border border-slate-200 px-2 py-1 text-slate-500 hover:bg-slate-50">Próximo</button>
      </div>
    </div>
  </div>
</template>
