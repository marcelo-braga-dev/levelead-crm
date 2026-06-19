<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_profile_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_profile_id')->constrained('import_profiles')->cascadeOnDelete();
            $table->string('source_column_label');
            $table->string('target_field');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_profile_column_mappings');
    }
};
