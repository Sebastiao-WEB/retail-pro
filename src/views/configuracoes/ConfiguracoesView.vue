<script setup>
import { onMounted, ref } from "vue";
import BotaoBase from "../../components/BotaoBase.vue";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";

const configuracoes = useConfiguracaoStore();
const impressorasDisponiveis = ref([]);
const carregandoImpressoras = ref(false);
const erroImpressoras = ref("");

onMounted(() => {
  configuracoes.hidratar();
  carregarImpressoras();
});

function guardarConfiguracoes() {
  configuracoes.salvar();
  alert("Configurações guardadas com sucesso (simulação).");
}

async function carregarImpressoras() {
  erroImpressoras.value = "";
  const fallbackGlobal = Array.isArray(window.__IMPRESSORAS_INSTALADAS) ? window.__IMPRESSORAS_INSTALADAS : [];
  if (!window.api?.listarImpressoras && fallbackGlobal.length) {
    impressorasDisponiveis.value = fallbackGlobal.map((item) => item.displayName || item.name).filter(Boolean);
    if (!configuracoes.impressoraPadrao && impressorasDisponiveis.value.length) {
      configuracoes.definirImpressoraPadrao(impressorasDisponiveis.value[0]);
    }
    return;
  }
  if (!window.api?.listarImpressoras) {
    erroImpressoras.value = "Listagem de impressoras disponível apenas no app desktop (Electron).";
    impressorasDisponiveis.value = [];
    return;
  }
  carregandoImpressoras.value = true;
  try {
    const resposta = await window.api.listarImpressoras();
    const listaApi = Array.isArray(resposta) ? resposta : resposta?.printers || [];
    const lista = listaApi.length ? listaApi : fallbackGlobal;
    impressorasDisponiveis.value = lista.map((item) => item.displayName || item.name).filter(Boolean);
    if (!impressorasDisponiveis.value.length) {
      erroImpressoras.value = "Nenhuma impressora instalada foi encontrada.";
    }
    if (!configuracoes.impressoraPadrao && impressorasDisponiveis.value.length) {
      configuracoes.definirImpressoraPadrao(impressorasDisponiveis.value[0]);
    }
  } catch {
    erroImpressoras.value = "Falha ao listar impressoras instaladas.";
    impressorasDisponiveis.value = [];
  } finally {
    carregandoImpressoras.value = false;
  }
}
</script>

