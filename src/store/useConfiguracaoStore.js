import { defineStore } from "pinia";
import { temApiConfigurada } from "../api/config";
import { companyApi } from "../api/modules/companyApi";

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
  copiasImpressao: 1,
  larguraTalao: "80mm",
  corteAutomatico: true,
  abrirGavetaAutomatico: true,
  gavetaPin: 0,
  somToastsAtivo: true,
};

function construirPayloadPersistencia(estado) {
  const payload = {};
  for (const chave of Object.keys(configuracoesPadrao)) {
    payload[chave] = estado[chave] ?? configuracoesPadrao[chave];
  }
  return payload;
}

export const useConfiguracaoStore = defineStore("configuracoes", {
  state: () => ({
    ...configuracoesPadrao,
    carregado: false,
  }),
  actions: {
    aplicarDadosEmpresa(dados = {}) {
      const empresa = {
        nomeEmpresa: String(dados.nomeEmpresa || this.nomeEmpresa || ""),
        nif: String(dados.nif || ""),
        email: String(dados.email || ""),
        telefone: String(dados.telefone || ""),
        endereco: String(dados.endereco || ""),
        banco: String(dados.banco || ""),
        iban: String(dados.iban || ""),
        rodapeFacturas:
          dados.rodapeFacturas !== undefined
            ? String(dados.rodapeFacturas || "")
            : String(this.rodapeFacturas || configuracoesPadrao.rodapeFacturas),
      };
      Object.assign(this, empresa);
      this.salvar();
    },
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
    async hidratarDadosEmpresaRemotos() {
      if (!temApiConfigurada()) return;
      const resposta = await companyApi.obterPerfil();
      this.aplicarDadosEmpresa(resposta?.data || {});
    },
    async salvarDadosEmpresaRemotos() {
      if (!temApiConfigurada()) {
        this.salvar();
        return;
      }
      const payload = {
        nomeEmpresa: this.nomeEmpresa,
        nif: this.nif,
        email: this.email,
        telefone: this.telefone,
        endereco: this.endereco,
        banco: this.banco,
        iban: this.iban,
        rodapeFacturas: this.rodapeFacturas,
      };
      const resposta = await companyApi.atualizarPerfil(payload);
      this.aplicarDadosEmpresa(resposta?.data || payload);
    },
    salvar() {
      localStorage.setItem(
        CHAVE_LOCAL_STORAGE,
        JSON.stringify(construirPayloadPersistencia(this.$state))
      );
    },
    definirImpressoraPadrao(impressora) {
      this.impressoraPadrao = String(impressora || "");
      this.salvar();
    },
    definirCopiasImpressao(valor) {
      const numero = Number(valor || 1);
      this.copiasImpressao = Math.max(1, Math.min(5, Number.isFinite(numero) ? Math.floor(numero) : 1));
      this.salvar();
    },
    definirLarguraTalao(valor) {
      this.larguraTalao = valor === "58mm" ? "58mm" : "80mm";
      this.salvar();
    },
    definirCorteAutomatico(valor) {
      this.corteAutomatico = !!valor;
      this.salvar();
    },
    definirAbrirGavetaAutomatico(valor) {
      this.abrirGavetaAutomatico = !!valor;
      this.salvar();
    },
    definirGavetaPin(valor) {
      this.gavetaPin = Number(valor) === 1 ? 1 : 0;
      this.salvar();
    },
    definirSomToastsAtivo(valor) {
      this.somToastsAtivo = !!valor;
      this.salvar();
    },
  },
});
