import { defineStore } from "pinia";

const CHAVE_SESSAO = "retailpro:sessao";

export const useSessaoStore = defineStore("sessao", {
  state: () => ({
    carregado: false,
    utilizador: null,
    caixaAtribuido: "",
    turnoAberto: false,
    fundoInicial: 0,
    aberturaEm: "",
    historicoFecho: [],
  }),
  getters: {
    estaLogado: (state) => !!state.utilizador,
  },
  actions: {
    hidratar() {
      if (this.carregado) return;
      this.carregado = true;
      try {
        const raw = localStorage.getItem(CHAVE_SESSAO);
        if (!raw) return;
        const dados = JSON.parse(raw);
        Object.assign(this, dados);
      } catch {
        // mantém estado inicial se houver erro de parsing
      }
    },
    salvar() {
      const payload = {
        utilizador: this.utilizador,
        caixaAtribuido: this.caixaAtribuido,
        turnoAberto: this.turnoAberto,
        fundoInicial: this.fundoInicial,
        aberturaEm: this.aberturaEm,
        historicoFecho: this.historicoFecho,
      };
      localStorage.setItem(CHAVE_SESSAO, JSON.stringify(payload));
    },
    login({ username, caixa }) {
      this.utilizador = username;
      this.caixaAtribuido = caixa;
      this.salvar();
    },
    logout() {
      this.utilizador = null;
      this.caixaAtribuido = "";
      this.turnoAberto = false;
      this.fundoInicial = 0;
      this.aberturaEm = "";
      this.salvar();
    },
    abrirTurno({ fundoInicial }) {
      this.turnoAberto = true;
      this.fundoInicial = Number(fundoInicial || 0);
      this.aberturaEm = new Date().toISOString();
      this.salvar();
    },
    fecharTurno(relatorio) {
      this.historicoFecho.unshift({
        ...relatorio,
        fechadoEm: new Date().toISOString(),
      });
      this.turnoAberto = false;
      this.fundoInicial = 0;
      this.aberturaEm = "";
      this.salvar();
    },
  },
});
