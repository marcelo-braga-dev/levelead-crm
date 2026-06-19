<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('identificador')->nullable();
            $table->string('nome');
            $table->text('cpf')->nullable();
            $table->string('tipo_documento')->nullable();
            $table->string('faixa_etaria')->nullable();
            $table->foreignId('partner_qualification_id')->nullable()->constrained('partner_qualifications')->restrictOnDelete();
            $table->date('data_entrada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_partners');
    }
};
