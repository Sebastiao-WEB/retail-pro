<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ConsolidateStoreFloorStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConsolidateStoreFloorStockCommand extends Command
{
    protected $signature = 'stock:consolidate-store-floor
                            {--dry-run : Apenas mostra o que seria feito}
                            {--execute : Aplica a consolidação}
                            {--force : Confirma execução sem prompt interactivo}
                            {--performed-by= : UUID do utilizador para auditoria}';

    protected $description = 'Unifica stock das caixas (LOC-CX01 + LOC-CX02) num piso de loja partilhado (LOC-LOJA).';

    public function handle(ConsolidateStoreFloorStockService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $execute = (bool) $this->option('execute');

        if ($execute && $dryRun) {
            $this->error('Use apenas --dry-run OU --execute.');

            return self::FAILURE;
        }

        if (! $execute) {
            $dryRun = true;
        }

        try {
            $preview = $service->preview();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('=== Consolidação piso de loja (supermercado) ===');
        $this->line('Modo: '.($dryRun ? 'SIMULAÇÃO' : 'EXECUÇÃO'));
        $this->newLine();

        $this->table(
            ['Campo', 'Valor'],
            [
                ['Destino', "{$preview['store_floor']['code']} — {$preview['store_floor']['name']}"],
                ['Origens', implode(', ', $preview['source_codes'])],
                ['Produtos a fundir', (string) $preview['products_merged']],
                ['Unidades a mover', number_format($preview['units_merged'], 2, ',', '.')],
                ['Linhas origem a limpar', (string) $preview['source_rows_cleared']],
            ]
        );

        $this->newLine();
        $this->comment('O Armazém Central (LOC-ARM-CENTRAL) NÃO é afectado.');
        $this->comment('Após a consolidação, Caixa 1 e Caixa 2 passam a vender do mesmo stock (LOC-LOJA).');
        $this->comment('App desktop/mobile: sem alteração de código — operadores devem voltar a iniciar sessão.');

        if ($dryRun) {
            $this->newLine();
            $this->comment('Simulação concluída. Para aplicar: php artisan stock:consolidate-store-floor --execute');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Confirma consolidação em PRODUÇÃO?')) {
            $this->warn('Operação cancelada.');

            return self::SUCCESS;
        }

        $performedBy = $this->option('performed-by');
        if ($performedBy && ! User::query()->whereKey($performedBy)->exists()) {
            $this->error("Utilizador {$performedBy} não encontrado.");

            return self::FAILURE;
        }
        if (! $performedBy) {
            $performedBy = User::query()->where('username', 'admin')->value('id');
        }

        $result = $service->consolidar($performedBy);

        $reportPath = storage_path('logs/store-floor-consolidation-'.now()->format('Y-m-d_His').'.json');
        File::put($reportPath, json_encode([
            'executed_at' => now()->toIso8601String(),
            'preview' => $preview,
            'result' => $result,
            'performed_by' => $performedBy,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info('Consolidação concluída.');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Piso de loja', $result['store_floor_code']],
                ['Produtos fundidos', (string) $result['products_merged']],
                ['Unidades movidas', number_format($result['units_merged'], 2, ',', '.')],
                ['Utilizadores actualizados', (string) $result['users_updated']],
                ['Relatório', $reportPath],
            ]
        );

        return self::SUCCESS;
    }
}
