<script setup>
import { onMounted, ref } from "vue";
import BotaoBase from "../../components/BotaoBase.vue";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";
import { mostrarToastSwal } from "../../services/toast";
import { RefreshCcw, Save, X } from "lucide-vue-next";

const configuracoes = useConfiguracaoStore();
const impressorasDisponiveis = ref([]);
const carregandoImpressoras = ref(false);
const bloqueadoAdministrador = true;

onMounted(() => {
  configuracoes.hidratar();
  carregarImpressoras();
});

function guardarConfiguracoes() {
  configuracoes.salvar();
  mostrarToastSwal("Configurações guardadas com sucesso.", "success");
}

async function carregarImpressoras() {
  const fallbackGlobal = Array.isArray(window.__IMPRESSORAS_INSTALADAS) ? window.__IMPRESSORAS_INSTALADAS : [];
  if (!window.api?.listarImpressoras && fallbackGlobal.length) {
    impressorasDisponiveis.value = fallbackGlobal.map((item) => item.displayName || item.name).filter(Boolean);
    if (!configuracoes.impressoraPadrao && impressorasDisponiveis.value.length) {
      configuracoes.definirImpressoraPadrao(impressorasDisponiveis.value[0]);
    }
    return;
  }
  if (!window.api?.listarImpressoras) {
    mostrarToastSwal("Listagem de impressoras disponível apenas no app desktop (Electron).", "warning");
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
      mostrarToastSwal("Nenhuma impressora instalada foi encontrada.", "warning");
    }
    if (!configuracoes.impressoraPadrao && impressorasDisponiveis.value.length) {
      configuracoes.definirImpressoraPadrao(impressorasDisponiveis.value[0]);
    }
  } catch {
    mostrarToastSwal("Falha ao listar impressoras instaladas.", "error");
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
          <p class="mt-1 text-[11px] font-semibold text-amber-700">Edição exclusiva do administrador</p>
        </div>
        <div class="space-y-3 p-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Nome da Empresa</label>
              <input v-model="configuracoes.nomeEmpresa" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">NUIT</label>
              <input v-model="configuracoes.nif" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Email Comercial</label>
              <input v-model="configuracoes.email" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Telefone</label>
              <input v-model="configuracoes.telefone" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Endereço</label>
            <input v-model="configuracoes.endereco" class="rp-input" :disabled="bloqueadoAdministrador" />
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Banco</label>
              <input v-model="configuracoes.banco" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">IBAN / Nº Conta</label>
              <input v-model="configuracoes.iban" class="rp-input" :disabled="bloqueadoAdministrador" />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
            <BotaoBase variante="secundario" :disabled="bloqueadoAdministrador">
              <span class="inline-flex items-center gap-1.5">
                <X :size="14" />
                <span>Cancelar</span>
              </span>
            </BotaoBase>
            <BotaoBase variante="aviso" :disabled="bloqueadoAdministrador" @click="guardarConfiguracoes">
              <span class="inline-flex items-center gap-1.5">
                <Save :size="14" />
                <span>Guardar Alterações</span>
              </span>
            </BotaoBase>
          </div>
        </div>
      </article>

    </div>

    <div class="space-y-4">
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
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Número de cópias</label>
              <input
                :value="configuracoes.copiasImpressao"
                type="number"
                min="1"
                max="5"
                class="rp-input"
                @input="configuracoes.definirCopiasImpressao($event.target.value)"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Largura do talão</label>
              <select :value="configuracoes.larguraTalao" class="rp-input" @change="configuracoes.definirLarguraTalao($event.target.value)">
                <option value="80mm">80mm (padrão)</option>
                <option value="58mm">58mm</option>
              </select>
            </div>
          </div>
          <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700">
            <span>Corte automático</span>
            <input
              :checked="configuracoes.corteAutomatico"
              type="checkbox"
              class="h-4 w-4 accent-amber-500"
              @change="configuracoes.definirCorteAutomatico($event.target.checked)"
            />
          </label>
          <div class="flex justify-end gap-2">
            <BotaoBase variante="secundario" @click="carregarImpressoras">
              <span class="inline-flex items-center gap-1.5">
                <RefreshCcw :size="14" />
                <span>Atualizar lista</span>
              </span>
            </BotaoBase>
            <BotaoBase variante="aviso" @click="guardarConfiguracoes">
              <span class="inline-flex items-center gap-1.5">
                <Save :size="14" />
                <span>Guardar Impressão</span>
              </span>
            </BotaoBase>
          </div>
          <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700">
            <div>
              <p class="font-semibold text-slate-700">Som dos alertas</p>
              <p class="text-[11px] text-slate-500">Toca som em sucesso, aviso, erro e informação</p>
            </div>
            <input
              :checked="configuracoes.somToastsAtivo"
              type="checkbox"
              class="h-4 w-4 accent-amber-500"
              @change="configuracoes.definirSomToastsAtivo($event.target.checked)"
            />
          </label>
        </div>
      </article>
    </div>
  </section>
</template>
