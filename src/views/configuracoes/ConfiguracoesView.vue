<script setup>
import { onMounted, ref } from "vue";
import BotaoBase from "../../components/BotaoBase.vue";
import ModalBase from "../../components/ModalBase.vue";
import { useConfiguracaoStore } from "../../store/useConfiguracaoStore";

const configuracoes = useConfiguracaoStore();
const impressorasDisponiveis = ref([]);
const carregandoImpressoras = ref(false);
const erroImpressoras = ref("");
const modalSucessoAberto = ref(false);
const mensagemSucesso = ref("");
const bloqueadoAdministrador = true;

onMounted(() => {
  configuracoes.hidratar();
  carregarImpressoras();
});

function guardarConfiguracoes() {
  configuracoes.salvar();
  mensagemSucesso.value = "Configurações guardadas com sucesso.";
  modalSucessoAberto.value = true;
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
            <BotaoBase variante="secundario" :disabled="bloqueadoAdministrador">Cancelar</BotaoBase>
            <BotaoBase variante="aviso" :disabled="bloqueadoAdministrador" @click="guardarConfiguracoes">Guardar Alterações</BotaoBase>
          </div>
        </div>
      </article>

    </div>

    <div class="space-y-4">
      <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-900">Aparência das Facturas</h3>
          <p class="mt-1 text-[11px] font-semibold text-amber-700">Edição exclusiva do administrador</p>
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
            <select v-model="configuracoes.idiomaFacturas" class="rp-input" :disabled="bloqueadoAdministrador">
              <option>Português (Moçambique)</option>
              <option>Português (Portugal)</option>
              <option>English</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Rodapé das Facturas</label>
            <textarea v-model="configuracoes.rodapeFacturas" rows="3" class="rp-input" :disabled="bloqueadoAdministrador" />
          </div>
          <div class="flex justify-end">
            <BotaoBase variante="aviso" :disabled="bloqueadoAdministrador" @click="guardarConfiguracoes">Guardar Aparência</BotaoBase>
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
            <BotaoBase variante="secundario" @click="carregarImpressoras">Atualizar lista</BotaoBase>
            <BotaoBase variante="aviso" @click="guardarConfiguracoes">Guardar Impressão</BotaoBase>
          </div>
        </div>
      </article>
    </div>
  </section>

  <ModalBase :aberto="modalSucessoAberto" titulo="Alterações guardadas" @fechar="modalSucessoAberto = false">
    <div class="space-y-4">
      <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-white">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <polyline points="20 6 9 17 4 12" />
          </svg>
        </span>
        <p>{{ mensagemSucesso }}</p>
      </div>
      <div class="flex justify-end">
        <BotaoBase variante="sucesso" @click="modalSucessoAberto = false">OK</BotaoBase>
      </div>
    </div>
  </ModalBase>
</template>
