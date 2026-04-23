<script setup>
import { computed, reactive, ref } from "vue";
import TabelaBase from "../../components/TabelaBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import BotaoBase from "../../components/BotaoBase.vue";
import { useProdutoStore } from "../../store/useProdutoStore";
import { formatarMoeda } from "../../services/formatadores";

const produtoStore = useProdutoStore();
const pesquisa = ref("");
const modalAberto = ref(false);
const emEdicao = ref(false);

const formulario = reactive({
  id: null,
  nome: "",
  codigoBarras: "",
  categoria: "",
  precoVenda: 0,
  stock: 0,
});

const colunas = [
  { chave: "nome", rotulo: "Produto" },
  { chave: "codigoBarras", rotulo: "Código" },
  { chave: "categoria", rotulo: "Categoria" },
  { chave: "precoVenda", rotulo: "Preço" },
  { chave: "stock", rotulo: "Stock" },
];

const linhas = computed(() =>
  produtoStore.produtos.filter((produto) =>
    `${produto.nome} ${produto.categoria}`.toLowerCase().includes(pesquisa.value.toLowerCase())
  )
);

function abrirNovo() {
  emEdicao.value = false;
  Object.assign(formulario, { id: null, nome: "", codigoBarras: "", categoria: "", precoVenda: 0, stock: 0 });
  modalAberto.value = true;
}

function abrirEdicao(produto) {
  emEdicao.value = true;
  Object.assign(formulario, { ...produto });
  modalAberto.value = true;
}

function guardarProduto() {
  if (emEdicao.value) {
    produtoStore.atualizarProduto({ ...formulario });
  } else {
    produtoStore.adicionarProduto({ ...formulario });
  }
  modalAberto.value = false;
}
</script>

<template>
  <section class="space-y-4">
    <TabelaBase :colunas="colunas" :linhas="linhas" :resumo-rodape="`Mostrando ${linhas.length} registos de produtos`">
      <template #filtros>
        <input v-model="pesquisa" class="rp-input !max-w-xs !py-1.5 !text-[11px]" placeholder="Pesquisar produto..." />
        <button class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-500">Filtrar</button>
        <button class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-500">Exportar</button>
        <span class="ml-auto">
          <BotaoBase @click="abrirNovo">Adicionar Produto</BotaoBase>
        </span>
      </template>
      <template #coluna-precoVenda="{ linha }">{{ formatarMoeda(linha.precoVenda) }}</template>
      <template #coluna-stock="{ linha }">
        <span :class="linha.stock <= 10 ? 'font-bold text-red-600' : 'text-slate-700'">{{ linha.stock }}</span>
      </template>
      <template #acoes="{ linha }">
        <div class="flex justify-end gap-1">
          <button class="flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 text-[11px] text-slate-500 hover:bg-slate-50" @click="abrirEdicao(linha)">
            ✎
          </button>
        </div>
      </template>
    </TabelaBase>
  </section>

  <ModalBase :aberto="modalAberto" :titulo="emEdicao ? 'Editar produto' : 'Adicionar produto'" @fechar="modalAberto = false">
    <form class="space-y-3" @submit.prevent="guardarProduto">
      <input v-model="formulario.nome" required class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Nome do produto" />
      <input v-model="formulario.codigoBarras" required class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Código de barras" />
      <input v-model="formulario.categoria" required class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Categoria" />
      <div class="grid grid-cols-2 gap-3">
        <input v-model.number="formulario.precoVenda" type="number" min="0" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Preço de venda" />
        <input v-model.number="formulario.stock" type="number" min="0" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Stock" />
      </div>
      <BotaoBase tipo="submit" bloco>Guardar</BotaoBase>
    </form>
  </ModalBase>
</template>
