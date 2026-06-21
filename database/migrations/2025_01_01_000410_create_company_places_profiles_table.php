<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_places_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->string('google_place_id')->nullable()->unique();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('user_rating_count')->nullable();
            $table->string('primary_type')->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('business_status')->nullable();
            $table->boolean('has_website')->default(false);
            $table->timestamp('last_review_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_places_profiles');
    }
};
