<?php

namespace Database\Seeders;

use App\Models\NationalHoliday;
use Illuminate\Database\Seeder;

/**
 * Feriados nacionais de data fixa, para os próximos anos. NÃO inclui feriados móveis
 * (Carnaval, Sexta-feira Santa, Corpus Christi) — calcular/seedar separadamente quando
 * o CallWindowValidator (Fase 5) precisar deles.
 */
class NationalHolidaysSeeder extends Seeder
{
    public function run(): void
    {
        $fixedHolidays = [
            ['month' => 1, 'day' => 1, 'name' => 'Confraternização Universal'],
            ['month' => 4, 'day' => 21, 'name' => 'Tiradentes'],
            ['month' => 5, 'day' => 1, 'name' => 'Dia do Trabalho'],
            ['month' => 9, 'day' => 7, 'name' => 'Independência do Brasil'],
            ['month' => 10, 'day' => 12, 'name' => 'Nossa Senhora Aparecida'],
            ['month' => 11, 'day' => 2, 'name' => 'Finados'],
            ['month' => 11, 'day' => 15, 'name' => 'Proclamação da República'],
            ['month' => 12, 'day' => 25, 'name' => 'Natal'],
        ];

        $currentYear = (int) now()->format('Y');

        foreach ([$currentYear, $currentYear + 1] as $year) {
            foreach ($fixedHolidays as $holiday) {
                $date = sprintf('%04d-%02d-%02d', $year, $holiday['month'], $holiday['day']);

                NationalHoliday::query()->updateOrCreate(['date' => $date], ['name' => $holiday['name']]);
            }
        }
    }
}
