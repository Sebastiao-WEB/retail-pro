<script setup>
import { computed, onMounted, ref } from "vue";
import BotaoBase from "../../components/BotaoBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import { useVendaStore } from "../../store/useVendaStore";
import { useSessaoStore } from "../../store/useSessaoStore";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";

const vendaStore = useVendaStore();
const sessaoStore = useSessaoStore();
const configuracaoStore = useConfiguracaoStore();

const vendaSelecionada = ref(null);
const modalDetalhesAberto = ref(false);
const mensagem = ref("");
const tipoMensagem = ref("sucesso");
const modalSolicitarReversaoAberto = ref(false);
const vendaParaReversao = ref(null);
const motivoReversao = ref("");

onMounted(() => {
  configuracaoStore.hidratar();
});

const vendasDoTurnoCaixa = computed(() => {
  const caixaAtual = sessaoStore.caixaAtribuido;
  const abertura = sessaoStore.aberturaEm ? new Date(sessaoStore.aberturaEm).getTime() : null;
  if (!caixaAtual || !abertura) return [];
  return vendaStore.vendas.filter((venda) => {
    const dataVenda = new Date(venda.data).getTime();
    const mesmaCaixa = venda.caixa ? venda.caixa === caixaAtual : true;
    return mesmaCaixa && dataVenda >= abertura;
  });
});
const solicitacoesPendentesPorVenda = computed(() => {
  const mapa = new Map();
  vendaStore.solicitacoesPendentes.forEach((item) => {
    mapa.set(item.vendaId, item);
  });
  return mapa;
});

function formatarMT(valor) {
  return `${new Intl.NumberFormat("pt-MZ", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(valor || 0))} MT`;
}

function formatarIva(valor) {
  const numero = Number(valor || 0);
  return `${Number.isFinite(numero) ? numero : 0}%`;
}

function obterIvaItem(item) {
  const subtotal = Number(item?.subtotal || 0);
  const ivaPercentual = Number(item?.ivaPercentual || 0);
  if (Number.isFinite(item?.valorIvaUnitario)) {
    return Number(item.valorIvaUnitario || 0) * Number(item?.quantidade || 0);
  }
  if (ivaPercentual <= 0 || subtotal <= 0) return 0;
  return subtotal - subtotal / (1 + ivaPercentual / 100);
}

function obterTotalIvaVenda(venda) {
  return (venda?.itens || []).reduce((acc, item) => acc + obterIvaItem(item), 0);
}

function formatarData(valor) {
  return new Date(valor).toLocaleString("pt-MZ");
}

function abrirDetalhes(venda) {
  vendaSelecionada.value = venda;
  modalDetalhesAberto.value = true;
}

