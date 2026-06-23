<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lead pode ser PF ou PJ (requisito real do produto) — `companies.cnpj` era obrigatório/único
 * desde a Fase 1, modelando só PJ. Adiciona `person_type` + `cpf` e torna `cnpj` nullable, sem
 * quebrar nenhuma linha existente (todas as companies de hoje são PJ, `person_type` default 'pj').
 * `DB::statement` (não `Schema::table(...)->change()`) porque o projeto não tem `doctrine/dbal`
 * instalado, exigido pelo Laravel para alterar nullability de coluna existente via Schema Builder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('person_type', ['pf', 'pj'])->default('pj')->after('id');
            $table->string('cpf', 11)->nullable()->unique()->after('cnpj');
        });

        DB::statement('ALTER TABLE companies MODIFY cnpj VARCHAR(14) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE companies MODIFY cnpj VARCHAR(14) NOT NULL');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['person_type', 'cpf']);
        });
    }
};
