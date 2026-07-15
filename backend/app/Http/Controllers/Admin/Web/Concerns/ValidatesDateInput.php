<?php

namespace App\Http\Controllers\Admin\Web\Concerns;

use Carbon\Carbon;

trait ValidatesDateInput
{
    protected function dataValida(?string $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return false;
        }

        [$ano, $mes, $dia] = array_map('intval', explode('-', $valor));

        return checkdate($mes, $dia, $ano);
    }

    protected function uuidValido(?string $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $valor);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function resolverIntervaloDatas(
        string $inicio,
        string $fim,
        ?Carbon $fallbackInicio = null,
        ?Carbon $fallbackFim = null,
    ): array {
        $fallbackInicio ??= now()->startOfMonth()->startOfDay();
        $fallbackFim ??= now()->endOfDay();

        $inicioCarbon = $this->dataValida($inicio)
            ? Carbon::parse($inicio)->startOfDay()
            : $fallbackInicio->copy()->startOfDay();

        $fimCarbon = $this->dataValida($fim)
            ? Carbon::parse($fim)->endOfDay()
            : $fallbackFim->copy()->endOfDay();

        if ($fimCarbon->lt($inicioCarbon)) {
            $fimCarbon = $inicioCarbon->copy()->endOfDay();
        }

        return [$inicioCarbon, $fimCarbon];
    }

    protected function intervaloDatasValido(string $inicio, string $fim): bool
    {
        if (! $this->dataValida($inicio) || ! $this->dataValida($fim)) {
            return false;
        }

        return Carbon::parse($fim)->gte(Carbon::parse($inicio));
    }
}
