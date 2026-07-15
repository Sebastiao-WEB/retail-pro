<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OrphanStockBalanceMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrateOrphanStockBalancesCommand extends Command
{
    protected $signature = 'stock:migrate-orphan-balances
                            {--destination=LOC-ARM-CENTRAL : Código da localização de destino}
                            {--dry-run : Apenas mostra o que seria feito, sem alterar dados}
                            {--execute : Aplica a migração (obrigatório para alterar produção)}
                            {--force : Confirma execução sem prompt interactivo}
                            {--performed-by= : UUID do utilizador para auditoria (opcional)}';

    protected $description = 'Migra saldos de stock órfãos (localizações apagadas) para uma localização activa de forma segura.';

    public function handle(OrphanStockBalanceMigrationService $service): int
    {
        $destinationCode = (string) $this->option('destination');
        $dryRun = (bool) $this->option('dry-run');
        $execute = (bool) $this->option('execute');

        if ($execute && $dryRun) {
            $this->error('Use apenas --dry-run OU --execute, não ambos.');

            return self::FAILURE;
        }

        if (! $execute) {
            $dryRun = true;
        }

        try {
            $preview = $service->preview($destinationCode);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('=== Migração de stock órfão ===');
        $this->line('Modo: '.($dryRun ? 'SIMULAÇÃO (dry-run)' : 'EXECUÇÃO EM PRODUÇÃO'));
        $this->newLine();

        if ($preview['rows_total'] === 0) {
            $this->info('Nenhum saldo órfão encontrado. Nada a fazer.');

            return self::SUCCESS;
        }

        $this->table(
            ['Campo', 'Valor'],
            [
                ['Localizações órfãs', implode(', ', $preview['orphan_location_ids'])],
                ['Destino', "{$preview['destination']['code']} — {$preview['destination']['name']}"],
                ['Linhas órfãs (total)', (string) $preview['rows_total']],
                ['Linhas com stock > 0', (string) $preview['rows_with_stock']],
                ['Unidades a migrar', number_format($preview['units_to_migrate'], 2, ',', '.')],
                ['Produtos afectados', (string) $preview['products_affected']],
            ]
        );

        if ($preview['sample'] !== []) {
            $this->newLine();
            $this->info('Amostra (top 15 por quantidade):');
            $this->table(
                ['Produto', 'Qtd', 'Local órfã'],
                collect($preview['sample'])->map(fn ($row) => [
                    $row['product_name'],
                    number_format($row['quantity'], 2, ',', '.'),
                    substr($row['orphan_location_id'], 0, 8).'…',
                ])->all()
            );
        }

        $suspeitas = $service->vendasSuspeitas();
        if ($suspeitas !== []) {
            $this->newLine();
            $this->warn('Vendas com quantidades elevadas (rever manualmente — NÃO revertidas automaticamente):');
            $this->table(
                ['Referência', 'Produto', 'Qtd', 'Data'],
                collect($suspeitas)->map(fn ($row) => [
                    $row['referencia'],
                    $row['product_name'],
                    number_format($row['quantity'], 2, ',', '.'),
                    $row['data'],
                ])->all()
            );
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Simulação concluída. Para aplicar: php artisan stock:migrate-orphan-balances --execute');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Confirma a migração em PRODUÇÃO? Esta acção é irreversível sem backup.')) {
            $this->warn('Operação cancelada.');

            return self::SUCCESS;
        }

        $performedBy = $this->option('performed-by');
        if ($performedBy !== null && $performedBy !== '') {
            if (! User::query()->whereKey($performedBy)->exists()) {
                $this->error("Utilizador {$performedBy} não encontrado.");

                return self::FAILURE;
            }
        } else {
            $performedBy = User::query()->where('username', 'admin')->value('id');
        }

        $result = $service->migrar($destinationCode, $performedBy);

        $reportPath = storage_path('logs/orphan-stock-migration-'.now()->format('Y-m-d_His').'.json');
        File::put($reportPath, json_encode([
            'executed_at' => now()->toIso8601String(),
            'preview' => $preview,
            'result' => $result,
            'performed_by' => $performedBy,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info('Migração concluída com sucesso.');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Linhas migradas (stock > 0)', (string) $result['migrated_rows']],
                ['Unidades migradas', number_format($result['migrated_units'], 2, ',', '.')],
                ['Linhas vazias removidas', (string) $result['deleted_empty_rows']],
                ['Produtos recalculados', (string) $result['products_recalculated']],
                ['Destino', $result['destination_code']],
                ['Relatório', $reportPath],
            ]
        );

        $remaining = $service->localizacoesOrfas();
        if ($remaining->isNotEmpty()) {
            $this->error('Atenção: ainda existem localizações órfãs: '.$remaining->implode(', '));

            return self::FAILURE;
        }

        $this->info('Verificação OK: nenhum saldo órfão restante.');

        return self::SUCCESS;
    }
}
