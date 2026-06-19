<?php

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj', 14)->unique();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();

            $table->string('address_type')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->restrictOnDelete();
            $table->string('cep', 8)->nullable();

            $table->enum('matriz_filial', ['matriz', 'filial'])->nullable();
            $table->string('ente_federativo')->nullable();

            $table->foreignId('primary_cnae_id')->nullable()->constrained('cnaes')->restrictOnDelete();
            $table->foreignId('legal_nature_id')->nullable()->constrained('legal_natures')->restrictOnDelete();

            $table->date('data_inicio_atividade')->nullable();
            $table->enum('company_size', ['MEI', 'ME', 'EPP', 'Medio', 'Grande'])->nullable();

            $table->decimal('share_capital', 18, 2)->nullable();
            $table->boolean('is_mei')->default(false);
            $table->date('mei_entry_date')->nullable();
            $table->date('mei_exit_date')->nullable();

            $table->enum('registration_status', array_column(RegistrationStatus::cases(), 'value'))->nullable();
            $table->date('registration_status_date')->nullable();

            $table->enum('tax_regime', ['simples', 'mei', 'presumido', 'real'])->nullable();

            $table->decimal('estimated_revenue_value', 18, 2)->nullable();
            $table->unsignedInteger('employee_count')->nullable();

            $table->decimal('active_federal_debt', 18, 2)->nullable();
            $table->decimal('total_debt', 18, 2)->nullable();

            $table->string('site')->nullable();
            $table->string('data_provider')->nullable();
            $table->string('license_reference')->nullable();
            $table->foreignId('last_import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestamp('last_enriched_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
