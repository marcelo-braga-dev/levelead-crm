<?php

use App\Enums\LeadStage;
use App\Enums\LossReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->enum('stage', array_column(LeadStage::cases(), 'value'))->default(LeadStage::New->value);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->nullOnDelete();

            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->string('contact_email')->nullable();

            $table->string('interest_level')->nullable();
            $table->string('purchase_potential')->nullable();
            $table->text('qualification_notes')->nullable();

            $table->enum('loss_reason', array_column(LossReason::cases(), 'value'))->nullable();
            $table->text('loss_notes')->nullable();

            $table->decimal('won_value', 18, 2)->nullable();
            $table->foreignId('won_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('won_commission_value', 18, 2)->nullable();

            $table->boolean('is_recycled')->default(false);
            $table->foreignId('recycled_from_lead_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('stage_entered_at')->nullable();
            $table->unsignedInteger('contact_attempts_count')->default(0);

            $table->unsignedInteger('fit_score')->default(0);
            $table->unsignedInteger('intent_score')->default(0);
            $table->unsignedInteger('total_score')->default(0);
            $table->timestamp('score_updated_at')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->string('external_ref')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
