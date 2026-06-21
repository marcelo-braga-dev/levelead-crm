<?php

namespace App\Actions\Companies;

/**
 * Casa o cabeçalho de um CSV importado contra os campos esperados de `companies`, tolerando
 * acentuação/maiúsculas/pontuação diferentes (ex.: "Razão Social" ≈ "RAZAO SOCIAL"). Colunas sem
 * match ficam com target `null` para o usuário resolver manualmente na tela de mapeamento.
 *
 * Sócios (`company_partners`) não fazem parte deste mapeamento — decisão de escopo: são uma
 * relação 1:N por linha e ficam para uma importação dedicada futura, casada por CNPJ.
 */
class ResolveColumnMappingAction
{
    /** @var array<string, array<int, string>> */
    private const ALIASES = [
        'cnpj' => ['CNPJ', 'CNPJ BASICO', 'NUMERO CNPJ', 'CNPJ DA EMPRESA'],
        'razao_social' => ['RAZAO SOCIAL', 'RAZAO SOCIAL DA EMPRESA', 'NOME EMPRESARIAL', 'NOME'],
        'nome_fantasia' => ['NOME FANTASIA', 'FANTASIA'],
        'logradouro' => ['LOGRADOURO', 'ENDERECO', 'RUA'],
        'numero' => ['NUMERO', 'NUMERO ENDERECO', 'NUMERO IMOVEL'],
        'complemento' => ['COMPLEMENTO'],
        'bairro' => ['BAIRRO'],
        'cep' => ['CEP'],
        'uf' => ['UF', 'ESTADO', 'SIGLA UF'],
        'city_name' => ['MUNICIPIO', 'CIDADE'],
        'matriz_filial' => ['MATRIZ FILIAL', 'IDENTIFICADOR MATRIZ FILIAL'],
        'ente_federativo' => ['ENTE FEDERATIVO RESPONSAVEL', 'ENTE FEDERATIVO'],
        'cnae_code' => ['CNAE FISCAL PRINCIPAL', 'CNAE PRINCIPAL', 'CODIGO CNAE'],
        'legal_nature_code' => ['NATUREZA JURIDICA', 'CODIGO NATUREZA JURIDICA'],
        'data_inicio_atividade' => ['DATA INICIO ATIVIDADE', 'DATA DE INICIO DA ATIVIDADE'],
        'company_size' => ['PORTE', 'PORTE DA EMPRESA'],
        'share_capital' => ['CAPITAL SOCIAL', 'CAPITAL SOCIAL DA EMPRESA'],
        'is_mei' => ['OPCAO MEI', 'MEI'],
        'mei_entry_date' => ['DATA OPCAO MEI', 'DATA DE OPCAO PELO MEI'],
        'mei_exit_date' => ['DATA EXCLUSAO MEI', 'DATA DE EXCLUSAO DO MEI'],
        'registration_status' => ['SITUACAO CADASTRAL', 'SITUACAO'],
        'registration_status_date' => ['DATA SITUACAO CADASTRAL', 'DATA DA SITUACAO CADASTRAL'],
        'tax_regime' => ['REGIME TRIBUTARIO', 'OPCAO PELO SIMPLES'],
        'estimated_revenue_value' => ['FATURAMENTO ESTIMADO', 'RECEITA ESTIMADA'],
        'employee_count' => ['QUANTIDADE FUNCIONARIOS', 'NUMERO FUNCIONARIOS', 'FUNCIONARIOS'],
        'active_federal_debt' => ['DIVIDA ATIVA', 'DIVIDA ATIVA FEDERAL'],
        'total_debt' => ['DIVIDA TOTAL'],
        'site' => ['SITE', 'WEBSITE'],
        'contact_phone' => ['TELEFONE', 'TELEFONE 1', 'DDD TELEFONE 1'],
        'contact_email' => ['EMAIL', 'CORREIO ELETRONICO'],
        'contact_whatsapp' => ['WHATSAPP'],
    ];

    /**
     * @param  array<int, string>  $sourceColumns
     * @return array<string, string|null> coluna de origem => campo-alvo (ou null sem match)
     */
    public function resolve(array $sourceColumns): array
    {
        $usedTargets = [];
        $mapping = [];

        foreach ($sourceColumns as $column) {
            $target = $this->matchTarget($column, $usedTargets);

            if ($target !== null) {
                $usedTargets[] = $target;
            }

            $mapping[$column] = $target;
        }

        return $mapping;
    }

    /** @return array<int, string> */
    public function targetFields(): array
    {
        return array_keys(self::ALIASES);
    }

    /** @param array<int, string> $usedTargets */
    private function matchTarget(string $column, array $usedTargets): ?string
    {
        $normalizedColumn = $this->normalize($column);

        foreach (self::ALIASES as $field => $aliases) {
            if (in_array($field, $usedTargets, true)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if ($this->normalize($alias) === $normalizedColumn) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        $value = $transliterated !== false ? $transliterated : $value;

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $value));
    }
}
