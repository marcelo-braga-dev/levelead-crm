<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * Fixture oficial completo: os ~5.571 municípios do IBGE (`database/seeders/data/municipios.json`,
 * gerado a partir de `https://servicodados.ibge.gov.br/api/v1/localidades/municipios`), substituindo
 * a antiga amostra de desenvolvimento (só as 27 capitais). `upsert()` em lotes — 5.571
 * `updateOrCreate()` individuais seria visivelmente lento no `migrate --seed`.
 */
class CitiesSeeder extends Seeder
{
    public function run(): void
    {
        $stateIdsByUf = State::query()->pluck('id', 'uf');
        $municipios = json_decode(file_get_contents(__DIR__.'/data/municipios.json'), true);

        $now = now();
        $rows = [];

        foreach ($municipios as $municipio) {
            if (! $stateIdsByUf->has($municipio['uf'])) {
                continue; // estado fora do seed (não deveria ocorrer com as 27 UFs já seedadas)
            }

            $rows[] = [
                'ibge_code' => $municipio['ibge_code'],
                'name' => $municipio['name'],
                'state_id' => $stateIdsByUf[$municipio['uf']],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            City::query()->upsert($chunk, ['ibge_code'], ['name', 'state_id', 'updated_at']);
        }
    }
}
