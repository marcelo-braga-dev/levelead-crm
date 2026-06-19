<?php

use App\Enums\LossReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loss_reason_recycle_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('loss_reason', array_column(LossReason::cases(), 'value'))->unique();
            $table->unsignedInteger('suggested_recycle_days_min')->nullable();
            $table->unsignedInteger('suggested_recycle_days_max')->nullable();
            $table->boolean('is_recyclable')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loss_reason_recycle_rules');
    }
};
