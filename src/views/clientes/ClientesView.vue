<script setup>
import { computed, reactive, ref } from "vue";
import { useClienteStore } from "../../store/useClienteStore";
import BotaoBase from "../../components/BotaoBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import TabelaBase from "../../components/TabelaBase.vue";

const clienteStore = useClienteStore();
const pesquisa = ref("");
const modalAberto = ref(false);
const editar = ref(false);

const formulario = reactive({
  id: null,
  nome: "",
  telefone: "",
});

const colunas = [
  { chave: "nome", rotulo: "Nome" },
  { chave: "telefone", rotulo: "Telefone" },
];

const linhas = computed(() =>
  clienteStore.clientes.filter((cliente) =>
    `${cliente.nome} ${cliente.telefone}`.toLowerCase().includes(pesquisa.value.toLowerCase())
  )
);

function novoCliente() {
  editar.value = false;
  Object.assign(formulario, { id: null, nome: "", telefone: "" });
  modalAberto.value = true;
}

function editarCliente(cliente) {
  editar.value = true;
  Object.assign(formulario, { ...cliente });
  modalAberto.value = true;
}

function guardar() {
  if (editar.value) clienteStore.atualizarCliente({ ...formulario });
  else clienteStore.adicionarCliente({ ...formulario });
  modalAberto.value = false;
}
</script>

<template>
  <section class="space-y-4">
    <TabelaBase :colunas="colunas" :linhas="linhas" :resumo-rodape="`Mostrando ${linhas.length} clientes`">
      <template #filtros>
        <input v-model="pesquisa" class="rp-input !max-w-xs !py-1.5 !text-[11px]" placeholder="Pesquisar por nome ou telefone..." />
        <button class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-500">Filtrar</button>
        <span class="ml-auto">
          <BotaoBase @click="novoCliente">Adicionar Cliente</BotaoBase>
        </span>
      </template>
      <template #acoes="{ linha }">
        <div class="flex justify-end gap-1">
          <button class="flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 text-[11px] text-slate-500 hover:bg-slate-50" @click="editarCliente(linha)">
            ✎
          </button>
          <button class="flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 text-[11px] text-red-500 hover:bg-red-50" @click="clienteStore.removerCliente(linha.id)">
            ⌫
          </button>
        </div>
      </template>
    </TabelaBase>
  </section>

  <ModalBase :aberto="modalAberto" :mostrar-fechar="false" :titulo="editar ? 'Editar cliente' : 'Novo cliente'" @fechar="modalAberto = false">
    <form id="form-cliente" class="space-y-3" @submit.prevent="guardar">
      <input v-model="formulario.nome" required class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Nome do cliente" />
      <input v-model="formulario.telefone" required class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Telefone" />
    </form>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase tipo="submit" form="form-cliente" variante="aviso">Guardar cliente</BotaoBase>
      </div>
    </template>
  </ModalBase>
</template>