<template>
  <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_300px]">
    <div class="space-y-4">
      <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-900">Dados da Empresa</h3>
          <p class="text-xs text-slate-500">Informações para as facturas</p>
        </div>
        <div class="space-y-3 p-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Nome da Empresa</label>
              <input v-model="configuracoes.nomeEmpresa" class="rp-input" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">NUIT / NIF</label>
              <input v-model="configuracoes.nif" class="rp-input" />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Email Comercial</label>
              <input v-model="configuracoes.email" class="rp-input" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Telefone</label>
              <input v-model="configuracoes.telefone" class="rp-input" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Endereço</label>
            <input v-model="configuracoes.endereco" class="rp-input" />
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Banco</label>
              <input v-model="configuracoes.banco" class="rp-input" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">IBAN / Nº Conta</label>
              <input v-model="configuracoes.iban" class="rp-input" />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
            <BotaoBase variante="secundario">Cancelar</BotaoBase>
            <BotaoBase variante="aviso" @click="guardarConfiguracoes">Guardar Alterações</BotaoBase>
          </div>
        </div>
      </article>

      <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-900">Numeração de Documentos</h3>
          <p class="text-xs text-slate-500">Séries e prefixos</p>
        </div>
        <div class="space-y-3 p-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Prefixo Factura</label>
              <input v-model="configuracoes.prefixoFactura" class="rp-input" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Próximo Número</label>
              <input v-model="configuracoes.proximoNumero" type="number" class="rp-input" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Ano</label>
              <input v-model="configuracoes.ano" class="rp-input" />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Prefixo Nota Crédito</label>
              <input v-model="configuracoes.prefixoNotaCredito" class="rp-input" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Prefixo Recibo</label>
              <input v-model="configuracoes.prefixoRecibo" class="rp-input" />
            </div>
          </div>
          <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-slate-600">
            Pré-visualização: <strong class="text-slate-900">{{ configuracoes.prefixoFactura }} {{ configuracoes.ano }}/{{ String(configuracoes.proximoNumero).padStart(4, "0") }}</strong>
          </div>
          <div class="flex justify-end">
            <BotaoBase variante="aviso" @click="guardarConfiguracoes">Guardar</BotaoBase>
          </div>
        </div>
      </article>
    </div>

    <div class="space-y-4">
      <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-900">Notificações Automáticas</h3>
        </div>
        <div class="space-y-3 p-4">
          <label class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-xs text-slate-700">Lembrete de Vencimento</span>
            <input v-model="configuracoes.lembreteVencimento" type="checkbox" class="h-4 w-4 accent-amber-500" />
          </label>
          <label class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-xs text-slate-700">Confirmação de Emissão</span>
            <input v-model="configuracoes.confirmacaoEmissao" type="checkbox" class="h-4 w-4 accent-amber-500" />
          </label>
          <label class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-xs text-slate-700">Alerta de Pagamento</span>
            <input v-model="configuracoes.alertaPagamento" type="checkbox" class="h-4 w-4 accent-amber-500" />
          </label>
          <label class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-xs text-slate-700">Relatório Semanal</span>
            <input v-model="configuracoes.relatorioSemanal" type="checkbox" class="h-4 w-4 accent-amber-500" />
          </label>
          <label class="flex items-center justify-between">
            <span class="text-xs text-slate-700">Alertas de Obrigações Fiscais</span>
            <input v-model="configuracoes.alertasFiscais" type="checkbox" class="h-4 w-4 accent-amber-500" />
          </label>
        </div>
      </article>

      <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-900">Aparência das Facturas</h3>
        </div>
        <div class="space-y-3 p-4">
          <div>
            <p class="mb-2 text-xs font-semibold text-slate-600">Tema / Cor Principal</p>
            <div class="flex gap-2">
              <span class="h-6 w-6 rounded-md border-2 border-slate-900 bg-amber-400" />
              <span class="h-6 w-6 rounded-md bg-blue-500" />
              <span class="h-6 w-6 rounded-md bg-emerald-500" />
              <span class="h-6 w-6 rounded-md bg-purple-500" />
              <span class="h-6 w-6 rounded-md bg-slate-900" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Idioma das Facturas</label>
            <select v-model="configuracoes.idiomaFacturas" class="rp-input">
              <option>Português (Moçambique)</option>
              <option>Português (Portugal)</option>
              <option>English</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Rodapé das Facturas</label>
            <textarea v-model="configuracoes.rodapeFacturas" rows="3" class="rp-input" />
          </div>
          <div class="flex justify-end">
            <BotaoBase variante="aviso" @click="guardarConfiguracoes">Guardar Aparência</BotaoBase>
          </div>
        </div>
      </article>

      <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-900">Impressão do POS</h3>
          <p class="text-xs text-slate-500">Defina a impressora padrão usada no talão</p>
        </div>
        <div class="space-y-3 p-4">
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Impressora padrão</label>
            <select :value="configuracoes.impressoraPadrao" class="rp-input" @change="configuracoes.definirImpressoraPadrao($event.target.value)">
              <option v-if="carregandoImpressoras" value="">A carregar impressoras instaladas...</option>
              <option v-else-if="!impressorasDisponiveis.length" value="">Sem impressoras disponíveis</option>
              <option v-for="impressora in impressorasDisponiveis" :key="impressora" :value="impressora">
                {{ impressora }}
              </option>
            </select>
            <p v-if="erroImpressoras" class="mt-1 text-[11px] text-red-600">{{ erroImpressoras }}</p>
          </div>
          <div class="flex justify-end gap-2">
            <BotaoBase variante="secundario" @click="carregarImpressoras">Atualizar lista</BotaoBase>
            <BotaoBase variante="aviso" @click="guardarConfiguracoes">Guardar Impressão</BotaoBase>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
