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
  ivaPercentual: 0,
  stock: 0,
});

const colunas = [
  { chave: "nome", rotulo: "Produto" },
  { chave: "codigoBarras", rotulo: "Código" },
  { chave: "categoria", rotulo: "Categoria" },
  { chave: "precoVendaComIva", rotulo: "Preço + IVA" },
  { chave: "stock", rotulo: "Stock" },
];

const linhas = computed(() =>
  produtoStore.produtos.filter((produto) =>
    `${produto.nome} ${produto.categoria}`.toLowerCase().includes(pesquisa.value.toLowerCase())
  )
);

function abrirNovo() {
  emEdicao.value = false;
  Object.assign(formulario, { id: null, nome: "", codigoBarras: "", categoria: "", precoVenda: 0, ivaPercentual: 0, stock: 0 });
  modalAberto.value = true;
}

function abrirEdicao(produto) {
  emEdicao.value = true;
  Object.assign(formulario, { ...produto });
  modalAberto.value = true;
}

function guardarProduto() {
  const precoBase = Number(formulario.precoVenda || 0);
  const iva = Math.max(0, Math.min(100, Number(formulario.ivaPercentual || 0)));
  if (emEdicao.value) {
    produtoStore.atualizarProduto({ ...formulario, precoVenda: precoBase, ivaPercentual: iva });
  } else {
    produtoStore.adicionarProduto({ ...formulario, precoVenda: precoBase, ivaPercentual: iva });
  }
  modalAberto.value = false;
}

const precoComIvaFormulario = computed(() => {
  const precoBase = Number(formulario.precoVenda || 0);
  const iva = Math.max(0, Math.min(100, Number(formulario.ivaPercentual || 0)));
  return precoBase * (1 + iva / 100);
});
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
      <template #coluna-precoVendaComIva="{ linha }">
        <div class="leading-tight">
          <p class="font-semibold">{{ formatarMoeda(linha.precoVendaComIva ?? linha.precoVenda) }}</p>
          <p class="text-[10px] text-slate-500">IVA: {{ Number(linha.ivaPercentual || 0) }}%</p>
        </div>
      </template>
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
        <input v-model.number="formulario.precoVenda" type="number" min="0" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Preço de venda (sem IVA)" />
        <input v-model.number="formulario.ivaPercentual" type="number" min="0" max="100" step="0.01" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="IVA (%)" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <input v-model.number="formulario.stock" type="number" min="0" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Stock" />
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
          Preço final (+ IVA): <strong>{{ formatarMoeda(precoComIvaFormulario) }}</strong>
        </div>
      </div>
      <BotaoBase tipo="submit" bloco>Guardar</BotaoBase>
    </form>
  </ModalBase>
</template>