function escaparHtml(valor) {
  return String(valor ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function gerarHtmlTalao(venda) {
  const linhasItens = (venda.itens || [])
    .map(
      (item) => `
        <tr>
          <td>${escaparHtml(item.nome)}</td>
          <td style="text-align:center;">${item.quantidade}</td>
          <td style="text-align:center;">${formatarIva(item.ivaPercentual)}</td>
          <td style="text-align:right;">${formatarMT(obterIvaItem(item))}</td>
          <td style="text-align:right;">${formatarMT(item.subtotal)}</td>
        </tr>
      `
    )
    .join("");

  return `
    <!doctype html>
    <html lang="pt">
      <head>
        <meta charset="UTF-8" />
        <title>Talão</title>
        <style>
          @page { size: 80mm auto; margin: 4mm; }
          body { font-family: Arial, sans-serif; width: 80mm; margin: 0 auto; color: #111; font-size: 12px; }
          .center { text-align: center; }
          .title { font-size: 18px; font-weight: 700; margin: 4px 0; }
          .muted { color: #555; font-size: 11px; }
          .sep { border-top: 1px dashed #444; margin: 8px 0; }
          table { width: 100%; border-collapse: collapse; }
          th, td { padding: 4px 0; font-size: 11px; }
          th { text-transform: uppercase; font-size: 10px; color: #666; border-bottom: 1px solid #ddd; }
          .tot { font-weight: 700; font-size: 14px; }
          .foot { margin-top: 10px; font-size: 10px; color: #555; text-align: center; }
        </style>
      </head>
      <body>
        <div class="center">
          <div class="title">${escaparHtml(configuracaoStore.nomeEmpresa || "RetailPro POS")}</div>
          <div class="muted">2a via do Talão</div>
          <div class="muted">NUIT: ${escaparHtml(configuracaoStore.nif || "")}</div>
          <div class="muted">${escaparHtml(configuracaoStore.endereco || "")}</div>
          <div class="muted">${escaparHtml(configuracaoStore.telefone || "")}</div>
          <div class="muted">Data: ${escaparHtml(new Date(venda.data).toLocaleString("pt-MZ"))}</div>
        </div>
        <div class="sep"></div>
        <div><strong>Cliente:</strong> ${escaparHtml(venda.cliente)}</div>
        <div><strong>Pagamento:</strong> ${escaparHtml(venda.metodoPagamento)}</div>
        <div class="sep"></div>
        <table>
          <thead>
            <tr><th>Item</th><th>Qtd</th><th>IVA %</th><th>IVA MT</th><th>Total</th></tr>
          </thead>
          <tbody>
            ${linhasItens}
          </tbody>
        </table>
        <div class="sep"></div>
        <table>
          <tbody>
            <tr><td>Subtotal</td><td style="text-align:right">${formatarMT(venda.subtotal ?? venda.total)}</td></tr>
            <tr><td>Total IVA</td><td style="text-align:right">${formatarMT(obterTotalIvaVenda(venda))}</td></tr>
            <tr><td>Desconto</td><td style="text-align:right">- ${formatarMT(venda.descontoAplicado || 0)}</td></tr>
            <tr><td>Total</td><td style="text-align:right" class="tot">${formatarMT(venda.total)}</td></tr>
            <tr><td>Valor Pago</td><td style="text-align:right">${venda.metodoPagamento === "Dinheiro" ? formatarMT(venda.valorPago) : "--"}</td></tr>
            <tr><td>Troco</td><td style="text-align:right">${venda.metodoPagamento === "Dinheiro" ? formatarMT(venda.troco) : "--"}</td></tr>
          </tbody>
        </table>
        <div class="sep"></div>
        <div class="foot">${escaparHtml(configuracaoStore.rodapeFacturas || "Obrigado pela preferência.")}</div>
      </body>
    </html>
  `;
}

async function reimprimirVenda(venda) {
  if (!window.api?.imprimirTalao) {
    tipoMensagem.value = "erro";
    mensagem.value = "Reimpressão disponível apenas na versão desktop (Electron).";
    return;
  }
  if (!configuracaoStore.impressoraPadrao) {
    tipoMensagem.value = "erro";
    mensagem.value = "Defina a impressora padrão em Configurações para reimprimir.";
    return;
  }
  try {
    const resultado = await window.api.imprimirTalao({
      html: gerarHtmlTalao(venda),
      deviceName: configuracaoStore.impressoraPadrao,
      copies: 1,
      larguraTalao: "80mm",
      corteAutomatico: true,
    });
    if (!resultado?.ok) {
      tipoMensagem.value = "erro";
      mensagem.value = resultado?.error || "Falha ao reimprimir recibo.";
      return;
    }
    tipoMensagem.value = "sucesso";
    mensagem.value = `Recibo reenviado para impressão em ${configuracaoStore.impressoraPadrao}.`;
  } catch {
    tipoMensagem.value = "erro";
    mensagem.value = "Falha ao reimprimir recibo.";
  }
}

function abrirSolicitacaoReversao(venda) {
  if (venda.estado === "Revertida") {
    tipoMensagem.value = "erro";
    mensagem.value = "Venda já foi revertida.";
    return;
  }
  if (solicitacoesPendentesPorVenda.value.has(venda.id)) {
    tipoMensagem.value = "erro";
    mensagem.value = "Já existe solicitação de reversão pendente para esta venda.";
    return;
  }
  vendaParaReversao.value = venda;
  motivoReversao.value = "";
  modalSolicitarReversaoAberto.value = true;
}

function solicitarReversao() {
  if (!vendaParaReversao.value) return;
  const venda = vendaParaReversao.value;
  const resultado = vendaStore.solicitarReversao({
    vendaId: venda.id,
    referencia: venda.referencia || String(venda.id),
    solicitadoPor: sessaoStore.utilizador || "Operador",
    motivo: motivoReversao.value.trim(),
  });
  if (!resultado.ok) {
    tipoMensagem.value = "erro";
    mensagem.value = resultado.erro || "Não foi possível solicitar reversão.";
    return;
  }
  modalSolicitarReversaoAberto.value = false;
  vendaParaReversao.value = null;
  tipoMensagem.value = "sucesso";
  mensagem.value = "Solicitação de reversão enviada ao gerente para aprovação.";
}
</script>

<template>
  <section class="space-y-4">
    <div class="rp-card p-4">
      <div class="mb-3 flex items-center justify-between border-b border-slate-200 pb-3">
        <div>
          <h3 class="text-base font-bold text-slate-900">Histórico de vendas do caixa</h3>
          <p class="text-xs text-slate-500">
            Caixa: <strong>{{ sessaoStore.caixaAtribuido || "Sem caixa" }}</strong> ·
            Turno: <strong>{{ sessaoStore.aberturaEm ? formatarData(sessaoStore.aberturaEm) : "não iniciado" }}</strong>
          </p>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-3 py-2">Referência</th>
              <th class="px-3 py-2">Data</th>
              <th class="px-3 py-2">Cliente</th>
              <th class="px-3 py-2">Pagamento</th>
              <th class="px-3 py-2">Estado</th>
              <th class="px-3 py-2">Total</th>
              <th class="px-3 py-2 text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!vendasDoTurnoCaixa.length">
              <td colspan="7" class="px-3 py-8 text-center text-xs text-slate-500">
                Sem vendas no turno atual deste caixa.
              </td>
            </tr>
            <tr v-for="venda in vendasDoTurnoCaixa" :key="venda.id" class="border-t border-slate-100 text-[12px] hover:bg-slate-50">
              <td class="px-3 py-2 font-semibold text-slate-700">{{ venda.referencia || venda.id }}</td>
              <td class="px-3 py-2 text-slate-600">{{ formatarData(venda.data) }}</td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ venda.cliente }}</td>
              <td class="px-3 py-2 text-slate-600">{{ venda.metodoPagamento }}</td>
              <td class="px-3 py-2">
                <span
                  class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                  :class="
                    venda.estado === 'Revertida'
                      ? 'bg-red-100 text-red-700'
                      : solicitacoesPendentesPorVenda.has(venda.id)
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-emerald-100 text-emerald-700'
                  "
                >
                  {{ venda.estado === "Revertida" ? "Revertida" : solicitacoesPendentesPorVenda.has(venda.id) ? "Reversão pendente" : "Concluída" }}
                </span>
              </td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ formatarMT(venda.total) }}</td>
              <td class="px-3 py-2">
                <div class="flex justify-end gap-2">
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                    title="Detalhes"
                    aria-label="Detalhes"
                    @click="abrirDetalhes(venda)"
                  >
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </button>
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                    title="Solicitar reversão"
                    aria-label="Solicitar reversão"
                    :disabled="venda.estado === 'Revertida' || solicitacoesPendentesPorVenda.has(venda.id)"
                    @click="abrirSolicitacaoReversao(venda)"
                  >
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.1" viewBox="0 0 24 24" aria-hidden="true">
                      <polyline points="1 4 1 10 7 10" />
                      <path d="M3.51 15a9 9 0 1 0 .49-9" />
                    </svg>
                  </button>
                  <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--gold)] text-black hover:brightness-95"
                    title="Reimprimir"
                    aria-label="Reimprimir"
                    @click="reimprimirVenda(venda)"
                  >
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                      <polyline points="6 9 6 2 18 2 18 9" />
                      <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                      <rect x="6" y="14" width="12" height="8" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="mensagem" class="mt-3 text-sm font-semibold" :class="tipoMensagem === 'erro' ? 'text-red-600' : 'text-emerald-700'">
        {{ mensagem }}
      </p>
    </div>
  </section>

  <ModalBase :aberto="modalDetalhesAberto" titulo="Detalhes da venda" @fechar="modalDetalhesAberto = false">
    <div v-if="vendaSelecionada" class="space-y-3">
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        <p><strong>Data:</strong> {{ formatarData(vendaSelecionada.data) }}</p>
        <p><strong>Cliente:</strong> {{ vendaSelecionada.cliente }}</p>
        <p><strong>Caixa:</strong> {{ vendaSelecionada.caixa || sessaoStore.caixaAtribuido }}</p>
        <p><strong>Operador:</strong> {{ vendaSelecionada.operador || sessaoStore.utilizador }}</p>
        <p><strong>Método:</strong> {{ vendaSelecionada.metodoPagamento }}</p>
      </div>

      <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-3 py-2">Item</th>
              <th class="px-3 py-2">Qtd</th>
              <th class="px-3 py-2">IVA %</th>
              <th class="px-3 py-2">IVA MT</th>
              <th class="px-3 py-2">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in vendaSelecionada.itens || []" :key="idx" class="border-t border-slate-100">
              <td class="px-3 py-2">{{ item.nome }}</td>
              <td class="px-3 py-2">{{ item.quantidade }}</td>
              <td class="px-3 py-2">{{ formatarIva(item.ivaPercentual) }}</td>
              <td class="px-3 py-2">{{ formatarMT(obterIvaItem(item)) }}</td>
              <td class="px-3 py-2">{{ formatarMT(item.subtotal) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        <p><strong>Subtotal:</strong> {{ formatarMT(vendaSelecionada.subtotal || vendaSelecionada.total) }}</p>
        <p><strong>Total IVA:</strong> {{ formatarMT(obterTotalIvaVenda(vendaSelecionada)) }}</p>
        <p><strong>Desconto:</strong> - {{ formatarMT(vendaSelecionada.descontoAplicado || 0) }}</p>
        <p><strong>Total:</strong> {{ formatarMT(vendaSelecionada.total) }}</p>
      </div>
    </div>
  </ModalBase>

  <ModalBase :aberto="modalSolicitarReversaoAberto" titulo="Confirmar solicitação de reversão" @fechar="modalSolicitarReversaoAberto = false">
    <div class="space-y-4">
      <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          </svg>
        </span>
        <div>
          <p class="font-semibold text-slate-900">Deseja realmente solicitar a reversão desta venda?</p>
          <p class="text-xs text-slate-600">Referência: {{ vendaParaReversao?.referencia || vendaParaReversao?.id }}</p>
        </div>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">Motivo (opcional)</label>
        <textarea v-model="motivoReversao" rows="2" class="rp-input" placeholder="Ex: item lançado por engano, cliente desistiu..." />
      </div>
      <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
        <BotaoBase variante="perigo" @click="modalSolicitarReversaoAberto = false">
          <span class="inline-flex items-center gap-1.5">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
            <span>Cancelar</span>
          </span>
        </BotaoBase>
        <BotaoBase variante="sucesso" @click="solicitarReversao">
          <span class="inline-flex items-center gap-1.5">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Confirmar solicitação</span>
          </span>
        </BotaoBase>
      </div>
    </div>
  </ModalBase>
</template>
