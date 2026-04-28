<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Configurações da empresa</p>
        <p class="text-sm text-slate-500">Dados institucionais exibidos no POS e usados nos recibos.</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Nome da Empresa</label>
                <input wire:model.defer="nomeEmpresa" type="text" class="rp-input" placeholder="Empresa Demo Lda">
                @error('nomeEmpresa') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">NUIT</label>
                <input wire:model.defer="nif" type="text" class="rp-input" placeholder="400000099">
                @error('nif') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Email Comercial</label>
                <input wire:model.defer="email" type="text" class="rp-input" placeholder="geral@empresa.co.mz">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Telefone</label>
                <input wire:model.defer="telefone" type="text" class="rp-input" placeholder="+258 21 000 000">
                @error('telefone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-slate-600">Endereço</label>
            <input wire:model.defer="endereco" type="text" class="rp-input" placeholder="Av. 25 de Setembro, 420, Maputo, Moçambique">
            @error('endereco') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Banco</label>
                <input wire:model.defer="banco" type="text" class="rp-input" placeholder="BCI">
                @error('banco') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">IBAN / Nº Conta</label>
                <input wire:model.defer="iban" type="text" class="rp-input" placeholder="MZ59 ...">
                @error('iban') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
            <button type="button" wire:click="salvar" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                Guardar configurações
            </button>
        </div>
    </div>
</div>

