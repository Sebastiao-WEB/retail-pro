import { defineStore } from "pinia";

const CHAVE_LOCAL_STORAGE = "retailpro:configuracoes";

const configuracoesPadrao = {
  nomeEmpresa: "Empresa Demo Lda",
  nif: "400000099",
  email: "geral@empresa.co.mz",
  telefone: "+258 21 000 000",
  endereco: "Av. 25 de Setembro, 420, Maputo, Moçambique",
  banco: "BCI — Banco Comercial e de Investimentos",
  iban: "MZ59 0000 0000 1234 5678 901",
  prefixoFactura: "FT",
  proximoNumero: 43,
  ano: "2026",
  prefixoNotaCredito: "NC",
  prefixoRecibo: "RC",
  lembreteVencimento: true,
  confirmacaoEmissao: true,
  alertaPagamento: true,
  relatorioSemanal: false,
  alertasFiscais: true,
  idiomaFacturas: "Português (Moçambique)",
  rodapeFacturas: "Obrigado pela sua preferência. Para reclamações contacte: geral@empresa.co.mz",
  impressoraPadrao: "",
};

export const useConfiguracaoStore = defineStore("configuracoes", {
  state: () => ({
    ...configuracoesPadrao,
    carregado: false,
  }),
  actions: {
    hidratar() {
      if (this.carregado) return;
      this.carregado = true;
      try {
        const guardado = localStorage.getItem(CHAVE_LOCAL_STORAGE);
        if (!guardado) return;
        const dados = JSON.parse(guardado);
        Object.assign(this, configuracoesPadrao, dados);
      } catch {
        // Mantém padrão quando storage inválido.
      }
    },
    salvar() {
      const payload = { ...this };
      delete payload.carregado;
      localStorage.setItem(CHAVE_LOCAL_STORAGE, JSON.stringify(payload));
    },
    definirImpressoraPadrao(impressora) {
      this.impressoraPadrao = String(impressora || "");
      this.salvar();
    },
  },
});
