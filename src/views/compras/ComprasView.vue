<script setup>
import { computed, reactive, ref } from "vue";
import { useProdutoStore } from "../../store/useProdutoStore";
import { useVendaStore } from "../../store/useVendaStore";
import TabelaBase from "../../components/TabelaBase.vue";
import BotaoBase from "../../components/BotaoBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import { formatarData, formatarMoeda } from "../../services/formatadores";

const produtoStore = useProdutoStore();
const vendaStore = useVendaStore();

const colunas = [
  { chave: "id", rotulo: "Nº Compra" },
  { chave: "fornecedor", rotulo: "Fornecedor" },
  { chave: "data", rotulo: "Data" },
  { chave: "total", rotulo: "Total" },
];

const formulario = reactive({
  fornecedor: "Fornecedor Local",
  produtoId: null,
  quantidade: 1,
  custoUnitario: 0,
});

const produtoSelecionado = computed(() => produtoStore.produtos.find((produto) => produto.id === Number(formulario.produtoId)));
const modalCadastroAberto = ref(false);

function registarCompra() {
  if (!produtoSelecionado.value || formulario.quantidade <= 0 || formulario.custoUnitario <= 0) return;
  const compra = {
    fornecedor: formulario.fornecedor,
    data: new Date().toISOString(),
    itens: [
      {
        nome: produtoSelecionado.value.nome,
        quantidade: formulario.quantidade,
        custoUnitario: formulario.custoUnitario,
      },
    ],
    total: formulario.quantidade * formulario.custoUnitario,
  };

  vendaStore.registarCompra(compra);
  produtoStore.reporStock(produtoSelecionado.value.id, formulario.quantidade);
  formulario.quantidade = 1;
  formulario.custoUnitario = 0;
  formulario.produtoId = null;
  modalCadastroAberto.value = false;
}
</script>

<template>
  <section class="space-y-5">
    <div class="flex justify-end">
      <BotaoBase @click="modalCadastroAberto = true">Nova Compra</BotaoBase>
    </div>

    <TabelaBase :colunas="colunas" :linhas="vendaStore.compras" :resumo-rodape="`Mostrando ${vendaStore.compras.length} compras`">
      <template #filtros>
        <button class="rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">Todas</button>
        <button class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] text-slate-500">Este mês</button>
        <span class="ml-auto">
          <button class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-500">Exportar</button>
        </span>
      </template>
      <template #coluna-data="{ linha }">{{ formatarData(linha.data) }}</template>
      <template #coluna-total="{ linha }">{{ formatarMoeda(linha.total) }}</template>
    </TabelaBase>
  </section>

  <ModalBase :aberto="modalCadastroAberto" :mostrar-fechar="false" titulo="Nova compra" @fechar="modalCadastroAberto = false">
    <form id="form-nova-compra" class="space-y-3" @submit.prevent="registarCompra">
      <input v-model="formulario.fornecedor" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Fornecedor" />
      <select v-model="formulario.produtoId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option :value="null">Selecionar produto</option>
        <option v-for="produto in produtoStore.produtos" :key="produto.id" :value="produto.id">{{ produto.nome }}</option>
      </select>
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <input v-model.number="formulario.quantidade" type="number" min="1" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Quantidade" />
        <input v-model.number="formulario.custoUnitario" type="number" min="0" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Custo unitário" />
      </div>
    </form>
    <template #footer>
      <div class="flex justify-end gap-2">
        <BotaoBase variante="perigo" @click="modalCadastroAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase tipo="submit" form="form-nova-compra" variante="aviso">Adicionar produtos à compra</BotaoBase>
      </div>
    </template>
  </ModalBase>
</template>
