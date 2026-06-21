<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extrai o endereço de `companies` para uma tabela satélite dedicada (mesmo padrão de
 * `company_places_profiles`/`company_financial_snapshots`) — abre caminho para o endereço ganhar
 * vida própria (edição a partir do Kanban, histórico, geocodificação) sem inchar `companies` mais
 * ainda. Backfill roda antes do `dropColumn` para não perder dado nenhum já importado via CSV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->string('address_type')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->restrictOnDelete();
            $table->string('cep', 8)->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            INSERT INTO addresses (company_id, address_type, logradouro, numero, complemento, bairro, city_id, state_id, cep, created_at, updated_at)
            SELECT id, address_type, logradouro, numero, complemento, bairro, city_id, state_id, cep, NOW(), NOW()
            FROM companies
            WHERE address_type IS NOT NULL
               OR logradouro IS NOT NULL
               OR numero IS NOT NULL
               OR complemento IS NOT NULL
               OR bairro IS NOT NULL
               OR city_id IS NOT NULL
               OR state_id IS NOT NULL
               OR cep IS NOT NULL
        SQL);

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropColumn(['address_type', 'logradouro', 'numero', 'complemento', 'bairro', 'cep']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address_type')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->restrictOnDelete();
            $table->string('cep', 8)->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE companies
            INNER JOIN addresses ON addresses.company_id = companies.id
            SET companies.address_type = addresses.address_type,
                companies.logradouro = addresses.logradouro,
                companies.numero = addresses.numero,
                companies.complemento = addresses.complemento,
                companies.bairro = addresses.bairro,
                companies.city_id = addresses.city_id,
                companies.state_id = addresses.state_id,
                companies.cep = addresses.cep
        SQL);

        Schema::dropIfExists('addresses');
    }
};
