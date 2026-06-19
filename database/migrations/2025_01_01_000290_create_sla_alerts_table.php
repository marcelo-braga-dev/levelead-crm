<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->restrictOnDelete();
            $table->enum('type', ['no_interaction_24h', 'negotiation_15d', 'no_return_7d', 'attempts_exhausted']);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_alerts');
    }
};
