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

function formatarMT(valor) {
  return `${new Intl.NumberFormat("pt-MZ", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(valor || 0))} MT`;
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
          <div class="muted">NIF: ${escaparHtml(configuracaoStore.nif || "")}</div>
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
            <tr><th>Item</th><th>Qtd</th><th>Total</th></tr>
          </thead>
          <tbody>
            ${linhasItens}
          </tbody>
        </table>
        <div class="sep"></div>
        <table>
          <tbody>
            <tr><td>Subtotal</td><td style="text-align:right">${formatarMT(venda.subtotal ?? venda.total)}</td></tr>
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
              <th class="px-3 py-2">Data</th>
              <th class="px-3 py-2">Cliente</th>
              <th class="px-3 py-2">Pagamento</th>
              <th class="px-3 py-2">Total</th>
              <th class="px-3 py-2 text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!vendasDoTurnoCaixa.length">
              <td colspan="5" class="px-3 py-8 text-center text-xs text-slate-500">
                Sem vendas no turno atual deste caixa.
              </td>
            </tr>
            <tr v-for="venda in vendasDoTurnoCaixa" :key="venda.id" class="border-t border-slate-100 text-[12px] hover:bg-slate-50">
              <td class="px-3 py-2 text-slate-600">{{ formatarData(venda.data) }}</td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ venda.cliente }}</td>
              <td class="px-3 py-2 text-slate-600">{{ venda.metodoPagamento }}</td>
              <td class="px-3 py-2 font-semibold text-slate-800">{{ formatarMT(venda.total) }}</td>
              <td class="px-3 py-2">
                <div class="flex justify-end gap-2">
                  <BotaoBase variante="secundario" @click="abrirDetalhes(venda)">Detalhes</BotaoBase>
                  <BotaoBase variante="aviso" @click="reimprimirVenda(venda)">Reimprimir</BotaoBase>
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
              <th class="px-3 py-2">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in vendaSelecionada.itens || []" :key="idx" class="border-t border-slate-100">
              <td class="px-3 py-2">{{ item.nome }}</td>
              <td class="px-3 py-2">{{ item.quantidade }}</td>
              <td class="px-3 py-2">{{ formatarMT(item.subtotal) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        <p><strong>Subtotal:</strong> {{ formatarMT(vendaSelecionada.subtotal || vendaSelecionada.total) }}</p>
        <p><strong>Desconto:</strong> - {{ formatarMT(vendaSelecionada.descontoAplicado || 0) }}</p>
        <p><strong>Total:</strong> {{ formatarMT(vendaSelecionada.total) }}</p>
      </div>
    </div>
  </ModalBase>
</template>
