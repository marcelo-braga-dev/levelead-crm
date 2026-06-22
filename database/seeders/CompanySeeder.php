<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Models\City;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\LegalNature;
use App\Models\PartnerQualification;
use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * Uma company de exemplo, fora do fluxo de importação CSV, só para destravar o board localmente.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $state = State::query()->where('uf', 'SP')->first();
        $city = City::query()->where('state_id', $state?->id)->where('name', 'São Paulo')->first();
        $cnae = Cnae::query()->where('code', '6202-3/00')->first();
        $legalNature = LegalNature::query()->where('code', '2062')->first();

        $company = Company::query()->updateOrCreate(
            ['cnpj' => '12345678000195'],
            [
                'razao_social' => 'Exemplo Tecnologia e Serviços LTDA',
                'nome_fantasia' => 'Exemplo Tech',
                'primary_cnae_id' => $cnae?->id,
                'legal_nature_id' => $legalNature?->id,
                'company_size' => 'ME',
                'registration_status' => RegistrationStatus::Active,
                'registration_status_date' => now()->subYears(3),
                'tax_regime' => 'simples',
                'data_inicio_atividade' => now()->subYears(5),
            ],
        );

        $company->address()->updateOrCreate([], ['city_id' => $city?->id, 'state_id' => $state?->id]);

        $company->contacts()->updateOrCreate(
            ['type' => 'phone', 'value' => '11999990000'],
            ['is_primary' => true],
        );

        $partnerQualification = PartnerQualification::query()->where('code', '49')->first();

        $company->partners()->updateOrCreate(
            ['nome' => 'Sócio Exemplo'],
            ['partner_qualification_id' => $partnerQualification?->id, 'data_entrada' => now()->subYears(5)],
        );
    }
}
