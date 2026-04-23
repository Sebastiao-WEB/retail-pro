import { defineStore } from "pinia";
import { carregarClientesIntegrado } from "../services/integracaoApi";

export const useClienteStore = defineStore("clientes", {
  state: () => ({
    clientes: [],
    carregado: false,
  }),
  getters: {
    totalClientes: (state) => state.clientes.length,
  },
  actions: {
    async carregarClientes() {
      if (this.carregado) return;
      this.clientes = await carregarClientesIntegrado();
      this.carregado = true;
    },
    adicionarCliente(novoCliente) {
      this.clientes.unshift({
        ...novoCliente,
        id: Date.now(),
      });
    },
    atualizarCliente(clienteAtualizado) {
      const indice = this.clientes.findIndex((cliente) => cliente.id === clienteAtualizado.id);
      if (indice === -1) return;
      this.clientes[indice] = { ...clienteAtualizado };
    },
    removerCliente(clienteId) {
      this.clientes = this.clientes.filter((cliente) => cliente.id !== clienteId);
    },
  },
});
