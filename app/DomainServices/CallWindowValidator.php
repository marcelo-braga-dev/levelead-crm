<?php

namespace App\DomainServices;

use App\Models\NationalHoliday;
use Illuminate\Support\Carbon;

/**
 * Janela de ligação permitida (ANATEL/Lei do Telemarketing): dias de semana 9h-21h,
 * sábado 10h-16h, domingo e feriado nacional sempre bloqueados. Usado só para **avisar**
 * o consultor — nunca bloqueia o registro (ver CLAUDE.md, "Avisos de compliance").
 */
class CallWindowValidator
{
    public function isAllowed(Carbon $when): bool
    {
        return $this->reason($when) === null;
    }

    public function reason(Carbon $when): ?string
    {
        if (NationalHoliday::query()->whereDate('date', $when->toDateString())->exists()) {
            return 'Hoje é feriado nacional — fora da janela de ligação permitida.';
        }

        if ($when->isSunday()) {
            return 'Ligações não são permitidas aos domingos.';
        }

        if ($when->isSaturday()) {
            return $when->hour >= 10 && $when->hour < 16
                ? null
                : 'Fora da janela permitida aos sábados (10h–16h).';
        }

        return $when->hour >= 9 && $when->hour < 21
            ? null
            : 'Fora da janela permitida em dias de semana (9h–21h).';
    }
}
