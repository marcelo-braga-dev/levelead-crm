<?php

use App\Enums\InteractionChannel;
use App\Enums\InteractionDirection;
use App\Enums\InteractionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', array_column(InteractionType::cases(), 'value'));
            $table->enum('channel', array_column(InteractionChannel::cases(), 'value'))->nullable();
            $table->enum('direction', array_column(InteractionDirection::cases(), 'value'))->nullable();
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');

            // Campos preparados para CTI/Omnichannel (Fase 2) — populados via webhook, sem migração futura.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('hold_time_seconds')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('outcome_code')->nullable();
            $table->string('phone_dialed')->nullable();
            $table->string('external_provider_ref')->nullable();
            $table->json('channel_metadata')->nullable();

            $table->timestamp('created_at');

            $table->index(['lead_id', 'occurred_at']);
            $table->index('phone_dialed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_interactions');
    }
};
